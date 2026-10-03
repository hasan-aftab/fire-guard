<?php

use App\Models\Asset;
use App\Models\Certificate;
use App\Models\Client;
use App\Models\GeneratedReport;
use App\Models\Inspection;
use App\Models\Site;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\LaravelPdf\Facades\Pdf;
use Spatie\LaravelPdf\Support\DummyPdf;

function generatedReportInput(Client $client, array $changes = []): array
{
    return array_replace([
        'type' => 'inspection',
        'client_id' => $client->id,
        'date_from' => '2026-09-01',
        'date_to' => '2026-09-30',
    ], $changes);
}

test('report history and artifact actions require a verified user', function () {
    $report = GeneratedReport::factory()->create();
    $routes = [
        route('reports.index'),
        route('reports.show', $report),
        route('reports.download', $report),
    ];
    foreach ($routes as $url) {
        $this->get($url)->assertRedirect(route('login'));
        $this->actingAs(User::factory()->unverified()->create())->get($url)
            ->assertRedirect(route('verification.notice'));
        auth()->logout();
    }

    $this->post(route('reports.store'), [])->assertRedirect(route('login'));
    $this->post(route('reports.regenerate', $report))->assertRedirect(route('login'));
    $this->actingAs(User::factory()->unverified()->create())
        ->post(route('reports.store'), [])->assertRedirect(route('verification.notice'));
    $this->post(route('reports.regenerate', $report))
        ->assertRedirect(route('verification.notice'));
});

test('generation stores a private PDF and records its disk author snapshot and checksum', function () {
    $this->travelTo(now()->setDate(2026, 10, 1)->startOfDay());
    $user = User::factory()->create(['name' => 'Report Author']);
    $client = Client::factory()->create(['company_name' => 'Original Client']);
    $site = Site::factory()->for($client)->create(['site_name' => 'Original Site']);
    $asset = Asset::factory()->for($site)->create();
    Inspection::factory()->for($asset)->create(['inspection_date' => '2026-09-01']);
    Storage::fake('local');
    Pdf::fake();

    $this->actingAs($user)->post(route('reports.store'), generatedReportInput($client, [
        'site_id' => $site->id,
    ]))->assertRedirect(route('reports.index'));

    $report = GeneratedReport::query()->sole();
    Storage::disk('local')->assertExists($report->storage_path);
    expect($report->storage_disk)->toBe('local');
    expect($report->generated_by)->toBe($user->id);
    expect($report->metadata)->toMatchArray([
        'client_name' => 'Original Client',
        'site_name' => 'Original Site',
        'generated_by_name' => 'Report Author',
    ]);
    expect($report->file_size)->toBe(strlen(DummyPdf::content()));
    expect($report->sha256)->toBe(hash('sha256', DummyPdf::content()));
    expect(Storage::disk('local')->get($report->storage_path))->toBe(DummyPdf::content());
    $this->assertDatabaseCount('inspections', 1);
    $this->assertDatabaseCount('assets', 1);
    $this->assertDatabaseCount('clients', 1);

    $this->get(route('reports.index'))->assertInertia(fn (Assert $page) => $page
        ->component('reports')
        ->where('counts.reports', 1)
        ->has('records.data', 1)
        ->where('records.data.0.client_name', 'Original Client')
        ->where('recentReport.id', $report->id)
        ->has('reportTypes', 4)
        ->etc());
});

test('site ownership and date order are validated before storage', function () {
    $client = Client::factory()->create();
    $otherSite = Site::factory()->create();
    Storage::fake('local');
    Pdf::fake();

    $this->actingAs(User::factory()->create())->post(route('reports.store'), generatedReportInput($client, [
        'site_id' => $otherSite->id,
        'date_from' => '2026-10-01',
        'date_to' => '2026-09-01',
    ]))->assertSessionHasErrors(['site_id', 'date_from', 'date_to']);

    $this->assertDatabaseCount('generated_reports', 0);
    expect(Storage::disk('local')->allFiles('fire-tracker/reports'))->toBe([]);
});

