<?php

use App\Models\Asset;
use App\Models\Certificate;
use App\Models\CertificateType;
use App\Models\Inspection;
use App\Models\ManagedUpload;
use App\Models\Site;
use App\Models\Technician;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Support\SessionKey;

test('managed uploads require a verified account', function () {
    $this->postJson(route('managed-uploads.store'), [])->assertUnauthorized();
    $this->actingAs(User::factory()->unverified()->create())
        ->post(route('managed-uploads.store'), [])
        ->assertRedirect(route('verification.notice'));
});

test('create forms return record ids for file handoff without an early success toast', function () {
    $user = User::factory()->create();
    $asset = Asset::factory()->create();
    $technician = Technician::factory()->create();
    $this->actingAs($user)->post(route('inspections.store'), [
        'asset_id' => $asset->id,
        'technician_id' => $technician->id,
        'inspection_date' => '2026-10-01',
        'result' => 'pass',
        'upload_pending' => '1',
    ])->assertRedirect()->assertSessionHas(SessionKey::FLASH_DATA, fn (array $flash) => $flash['createdRecordId'] === Inspection::query()->firstOrFail()->id && ! isset($flash['toast']));

    $site = Site::factory()->create();
    $this->post(route('certificates.store'), [
        'site_id' => $site->id,
        'certificate_type_id' => CertificateType::factory()->create()->id,
        'issue_date' => '2026-10-01',
        'expiry_date' => '2027-10-01',
        'upload_pending' => '1',
    ])->assertRedirect()->assertSessionHas(SessionKey::FLASH_DATA, fn (array $flash) => $flash['createdRecordId'] === Certificate::query()->firstOrFail()->id && ! isset($flash['toast']));
});

test('editing details with selected files waits for upload before showing success', function () {
    $inspection = Inspection::factory()->create();
    $certificate = Certificate::factory()->create();
    $this->actingAs(User::factory()->create())->put(route('inspections.update', $inspection), [
        'asset_id' => $inspection->asset_id,
        'technician_id' => $inspection->technician_id,
        'inspection_date' => '2026-10-01',
        'result' => 'pass',
        'upload_pending' => '1',
    ])->assertRedirect()->assertSessionMissing(SessionKey::FLASH_DATA);

    $this->put(route('certificates.update', $certificate), [
        'site_id' => $certificate->site_id,
        'certificate_type_id' => $certificate->certificate_type_id,
        'issue_date' => '2026-10-01',
        'expiry_date' => '2027-10-01',
        'upload_pending' => '1',
    ])->assertRedirect()->assertSessionMissing(SessionKey::FLASH_DATA);
});

test('a file larger than the PHP POST limit uploads in bounded chunks and attaches to an inspection', function () {
    Storage::fake('local');
    $inspection = Inspection::factory()->create();
    $image = UploadedFile::fake()->image('evidence.jpg');
    $header = file_get_contents($image->getPathname());
    $contents = $header.str_repeat('a', 2_200_000 - strlen($header));
    $user = User::factory()->create();
    $start = $this->actingAs($user)->postJson(route('managed-uploads.store'), [
        'kind' => 'photo', 'record_id' => $inspection->id, 'name' => 'evidence.jpg', 'size' => strlen($contents),
    ])->assertCreated();
    $id = $start->json('id');
    $chunks = str_split($contents, $start->json('chunk_bytes'));
    expect(count($chunks))->toBeGreaterThan(1);

    foreach ($chunks as $index => $chunk) {
        $this->call('PUT', route('managed-uploads.chunk', [$id, $index]), [], [], [], [
            'CONTENT_TYPE' => 'application/octet-stream', 'CONTENT_LENGTH' => strlen($chunk),
        ], $chunk)->assertOk();
    }
    $this->postJson(route('managed-uploads.complete', $id))->assertCreated();

    $photo = $inspection->fresh()->photos->sole();
    expect($photo->file_size)->toBe(2_200_000);
    Storage::disk('local')->assertExists($photo->storage_path);
    $this->assertDatabaseHas('managed_uploads', ['id' => $id, 'status' => 'done']);
    $this->postJson(route('managed-uploads.complete', $id))->assertOk();
});

