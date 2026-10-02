<?php

use App\Actions\ManagedFiles;
use App\Models\Certificate;
use App\Models\CertificateDocument;
use App\Models\Inspection;
use App\Models\InspectionPhoto;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

test('an inspection stores multiple private photos and later uploads append to the collection', function () {
    Storage::fake('local');
    config()->set('fire-tracker.disk', 'local');
    $inspection = Inspection::factory()->create();
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('inspections.photos.store', $inspection), [
        'photos' => [UploadedFile::fake()->image('first.jpg'), UploadedFile::fake()->image('second.png')],
    ])->assertRedirect();
    $this->post(route('inspections.photos.store', $inspection), [
        'photos' => [UploadedFile::fake()->image('third.webp')],
    ])->assertRedirect();

    $photos = $inspection->fresh()->photos;
    expect($photos)->toHaveCount(3);
    foreach ($photos as $photo) {
        expect($photo->storage_disk)->toBe('local')
            ->and($photo->storage_path)->toStartWith('fire-tracker/inspections/'.$inspection->id.'/')
            ->and($photo->storage_path)->not->toContain($photo->original_name);
        Storage::disk('local')->assertExists($photo->storage_path);
    }
    $this->get(route('inspections.index'))->assertInertia(fn (Assert $page) => $page
        ->has('records.data.0.photos', 3)
        ->missing('records.data.0.photos.0.storage_path')
        ->missing('records.data.0.photos.0.storage_disk'));
});

test('photo validation rejects unexpected formats and oversized uploads', function () {
    Storage::fake('local');
    $inspection = Inspection::factory()->create();
    $this->actingAs(User::factory()->create())
        ->post(route('inspections.photos.store', $inspection), ['photos' => [UploadedFile::fake()->create('payload.txt', 10, 'text/plain')]])
        ->assertSessionHasErrors('photos.0');
    $this->post(route('inspections.photos.store', $inspection), [
        'photos' => [UploadedFile::fake()->image('huge.jpg')->size(20481)],
    ])->assertSessionHasErrors('photos.0');
    expect($inspection->photos()->count())->toBe(0);
});

test('image thumbnails are private, bounded, and removed with their original', function () {
    Storage::fake('local');
    $inspection = Inspection::factory()->create();
    $certificate = Certificate::factory()->create();
    $this->actingAs(User::factory()->create());

    $this->post(route('inspections.photos.store', $inspection), [
        'photos' => [UploadedFile::fake()->image('large.jpg', 1200, 800)],
    ])->assertRedirect();
    $photo = $inspection->fresh()->photos->sole();
    $thumbnailPath = $photo->storage_path.'.thumbnail.webp';
    Storage::disk('local')->assertExists($thumbnailPath);
    $dimensions = getimagesizefromstring(Storage::disk('local')->get($thumbnailPath));
    expect($dimensions[0])->toBeLessThanOrEqual(256)
        ->and($dimensions[1])->toBeLessThanOrEqual(256);
    $this->get(route('inspections.photos.thumbnail', [$inspection, $photo]))
        ->assertOk()->assertHeader('Content-Type', 'image/webp');

    $this->post(route('certificates.document.store', $certificate), [
        'document' => UploadedFile::fake()->image('certificate.png', 900, 600),
    ])->assertRedirect();
    $document = $certificate->fresh()->document;
    $documentThumbnail = $document->storage_path.'.thumbnail.webp';
    Storage::disk('local')->assertExists($documentThumbnail);
    $this->get(route('certificates.document.thumbnail', [$certificate, $document]))
        ->assertOk()->assertHeader('Content-Type', 'image/webp');

    $this->delete(route('inspections.photos.destroy', [$inspection, $photo]))->assertRedirect();
    $this->delete(route('certificates.document.destroy', [$certificate, $document]))->assertRedirect();
    Storage::disk('local')->assertMissing($thumbnailPath);
    Storage::disk('local')->assertMissing($documentThumbnail);
});