test('original view and download read saved bytes after current data and default disk change', function () {
    $user = User::factory()->create();
    $client = Client::factory()->create(['company_name' => 'Before']);
    $site = Site::factory()->for($client)->create();
    $asset = Asset::factory()->for($site)->create();
    Inspection::factory()->for($asset)->create(['inspection_date' => '2026-09-10']);
    Storage::fake('local');
    Storage::fake('archive');
    Pdf::fake();
    config()->set('fire-tracker.disk', 'local');

    $this->actingAs($user)->post(route('reports.store'), generatedReportInput($client, ['site_id' => $site->id]))
        ->assertRedirect(route('reports.index'));
    $old = GeneratedReport::query()->sole();
    $originalBytes = Storage::disk('local')->get($old->storage_path);

    $client->update(['company_name' => 'After', 'is_active' => false]);
    $site->update(['is_active' => false]);
    $asset->update(['status' => 'decommissioned']);
    config()->set('fire-tracker.disk', 'archive');
    $this->get(route('reports.show', $old))->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');
    expect($this->get(route('reports.show', $old))->streamedContent())->toBe($originalBytes);
    $download = $this->get(route('reports.download', $old))->assertOk();
    expect($download->streamedContent())->toBe($originalBytes);
    expect($download->headers->get('Content-Disposition'))->toContain('attachment');
    expect($this->get(route('reports.download', $old).'?storage_path=some-other-file.pdf')->streamedContent())
        ->toBe($originalBytes);

    $this->post(route('reports.store'), generatedReportInput($client, ['site_id' => $site->id]))
        ->assertRedirect(route('reports.index'));
    $new = GeneratedReport::query()->latest('id')->firstOrFail();
    expect($new->storage_disk)->toBe('archive');
    expect($old->storage_disk)->toBe('local');
    expect($new->metadata['client_name'])->toBe('After');
    expect($old->fresh()->metadata['client_name'])->toBe('Before');
    expect(Storage::disk('local')->get($old->storage_path))->toBe($originalBytes);
    Storage::disk('archive')->assertExists($new->storage_path);
});

test('type edits affect new report data while saved report bytes remain unchanged', function () {
    $client = Client::factory()->create();
    $site = Site::factory()->for($client)->create();
    $asset = Asset::factory()->for($site)->create();
    $certificate = Certificate::factory()->for($site)->create();
    Storage::fake('local');
    Pdf::fake();
    $this->actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]));

    $this->post(route('reports.store'), generatedReportInput($client, [
        'type' => 'audit', 'site_id' => $site->id,
    ]))->assertRedirect(route('reports.index'));
    $saved = GeneratedReport::query()->sole();
    $originalBytes = Storage::disk('local')->get($saved->storage_path);

    $assetType = $asset->assetType;
    $this->put(route('asset-types.update', $assetType), [
        'type_name' => 'Updated Equipment',
        'default_interval_months' => $assetType->default_interval_months,
    ])->assertSessionHasNoErrors();
    $this->put(route('certificate-types.update', $certificate->certificateType), [
        'name' => 'Updated Certificate',
    ])->assertSessionHasNoErrors();
    expect($assetType->refresh()->type_name)->toBe('Updated Equipment');
    expect($certificate->certificateType->refresh()->name)->toBe('Updated Certificate');
    Pdf::fake();

    $this->get(route('reports.pdf', generatedReportInput($client, [
        'type' => 'audit', 'site_id' => $site->id,
    ])))->assertOk();
    Pdf::assertSee(['Updated Equipment', 'Updated Certificate']);
    expect(Storage::disk('local')->get($saved->storage_path))->toBe($originalBytes);
    expect($this->get(route('reports.show', $saved))->streamedContent())->toBe($originalBytes);
});

test('the previous direct PDF link also persists every generated report', function () {
    $client = Client::factory()->create();
    $user = User::factory()->create();
    Storage::fake('local');
    Pdf::fake();

    $this->actingAs($user)->get(route('reports.pdf', generatedReportInput($client)))
        ->assertOk()->assertHeader('Content-Type', 'application/pdf');

    $report = GeneratedReport::query()->sole();
    Storage::disk('local')->assertExists($report->storage_path);
});

test('reporting period includes inspections on both selected dates', function () {
    $client = Client::factory()->create();
    $site = Site::factory()->for($client)->create();
    $asset = Asset::factory()->for($site)->create();
    Inspection::factory()->for($asset)->create(['inspection_date' => '2026-09-01', 'notes' => 'First boundary']);
    Inspection::factory()->for($asset)->create(['inspection_date' => '2026-09-30', 'notes' => 'Last boundary']);
    Inspection::factory()->for($asset)->create(['inspection_date' => '2026-10-01', 'notes' => 'Outside boundary']);
    Storage::fake('local');
    $pdf = Pdf::fake();

    $this->actingAs(User::factory()->create())->post(route('reports.store'), generatedReportInput($client))
        ->assertRedirect(route('reports.index'));
    $pdf->assertSee(['First boundary', 'Last boundary']);
    $pdf->assertDontSee('Outside boundary');
});

test('generate again creates a linked new row and unique PDF without changing the original', function () {
    $user = User::factory()->create();
    $client = Client::factory()->create(['company_name' => 'First Name']);
    Storage::fake('local');
    Pdf::fake();
    $this->actingAs($user)->post(route('reports.store'), generatedReportInput($client))
        ->assertRedirect(route('reports.index'));
    $original = GeneratedReport::query()->sole();
    $oldPath = $original->storage_path;
    $oldHash = $original->sha256;
    $oldBytes = Storage::disk('local')->get($oldPath);

    $client->update(['company_name' => 'Second Name']);
    $this->post(route('reports.regenerate', $original))->assertRedirect(route('reports.index'));

    $this->assertDatabaseCount('generated_reports', 2);
    $new = GeneratedReport::query()->latest('id')->firstOrFail();
    expect($new->regenerated_from_id)->toBe($original->id);
    expect($new->storage_path)->not->toBe($oldPath);
    expect($new->metadata['client_name'])->toBe('Second Name');
    expect($original->fresh()->sha256)->toBe($oldHash);
    expect(Storage::disk('local')->get($oldPath))->toBe($oldBytes);
    Storage::disk('local')->assertExists($new->storage_path);
});

