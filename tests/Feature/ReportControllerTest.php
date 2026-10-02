<?php

use App\Models\Asset;
use App\Models\Certificate;
use App\Models\Client;
use App\Models\Inspection;
use App\Models\InspectionPhoto;
use App\Models\Site;
use App\Models\User;
use App\Reports\ReportDataBuilder;
use App\Reports\ReportFilters;
use App\Reports\ReportPhoto;
use App\ReportType;
use Illuminate\Support\Facades\Storage;
use Spatie\LaravelPdf\Facades\Pdf;

beforeEach(function (): void {
    Storage::fake('report-tests');
    config()->set('fire-tracker.report.disk', 'report-tests');
});

function reportQuery(Client $client, array $overrides = []): array
{
    return array_replace([
        'type' => 'inspection',
        'client_id' => $client->id,
        'date_from' => '2026-09-01',
        'date_to' => '2026-10-01',
    ], $overrides);
}

test('report endpoint requires a verified user', function () {
    $client = Client::factory()->create();
    $query = reportQuery($client);

    $this->get(route('reports.pdf', $query))->assertRedirect(route('login'));
    $this->actingAs(User::factory()->unverified()->create())
        ->get(route('reports.pdf', $query))->assertRedirect(route('verification.notice'));
});

test('report filters reject invalid types dates ids and sites from another client', function () {
    $user = User::factory()->create();
    $client = Client::factory()->create();
    $otherSite = Site::factory()->create();
    Pdf::fake();

    $this->actingAs($user)->get(route('reports.pdf', reportQuery($client, [
        'type' => 'unknown',
        'site_id' => $otherSite->id,
        'date_from' => '2026-10-02',
        'date_to' => '2026-10-01',
    ])))->assertSessionHasErrors(['type', 'site_id', 'date_from', 'date_to']);

    $this->get(route('reports.pdf', reportQuery($client, ['client_id' => 999999])))
        ->assertSessionHasErrors('client_id');
});

test('all four report types render their own introduction using shared record sections', function (string $type, string $message) {
    $user = User::factory()->create();
    $client = Client::factory()->create(['company_name' => 'Acme Hotel']);
    $site = Site::factory()->for($client)->create(['site_name' => 'Main Building']);
    $asset = Asset::factory()->for($site)->create(['serial_number' => 'EX-101']);
    Inspection::factory()->for($asset)->create(['inspection_date' => '2026-09-20', 'notes' => 'Pressure checked']);
    $pdf = Pdf::fake();

    $this->actingAs($user)->get(route('reports.pdf', reportQuery($client, ['type' => $type])))
        ->assertOk()->assertHeader('Content-Type', 'application/pdf')
        ->assertHeader('Cache-Control', 'no-store, private');

    $pdf->assertViewIs('reports.'.$type);
    $pdf->assertSee([$message, 'Main Building', 'EX-101', 'Pressure checked']);
    $this->assertDatabaseCount('inspections', 1);
})->with([
    ['inspection', 'Inspection activity recorded'],
    ['service', 'does not yet identify a separate service activity type'],
    ['maintenance', 'does not yet record a distinct maintenance activity type'],
    ['audit', 'does not state regulatory compliance'],
]);

test('historical inactive records remain available and entered in error records require an explicit filter', function () {
    $user = User::factory()->create();
    $client = Client::factory()->create(['is_active' => false]);
    $site = Site::factory()->for($client)->create(['is_active' => false]);
    $asset = Asset::factory()->for($site)->create(['status' => 'decommissioned']);
    $inspection = Inspection::factory()->for($asset)->create(['inspection_date' => '2026-09-20']);
    $pdf = Pdf::fake();

    $this->actingAs($user)->get(route('reports.pdf', reportQuery($client)))->assertOk();
    $pdf->assertSee(['decommissioned', 'inactive']);

    $inspection->forceFill(['entered_in_error_at' => now()])->save();
    $pdf = Pdf::fake();
    $this->get(route('reports.pdf', reportQuery($client)))->assertOk();
    $pdf->assertDontSee('Equipment inspected');

    $pdf = Pdf::fake();
    $this->get(route('reports.pdf', reportQuery($client, ['include_entered_in_error' => 1])))->assertOk();
    $pdf->assertSee('entered in error');
    $this->assertDatabaseHas('inspections', ['id' => $inspection->id, 'asset_id' => $asset->id]);
});