test('photo access is authenticated, scoped to its inspection, and ignores arbitrary path input', function () {
    Storage::fake('local');
    $inspection = Inspection::factory()->create();
    $other = Inspection::factory()->create();
    $photo = InspectionPhoto::factory()->for($inspection)->create();
    Storage::disk('local')->put($photo->storage_path, 'private image');
    Storage::disk('local')->put('secret.txt', 'secret');

    $url = route('inspections.photos.show', [$inspection, $photo]);
    $this->get($url)->assertRedirect(route('login'));
    $this->actingAs(User::factory()->create())->get($url.'?path=secret.txt')
        ->assertOk()->assertHeader('Content-Type', 'image/jpeg')
        ->assertHeader('X-Content-Type-Options', 'nosniff');
    $this->get(route('inspections.photos.download', [$inspection, $photo]))
        ->assertOk()->assertHeader('content-disposition', 'attachment; filename=photo.jpg');
    $this->get(route('inspections.photos.show', [$other, $photo]))->assertNotFound();
    $this->get(route('inspections.photos.show', [$inspection, 999999]))->assertNotFound();
});

test('photo removal deletes its object and decommissioning or deactivation preserves other evidence', function () {
    Storage::fake('local');
    $inspection = Inspection::factory()->create();
    $photo = InspectionPhoto::factory()->for($inspection)->create();
    Storage::disk('local')->put($photo->storage_path, 'image');
    $this->actingAs(User::factory()->create())->delete(route('inspections.photos.destroy', [$inspection, $photo]))->assertRedirect();
    $this->assertDatabaseMissing('inspection_photos', ['id' => $photo->id]);
    Storage::disk('local')->assertMissing($photo->storage_path);

    $kept = InspectionPhoto::factory()->for($inspection)->create();
    Storage::disk('local')->put($kept->storage_path, 'history');
    $asset = $inspection->asset;
    $site = $asset->site;
    $client = $site->client;
    $this->delete(route('assets.destroy', $asset))->assertRedirect();
    $this->delete(route('sites.destroy', $site))->assertRedirect();
    $this->delete(route('clients.destroy', $client))->assertRedirect();
    expect($inspection->fresh()->photos()->count())->toBe(1);
    Storage::disk('local')->assertExists($kept->storage_path);
});

test('certificate PDF and image uploads are private and replacement removes the prior file', function () {
    Storage::fake('local');
    $certificate = Certificate::factory()->create();
    $this->actingAs(User::factory()->create())->post(route('certificates.document.store', $certificate), [
        'document' => UploadedFile::fake()->create('certificate.pdf', 100, 'application/pdf'),
    ])->assertRedirect();
    $old = $certificate->fresh()->document;
    expect($old)->not->toBeNull();
    Storage::disk('local')->assertExists($old->storage_path);
    $this->get(route('certificates.document.show', [$certificate, $old]))->assertOk();

    $this->post(route('certificates.document.store', $certificate), [
        'document' => UploadedFile::fake()->image('replacement.png'),
    ])->assertRedirect();
    $new = $certificate->fresh()->document;
    expect($new->id)->not->toBe($old->id)
        ->and($new->mime_type)->toBe('image/png');
    Storage::disk('local')->assertExists($new->storage_path);
    Storage::disk('local')->assertMissing($old->storage_path);
    $this->assertDatabaseMissing('certificate_documents', ['id' => $old->id]);
    $this->get(route('certificates.document.show', [$certificate, $old]))->assertNotFound();
    $this->get(route('certificates.document.download', [$certificate, $new]))->assertOk();
});

test('invalid certificate replacement leaves the current document available', function () {
    Storage::fake('local');
    $certificate = Certificate::factory()->create();
    $document = CertificateDocument::factory()->for($certificate)->create();
    Storage::disk('local')->put($document->storage_path, 'original');
    $this->actingAs(User::factory()->create())->post(route('certificates.document.store', $certificate), [
        'document' => UploadedFile::fake()->create('script.php', 1, 'text/plain'),
    ])->assertSessionHasErrors('document');
    expect($certificate->fresh()->document->id)->toBe($document->id);
    Storage::disk('local')->assertExists($document->storage_path);
});