test('history filters stored snapshots on the server and paginates', function () {
    $user = User::factory()->create();
    $client = Client::factory()->create();
    $site = Site::factory()->for($client)->create();
    $otherClient = Client::factory()->create();
    $first = GeneratedReport::factory()->for($client)->create([
        'report_type' => 'audit', 'site_id' => $site->id,
        'date_from' => '2026-09-01', 'date_to' => '2026-09-30',
        'generated_at' => '2026-10-01 10:00:00',
    ]);
    GeneratedReport::factory()->for($otherClient)->create([
        'report_type' => 'inspection', 'generated_at' => '2026-09-01 10:00:00',
    ]);
    $this->actingAs($user)->get(route('reports.index', [
        'type' => 'audit', 'client_id' => $client->id, 'site_id' => $site->id,
        'generated_from' => '2026-10-01', 'generated_to' => '2026-10-01',
        'period_from' => '2026-09-01', 'period_to' => '2026-09-30',
    ]))->assertInertia(fn (Assert $page) => $page->component('reports')
        ->has('records.data', 1)->where('records.data.0.id', $first->id)
        ->where('records.total', 1)->missing('filters.page')->etc());

    GeneratedReport::factory()->count(11)->for($client)->create();
    $this->get(route('reports.index'))->assertInertia(fn (Assert $page) => $page
        ->component('reports')->has('records.data', 10)->where('records.total', 13)->etc());
    $this->get(route('reports.index', ['page' => 99]))
        ->assertRedirect(route('reports.index', ['page' => 2]));
});

test('history rejects a site filter from another client', function () {
    $client = Client::factory()->create();
    $otherSite = Site::factory()->create();

    $this->actingAs(User::factory()->create())->get(route('reports.index', [
        'client_id' => $client->id,
        'site_id' => $otherSite->id,
    ]))->assertSessionHasErrors('site_id');
});

test('author snapshot remains readable after the generating user is removed', function () {
    $author = User::factory()->create(['name' => 'Former Author']);
    $viewer = User::factory()->create();
    $client = Client::factory()->create();
    Storage::fake('local');
    Pdf::fake();
    $this->actingAs($author)->post(route('reports.store'), generatedReportInput($client))
        ->assertRedirect(route('reports.index'));
    $report = GeneratedReport::query()->sole();

    $author->delete();
    expect($report->fresh()->generated_by)->toBeNull();
    $this->actingAs($viewer)->get(route('reports.index'))->assertInertia(fn (Assert $page) => $page
        ->component('reports')->where('records.data.0.generated_by_name', 'Former Author')->etc());
});

test('missing saved PDFs fail clearly without regenerating or exposing paths', function () {
    $report = GeneratedReport::factory()->create();
    Storage::fake('local');
    Pdf::fake();

    $response = $this->actingAs(User::factory()->create())->get(route('reports.show', $report));
    $response->assertNotFound()->assertSee('The saved PDF is unavailable.');
    expect($response->getContent())->not->toContain($report->storage_path);
    $this->assertDatabaseCount('generated_reports', 1);
});

test('changed saved PDF bytes fail the integrity check', function () {
    $report = GeneratedReport::factory()->create();
    Storage::fake('local');
    Storage::disk('local')->put($report->storage_path, 'changed bytes');

    $this->actingAs(User::factory()->create())->get(route('reports.download', $report))
        ->assertNotFound()->assertSee('The saved PDF is unavailable.');
});

test('storage failure cannot create a history row', function () {
    $client = Client::factory()->create();
    Pdf::fake();
    config()->set('fire-tracker.disk', 'missing-disk');

    $this->actingAs(User::factory()->create())->post(route('reports.store'), generatedReportInput($client))
        ->assertSessionHasErrors('report');
    $this->assertDatabaseCount('generated_reports', 0);
});

test('database failure after storage cleans up the new PDF', function () {
    $client = Client::factory()->create();
    $user = User::factory()->create();
    Storage::fake('local');
    Pdf::fake();
    GeneratedReport::creating(function (): void {
        throw new RuntimeException('Database write failed.');
    });

    try {
        $this->actingAs($user)->post(route('reports.store'), generatedReportInput($client))
            ->assertSessionHasErrors('report');
    } finally {
        GeneratedReport::flushEventListeners();
    }

    $this->assertDatabaseCount('generated_reports', 0);
    expect(Storage::disk('local')->allFiles('fire-tracker/reports'))->toBe([]);
});

test('history does not provide a permanent deletion route', function () {
    $report = GeneratedReport::factory()->create();
    $this->actingAs(User::factory()->create())->delete('/reports/'.$report->id)->assertNotFound();
    $this->assertDatabaseCount('generated_reports', 1);
});