test('selected site and period scope activity while audit retains its equipment inventory', function () {
    $this->travelTo(now()->setDate(2026, 10, 1)->startOfDay());
    $user = User::factory()->create();
    $client = Client::factory()->create();
    $selectedSite = Site::factory()->for($client)->create(['site_name' => 'Selected Site']);
    $otherSite = Site::factory()->for($client)->create(['site_name' => 'Other Site']);
    $selectedAsset = Asset::factory()->for($selectedSite)->create(['serial_number' => 'SELECTED-1']);
    $otherAsset = Asset::factory()->for($otherSite)->create(['serial_number' => 'OTHER-1']);
    Inspection::factory()->for($selectedAsset)->create(['inspection_date' => '2026-09-20', 'notes' => 'Within period']);
    Inspection::factory()->for($selectedAsset)->create(['inspection_date' => '2026-08-20', 'notes' => 'Outside period']);
    Inspection::factory()->for($otherAsset)->create(['inspection_date' => '2026-09-20', 'notes' => 'Other site work']);
    Certificate::factory()->for($selectedSite)->create(['certificate_type' => 'Site Fire Certificate']);
    $pdf = Pdf::fake();

    $this->actingAs($user)->get(route('reports.pdf', reportQuery($client, [
        'type' => 'audit', 'site_id' => $selectedSite->id,
    ])))->assertOk();

    $pdf->assertSee(['Selected Site', 'SELECTED-1', 'Within period', 'Site Fire Certificate']);
    $pdf->assertDontSee(['Other Site', 'OTHER-1', 'Outside period', 'Other site work']);
});

test('audit totals exclude inactive records from current overdue and expired counts', function () {
    $this->travelTo(now()->setDate(2026, 10, 1)->startOfDay());
    $client = Client::factory()->create();
    $activeSite = Site::factory()->for($client)->create();
    $inactiveSite = Site::factory()->for($client)->create(['is_active' => false]);
    Asset::factory()->for($activeSite)->create(['next_service_due' => '2026-09-01', 'status' => 'active']);
    Asset::factory()->for($activeSite)->create(['next_service_due' => '2026-09-01', 'status' => 'decommissioned']);
    Certificate::factory()->for($activeSite)->create(['expiry_date' => '2026-09-01']);
    Certificate::factory()->for($inactiveSite)->create(['expiry_date' => '2026-09-01']);

    $report = app(ReportDataBuilder::class)->build(new ReportFilters(
        ReportType::Audit, $client->id, null, '2026-09-01', '2026-10-01', false,
    ));

    expect($report->summary)->toMatchArray([
        'assets' => 2, 'overdue_assets' => 1,
        'certificates' => 2, 'expired_certificates' => 1,
    ]);
});

test('report embeds bounded private photos from their recorded disk and tolerates missing photos', function (string $disk) {
    $user = User::factory()->create();
    $client = Client::factory()->create();
    $site = Site::factory()->for($client)->create();
    $asset = Asset::factory()->for($site)->create();
    $inspection = Inspection::factory()->for($asset)->create(['inspection_date' => '2026-09-20']);
    Storage::fake($disk);
    $image = imagecreatetruecolor(32, 16);
    ob_start();
    imagejpeg($image);
    $bytes = ob_get_clean();
    Storage::disk($disk)->put('evidence/available.jpg', $bytes);
    InspectionPhoto::factory()->for($inspection)->create(['storage_disk' => $disk, 'storage_path' => 'evidence/available.jpg']);
    InspectionPhoto::factory()->for($inspection)->create(['storage_disk' => $disk, 'storage_path' => 'evidence/missing.jpg']);
    $pdf = Pdf::fake();

    $this->actingAs($user)->get(route('reports.pdf', reportQuery($client)))->assertOk();
    $pdf->assertSee(['data:image/jpeg;base64,', 'Image unavailable']);
})->with(['local', 's3']);