test('chunk uploads reject wrong owners, gaps, expired sessions, and invalid file content', function () {
    Storage::fake('local');
    $inspection = Inspection::factory()->create();
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $start = $this->actingAs($owner)->postJson(route('managed-uploads.store'), [
        'kind' => 'photo', 'record_id' => $inspection->id, 'name' => 'not-an-image.jpg', 'size' => 4,
    ])->assertCreated();
    $id = $start->json('id');
    $oversizedChunk = str_repeat('x', (int) config('fire-tracker.upload_chunk_bytes') + 1);
    $this->call('PUT', route('managed-uploads.chunk', [$id, 0]), [], [], [], [
        'CONTENT_TYPE' => 'application/octet-stream', 'CONTENT_LENGTH' => strlen($oversizedChunk),
    ], $oversizedChunk)->assertStatus(413);
    $this->actingAs($other)->call('PUT', route('managed-uploads.chunk', [$id, 0]), [], [], [], [
        'CONTENT_TYPE' => 'application/octet-stream', 'CONTENT_LENGTH' => 4,
    ], 'test')->assertNotFound();
    $this->actingAs($owner)->call('PUT', route('managed-uploads.chunk', [$id, 1]), [], [], [], [
        'CONTENT_TYPE' => 'application/octet-stream', 'CONTENT_LENGTH' => 4,
    ], 'test')->assertStatus(409);
    $this->call('PUT', route('managed-uploads.chunk', [$id, 0]), [], [], [], [
        'CONTENT_TYPE' => 'application/octet-stream', 'CONTENT_LENGTH' => 4,
    ], 'test')->assertOk();
    $this->postJson(route('managed-uploads.complete', $id))->assertUnprocessable();
    expect($inspection->photos()->count())->toBe(0);

    ManagedUpload::findOrFail($id)->update(['expires_at' => now()->subMinute()]);
    $this->postJson(route('managed-uploads.complete', $id))->assertStatus(410);
});

test('chunked certificate uploads store a PDF and replace it with an image', function () {
    Storage::fake('local');
    $certificate = Certificate::factory()->create();
    $this->actingAs(User::factory()->create());
    $image = UploadedFile::fake()->image('replacement.png');
    foreach ([['certificate.pdf', "%PDF-1.4\n1 0 obj\n<<>>\nendobj\n%%EOF"], ['replacement.png', file_get_contents($image->getPathname())]] as [$name, $contents]) {
        $start = $this->postJson(route('managed-uploads.store'), [
            'kind' => 'document', 'record_id' => $certificate->id, 'name' => $name, 'size' => strlen($contents),
        ])->assertCreated();
        $id = $start->json('id');
        $this->call('PUT', route('managed-uploads.chunk', [$id, 0]), [], [], [], [
            'CONTENT_TYPE' => 'application/octet-stream', 'CONTENT_LENGTH' => strlen($contents),
        ], $contents)->assertOk();
        $this->postJson(route('managed-uploads.complete', $id))->assertCreated();
        $document = $certificate->fresh()->document;
        Storage::disk('local')->assertExists($document->storage_path);
    }
    expect($certificate->fresh()->document->mime_type)->toBe('image/png');
    expect($certificate->fresh()->document()->count())->toBe(1);
});

test('expired upload chunks are removed by the cleanup command', function () {
    Storage::fake('local');
    $upload = ManagedUpload::factory()->create(['expires_at' => now()->subMinute()]);
    $completed = ManagedUpload::factory()->create(['status' => 'done', 'expires_at' => now()->subMinute()]);
    Storage::disk('local')->put('fire-tracker/pending/'.$upload->id.'/0', 'chunk');
    $this->artisan('fire-tracker:prune-files')->assertSuccessful();
    Storage::disk('local')->assertMissing('fire-tracker/pending/'.$upload->id.'/0');
    $this->assertDatabaseMissing('managed_uploads', ['id' => $upload->id]);
    $this->assertDatabaseMissing('managed_uploads', ['id' => $completed->id]);
});