test('storage failure leaves the existing certificate document intact', function () {
    Storage::fake('local');
    $certificate = Certificate::factory()->create();
    $document = CertificateDocument::factory()->for($certificate)->create();
    Storage::disk('local')->put($document->storage_path, 'original');
    config()->set('fire-tracker.disk', 'missing-disk');

    $this->actingAs(User::factory()->create())->post(route('certificates.document.store', $certificate), [
        'document' => UploadedFile::fake()->image('new.jpg'),
    ])->assertSessionHasErrors('document');

    expect($certificate->fresh()->document->id)->toBe($document->id);
    Storage::disk('local')->assertExists($document->storage_path);
});

test('existing files keep their recorded disk after the upload disk changes', function () {
    Storage::fake('local');
    Storage::fake('s3');
    config()->set('fire-tracker.disk', 'local');
    $inspection = Inspection::factory()->create();
    $user = User::factory()->create();
    $this->actingAs($user)->post(route('inspections.photos.store', $inspection), [
        'photos' => [UploadedFile::fake()->image('old.jpg')],
    ])->assertRedirect();
    $old = $inspection->fresh()->photos->first();

    config()->set('fire-tracker.disk', 's3');
    $this->post(route('inspections.photos.store', $inspection), [
        'photos' => [UploadedFile::fake()->image('new.jpg')],
    ])->assertRedirect();
    $new = $inspection->fresh()->photos->last();
    expect($old->storage_disk)->toBe('local')->and($new->storage_disk)->toBe('s3');
    Storage::disk('local')->assertExists($old->storage_path);
    Storage::disk('s3')->assertExists($new->storage_path);
    $this->get(route('inspections.photos.show', [$inspection, $old]))->assertOk();
    $this->get(route('inspections.photos.show', [$inspection, $new]))->assertOk();
    foreach ([$old, $new] as $photo) {
        $stream = app(ManagedFiles::class)->readStream($photo);
        expect(stream_get_contents($stream))->not->toBeFalse();
        fclose($stream);
    }
    $this->delete(route('inspections.photos.destroy', [$inspection, $old]))->assertRedirect();
    Storage::disk('local')->assertMissing($old->storage_path);
    Storage::disk('s3')->assertExists($new->storage_path);
});

test('a pending replacement is recoverable when object deletion is retried', function () {
    Storage::fake('local');
    $document = CertificateDocument::factory()->create(['pending_deletion_at' => now()]);
    Storage::disk('local')->put($document->storage_path, 'old');
    $this->artisan('fire-tracker:prune-files')->assertSuccessful();
    $this->assertDatabaseMissing('certificate_documents', ['id' => $document->id]);
    Storage::disk('local')->assertMissing($document->storage_path);
});

test('certificate documents are protected, scoped, and removable', function () {
    Storage::fake('local');
    $certificate = Certificate::factory()->create();
    $other = Certificate::factory()->create();
    $document = CertificateDocument::factory()->for($certificate)->create();
    Storage::disk('local')->put($document->storage_path, 'document');
    $url = route('certificates.document.show', [$certificate, $document]);

    $this->get($url)->assertRedirect(route('login'));
    $this->actingAs(User::factory()->unverified()->create())->get($url)
        ->assertRedirect(route('verification.notice'));
    $this->actingAs(User::factory()->create())->get($url)->assertOk();
    $this->get(route('certificates.document.show', [$other, $document]))->assertNotFound();
    $this->delete(route('certificates.document.destroy', [$other, $document]))->assertNotFound();
    $this->delete(route('certificates.document.destroy', [$certificate, $document]))->assertRedirect();
    $this->assertDatabaseMissing('certificate_documents', ['id' => $document->id]);
    Storage::disk('local')->assertMissing($document->storage_path);
});