test('report letterhead can embed a private configured company logo', function (string $disk) {
    $user = User::factory()->create();
    $client = Client::factory()->create();
    Storage::fake($disk);
    $image = imagecreatetruecolor(32, 16);
    ob_start();
    imagejpeg($image);
    $bytes = ob_get_clean();
    Storage::disk($disk)->put('branding/logo.jpg', $bytes);
    config()->set('fire-tracker.report.logo_disk', $disk);
    config()->set('fire-tracker.report.logo_path', 'branding/logo.jpg');
    $pdf = Pdf::fake();

    $this->actingAs($user)->get(route('reports.pdf', reportQuery($client)))->assertOk();
    $pdf->assertSee(['Company logo', 'data:image/jpeg;base64,']);
})->with(['local', 's3']);

test('report rejects oversized data instead of silently truncating it', function () {
    $user = User::factory()->create();
    $client = Client::factory()->create();
    $site = Site::factory()->for($client)->create();
    $asset = Asset::factory()->for($site)->create();
    Inspection::factory()->count(3)->for($asset)->create(['inspection_date' => '2026-09-20']);
    config()->set('fire-tracker.report.max_inspections', 2);
    Pdf::fake();

    $this->actingAs($user)->get(route('reports.pdf', reportQuery($client)))
        ->assertSessionHasErrors('date_from');
});

test('report photo derivation reduces dimensions without changing private source evidence', function () {
    $inspection = Inspection::factory()->create();
    Storage::fake('local');
    $image = imagecreatetruecolor(1600, 800);
    ob_start();
    imagejpeg($image);
    $original = ob_get_clean();
    Storage::disk('local')->put('evidence/original.jpg', $original);
    $photo = InspectionPhoto::factory()->for($inspection)->create([
        'storage_disk' => 'local', 'storage_path' => 'evidence/original.jpg',
    ]);

    $uri = app(ReportPhoto::class)->dataUri($photo);
    $derived = base64_decode(substr($uri, strlen('data:image/jpeg;base64,')), true);

    expect(getimagesizefromstring($derived))->toMatchArray([0 => 1200, 1 => 600]);
    expect(Storage::disk('local')->get('evidence/original.jpg'))->toBe($original);
});

test('download disposition uses a safe filename', function () {
    $user = User::factory()->create();
    $client = Client::factory()->create(['company_name' => 'Acme / Hotel']);
    Pdf::fake();

    $this->actingAs($user)->get(route('reports.pdf', reportQuery($client, ['disposition' => 'download'])))
        ->assertOk()->assertHeader('Content-Disposition', 'attachment; filename="inspection-report-acme-hotel-2026-10-01.pdf"');
});

test('renderer failure returns a controlled error without a partial artifact', function () {
    $user = User::factory()->create();
    $client = Client::factory()->create();
    config()->set('laravel-pdf.driver', 'unavailable');

    $this->actingAs($user)->get(route('reports.pdf', reportQuery($client)))
        ->assertStatus(503)
        ->assertSee('The PDF could not be generated right now. Please try again later.');
});

test('DOMPDF renders a multi-page A4 report with private inspection evidence', function () {
    $this->travelTo(now()->setDate(2026, 10, 1)->startOfDay());
    $user = User::factory()->create();
    $client = Client::factory()->create();
    $site = Site::factory()->for($client)->create();
    $asset = Asset::factory()->for($site)->create();
    $inspections = Inspection::factory()->count(25)->for($asset)->create([
        'inspection_date' => '2026-09-20',
        'notes' => str_repeat('Inspection note with equipment observations. ', 8),
    ]);
    Storage::fake('local');
    $image = imagecreatetruecolor(1600, 800);
    ob_start();
    imagejpeg($image);
    $bytes = ob_get_clean();
    Storage::disk('local')->put('evidence/report.jpg', $bytes);
    InspectionPhoto::factory()->for($inspections->first())->create([
        'storage_disk' => 'local', 'storage_path' => 'evidence/report.jpg',
    ]);

    $response = $this->actingAs($user)->get(route('reports.pdf', reportQuery($client)))->assertOk();
    $pdf = $response->getContent();

    expect(substr($pdf, 0, 4))->toBe('%PDF');
    expect(preg_match_all('/\/Type\s*\/Page\b/', $pdf))->toBeGreaterThan(1);
    expect(preg_match('/\/Subtype\s*\/Image\b/', $pdf))->toBe(1);
    $this->assertDatabaseCount('inspections', 25);
});
