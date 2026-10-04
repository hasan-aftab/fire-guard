<?php

use App\Models\Asset;
use App\Models\AssetType;
use App\Models\Certificate;
use App\Models\CertificateDocument;
use App\Models\CertificateType;
use App\Models\Client;
use App\Models\FireNotification;
use App\Models\GeneratedReport;
use App\Models\Inspection;
use App\Models\ReminderDelivery;
use App\Models\Site;
use App\Models\Technician;
use App\Models\User;
use Database\Seeders\AssetTypeSeeder;
use Database\Seeders\CertificateTypeSeeder;
use Database\Seeders\FireTrackerDemoSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

test('guests cannot open tracker pages', function (string $route) {
    $this->get(route($route))->assertRedirect(route('login'));
})->with(['clients.index', 'assets.index', 'inspections.index', 'certificates.index', 'technicians.index']);

test('unverified users must verify their email before managing tracker data', function (string $route) {
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)->get(route($route))->assertRedirect(route('verification.notice'));
})->with(['clients.index', 'assets.index', 'inspections.index', 'certificates.index', 'technicians.index']);

test('guests cannot create tracker records', function (string $resource) {
    $this->post(route($resource.'.store'), [])->assertRedirect(route('login'));
})->with(['clients', 'sites', 'assets', 'inspections', 'certificates', 'technicians']);

test('staff can create and update tracker records', function (string $model, string $resource, string $field, string $replacement, string $page) {
    $user = User::factory()->create();
    $payload = $model::factory()->make()->getAttributes();

    $this->actingAs($user)->from(route($page))->post(route($resource.'.store'), $payload)
        ->assertSessionHasNoErrors()->assertRedirect(route($page));
    $record = $model::query()->latest('id')->firstOrFail();
    $this->assertDatabaseHas($record->getTable(), [$field => $payload[$field]]);

    $payload[$field] = $replacement;
    $this->put(route($resource.'.update', $record), $payload)->assertSessionHasNoErrors()->assertRedirect(route($page));
    expect($record->refresh()->getAttribute($field))->toBe($replacement);

    $this->assertModelExists($record);
})->with([
    'client' => [Client::class, 'clients', 'company_name', 'Updated PNG Company', 'clients.index'],
    'site' => [Site::class, 'sites', 'site_name', 'Updated Warehouse', 'clients.index'],
    'asset' => [Asset::class, 'assets', 'serial_number', 'UPDATED-675', 'assets.index'],
    'inspection' => [Inspection::class, 'inspections', 'notes', 'Follow-up inspection', 'inspections.index'],
    'certificate' => [Certificate::class, 'certificates', 'issued_by', 'Updated Fire Safety Authority', 'certificates.index'],
    'technician' => [Technician::class, 'technicians', 'full_name', 'Updated Technician', 'technicians.index'],
]);

test('tracker forms report required fields without creating records', function (string $resource, string $table, array $fields) {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route($resource.'.store'), [])->assertSessionHasErrors($fields);
    $this->assertDatabaseCount($table, 0);
})->with([
    'client' => ['clients', 'clients', ['company_name']],
    'site' => ['sites', 'sites', ['client_id', 'site_name', 'address']],
    'asset' => ['assets', 'assets', ['site_id', 'asset_type_id']],
    'inspection' => ['inspections', 'inspections', ['asset_id', 'technician_id', 'inspection_date', 'result']],
    'certificate' => ['certificates', 'certificates', ['site_id', 'certificate_type_id', 'issue_date', 'expiry_date']],
    'technician' => ['technicians', 'technicians', ['full_name']],
]);

test('invalid foreign keys cannot create orphan records', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('assets.store'), ['site_id' => 9999, 'asset_type_id' => 9999])
        ->assertSessionHasErrors(['site_id', 'asset_type_id']);
    $this->assertDatabaseCount('assets', 0);
});

test('invalid inspection results and dates are rejected', function () {
    $user = User::factory()->create();
    $asset = Asset::factory()->create();
    $technician = Technician::factory()->create();

    $this->actingAs($user)->post(route('inspections.store'), [
        'asset_id' => $asset->id, 'technician_id' => $technician->id,
        'inspection_date' => '2026-02-30', 'result' => 'unknown',
    ])->assertSessionHasErrors(['inspection_date', 'result']);
    $this->assertDatabaseCount('inspections', 0);
});

test('certificate expiry cannot precede its issue date or refer to another sites asset', function () {
    $user = User::factory()->create();
    $site = Site::factory()->create();
    $asset = Asset::factory()->create();

    $this->actingAs($user)->post(route('certificates.store'), [
        'site_id' => $site->id, 'asset_id' => $asset->id, 'certificate_type_id' => CertificateType::factory()->create()->id,
        'issue_date' => '2026-10-01', 'expiry_date' => '2026-09-01',
    ])->assertSessionHasErrors(['asset_id', 'expiry_date']);
    $this->assertDatabaseCount('certificates', 0);
});

test('deactivating a client retains its sites assets inspections and certificates', function () {
    $user = User::factory()->create();
    $client = Client::factory()->create();
    $site = Site::factory()->for($client)->create();
    $asset = Asset::factory()->for($site)->create();
    $inspection = Inspection::factory()->for($asset)->create();
    $certificate = Certificate::factory()->for($site)->create(['asset_id' => $asset->id]);

    $this->actingAs($user)->delete(route('clients.destroy', $client))->assertRedirect();
    foreach ([$client, $site, $asset, $inspection, $certificate] as $record) {
        $this->assertModelExists($record);
    }
    expect($client->refresh()->is_active)->toBeFalse();
    $this->patch(route('clients.restore', $client))->assertRedirect();
    expect($client->refresh()->is_active)->toBeTrue();
});

test('decommissioning an asset retains its inspections and certificate attribution', function () {
    $user = User::factory()->create();
    $asset = Asset::factory()->create();
    $inspection = Inspection::factory()->for($asset)->create();
    $certificate = Certificate::factory()->create(['site_id' => $asset->site_id, 'asset_id' => $asset->id]);

    $this->actingAs($user)->delete(route('assets.destroy', $asset))->assertRedirect();
    $this->assertModelExists($asset);
    $this->assertModelExists($inspection);
    expect($asset->refresh()->status)->toBe('decommissioned');
    expect($certificate->refresh()->asset_id)->toBe($asset->id);
    $this->patch(route('assets.restore', $asset))->assertRedirect();
    expect($asset->refresh()->status)->toBe('active');
});

test('deactivating a technician preserves inspection attribution', function () {
    $user = User::factory()->create();
    $technician = Technician::factory()->create();
    $inspection = Inspection::factory()->for($technician)->create();

    $this->actingAs($user)->delete(route('technicians.destroy', $technician))->assertRedirect();
    expect($technician->refresh()->is_active)->toBeFalse();
    expect($inspection->refresh()->technician_id)->toBe($technician->id);
    $this->patch(route('technicians.restore', $technician))->assertRedirect();
    expect($technician->refresh()->is_active)->toBeTrue();
});

test('asset filters respect the fourteen day boundary and missing dates', function () {
    $this->travelTo(now()->setDate(2026, 9, 29)->startOfDay());
    $user = User::factory()->create();
    $type = AssetType::factory()->create();
    $site = Site::factory()->create();
    $asset = Asset::factory()->for($site)->for($type)->create(['next_service_due' => '2026-10-13']);
    Asset::factory()->for($site)->for($type)->create(['next_service_due' => '2026-10-14']);
    Asset::factory()->for($site)->for($type)->create(['next_service_due' => null]);

    $this->actingAs($user)->get(route('assets.index', ['status' => 'due_soon']))
        ->assertInertia(fn (Assert $page) => $page->component('assets')->has('records.data', 1)->where('records.data.0.id', $asset->id));
    $this->get(route('assets.index', ['status' => 'unknown']))
        ->assertInertia(fn (Assert $page) => $page->has('records.data', 1)->where('records.data.0.next_service_due', null));
});

test('clients can be searched by contact and have stable pagination', function () {
    $user = User::factory()->create();
    Client::factory()->count(11)->create(['company_name' => 'PNG Company']);
    $target = Client::factory()->create(['contact_name' => "O'Brien"]);

    $this->actingAs($user)->get(route('clients.index', ['search' => "O'Brien"]))
        ->assertInertia(fn (Assert $page) => $page->component('clients')->has('records.data', 1)->where('records.data.0.id', $target->id));
    $this->get(route('clients.index', ['page' => 2]))
        ->assertInertia(fn (Assert $page) => $page->has('records.data', 2)->where('records.total', 12)->where('records.current_page', 2));
});

test('inspection result and date filters work together', function () {
    $user = User::factory()->create();
    $asset = Asset::factory()->create(['serial_number' => 'EX-675']);
    $target = Inspection::factory()->for($asset)->create(['result' => 'needs_repair', 'inspection_date' => '2026-09-20']);
    Inspection::factory()->for($asset)->create(['result' => 'pass', 'inspection_date' => '2026-09-20']);

    $this->actingAs($user)->get(route('inspections.index', ['search' => 'EX-675', 'result' => 'needs_repair', 'from' => '2026-09-01', 'to' => '2026-09-30']))
        ->assertInertia(fn (Assert $page) => $page->component('inspections')->has('records.data', 1)->where('records.data.0.id', $target->id));
});

test('certificate issue and expiry date filters work together', function () {
    $user = User::factory()->create();
    $target = Certificate::factory()->create(['issue_date' => '2026-01-01', 'expiry_date' => '2026-10-01']);
    Certificate::factory()->create(['issue_date' => '2025-01-01', 'expiry_date' => '2026-10-01']);

    $this->actingAs($user)->get(route('certificates.index', ['issue_from' => '2026-01-01', 'issue_to' => '2026-12-31', 'from' => '2026-10-01', 'to' => '2026-10-01']))
        ->assertInertia(fn (Assert $page) => $page->component('compliance')->has('records.data', 1)->where('records.data.0.id', $target->id));
});

test('certificate date filters accept end dates without start dates', function () {
    $user = User::factory()->create();
    $target = Certificate::factory()->create(['issue_date' => '2026-01-01', 'expiry_date' => '2026-10-01']);
    Certificate::factory()->create(['issue_date' => '2026-02-01', 'expiry_date' => '2026-10-01']);
    Certificate::factory()->create(['issue_date' => '2026-01-01', 'expiry_date' => '2026-10-03']);

    $this->actingAs($user)->get(route('certificates.index', ['issue_to' => '2026-01-31', 'to' => '2026-10-02']))
        ->assertInertia(fn (Assert $page) => $page->has('records.data', 1)->where('records.data.0.id', $target->id));
});

test('dashboard uses fourteen day asset alerts thirty day certificate alerts and current inspection dates', function () {
    $this->travelTo(now()->setDate(2026, 9, 29)->startOfDay());
    $user = User::factory()->create();
    $client = Client::factory()->create();
    Client::factory()->create(['is_active' => false]);
    $site = Site::factory()->for($client)->create();
    $type = AssetType::factory()->create();
    $overdue = Asset::factory()->for($site)->for($type)->create(['next_service_due' => '2026-09-28']);
    Asset::factory()->for($site)->for($type)->create(['next_service_due' => '2026-10-29']);
    Asset::factory()->for($site)->for($type)->create(['next_service_due' => '2026-12-28']);
    Asset::factory()->for($site)->for($type)->create(['next_service_due' => '2026-12-29']);
    Asset::factory()->for($site)->for($type)->create(['next_service_due' => '2026-09-28', 'status' => 'decommissioned']);
    Certificate::factory()->for($site)->create(['expiry_date' => '2026-09-28']);
    Certificate::factory()->for($site)->create(['expiry_date' => '2026-10-29']);
    Certificate::factory()->for($site)->create(['expiry_date' => '2026-10-30']);
    Inspection::factory()->for($overdue)->create(['result' => 'pass']);
    Inspection::factory()->for($overdue)->create(['result' => 'fail']);
    Inspection::factory()->for($overdue)->create(['result' => 'needs_repair']);

    $this->actingAs($user)->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page->component('dashboard')
        ->where('today', '2026-09-29')->where('summary.asOfDate', '2026-09-29')
        ->where('summary.stats.activeClients', 1)->where('summary.stats.totalAssets', 5)
        ->where('summary.stats.overdueInspections', 1)->where('summary.stats.expiringCerts', 2)
        ->where('attention.total', 1)->where('certificates.total', 2)
        ->has('summary.upcomingDates', 2)
        ->where('summary.upcomingDates.0', '2026-10-29')
        ->where('summary.upcomingDates.1', '2026-12-28')
        ->where('calendar.month', '2026-10')
        ->where('calendar.events.total', 1)
        ->where('summary.complianceRate', ['pass' => 1, 'fail' => 1, 'needs_repair' => 1, 'total' => 3]));
});

test('demo data and private artifacts are seeded repeatedly without duplicates or email delivery', function () {
    Storage::fake('local');
    Mail::fake();
    config()->set('fire-tracker.disk', 'local');
    config()->set('fire-tracker.report.disk', null);

    $this->seed(AssetTypeSeeder::class);
    $this->seed(FireTrackerDemoSeeder::class);
    $this->seed(FireTrackerDemoSeeder::class);

    $this->assertDatabaseCount('asset_types', 9);
    $this->assertDatabaseCount('certificate_types', 5);
    $this->assertDatabaseCount('clients', 3);
    $this->assertDatabaseCount('sites', 4);
    $this->assertDatabaseCount('assets', 13);
    $this->assertDatabaseCount('technicians', 3);
    $this->assertDatabaseCount('inspections', 8);
    $this->assertDatabaseCount('certificates', 6);
    $this->assertDatabaseCount('users', 1);
    $this->assertDatabaseCount('inspection_photos', 4);
    $this->assertDatabaseCount('certificate_documents', 7);
    $this->assertDatabaseCount('managed_uploads', 1);
    $this->assertDatabaseCount('generated_reports', 5);
    $this->assertDatabaseCount('reminder_recipients', 2);
    $this->assertDatabaseCount('reminder_deliveries', 4);

    $this->assertDatabaseHas('clients', [
        'company_name' => 'Moresby Central Bank Tower',
        'email' => 'simon.aitsi@example.test',
        'is_active' => false,
    ]);
    $this->assertDatabaseHas('asset_types', [
        'type_name' => 'Fire Blanket',
        'is_active' => false,
    ]);
    $this->assertDatabaseHas('certificate_types', [
        'name' => 'Emergency Evacuation Plan',
        'is_active' => false,
    ]);
    $this->assertDatabaseHas('technicians', [
        'full_name' => 'Michael Doa',
        'license_number' => null,
        'license_expiry' => null,
        'is_active' => false,
    ]);
    $this->assertDatabaseHas('assets', [
        'serial_number' => null,
        'next_service_due' => null,
    ]);
    $this->assertDatabaseHas('inspections', [
        'technician_id' => null,
        'notes' => null,
        'next_due_date' => null,
    ]);
    $this->assertDatabaseHas('certificates', [
        'asset_id' => null,
        'issued_by' => null,
        'legacy_certificate_type' => 'Annual Fire Safety Statement (legacy)',
    ]);
    $this->assertDatabaseHas('managed_uploads', [
        'status' => 'pending',
        'total_chunks' => 1,
        'next_chunk' => 0,
    ]);
    $this->assertDatabaseHas('fire_notifications', ['is_read' => true]);
    $this->assertDatabaseHas('fire_notifications', ['is_read' => false]);

    $repairAsset = Asset::query()->where('serial_number', 'HR-0093')->firstOrFail();
    $repairInspection = $repairAsset->inspections()
        ->firstOrFail();
    $repairPhotos = $repairInspection->photos;
    expect($repairPhotos)->toHaveCount(3);

    foreach ($repairPhotos as $photo) {
        $bytes = Storage::disk($photo->storage_disk)->get($photo->storage_path);
        $dimensions = getimagesizefromstring($bytes);
        expect($photo->mime_type)->toBe('image/jpeg')
            ->and($photo->file_size)->toBeGreaterThan(100_000)
            ->and($dimensions[0])->toBe(1800)
            ->and($dimensions[1])->toBe(1200);

        $decodedImage = imagecreatefromstring($bytes);
        expect($decodedImage)->not->toBeFalse();
        $pixel = imagecolorsforindex($decodedImage, imagecolorat($decodedImage, 10, 10));
        expect($pixel['red'])->toBeGreaterThan($pixel['green'])
            ->and($pixel['red'])->toBeGreaterThan($pixel['blue']);
        imagedestroy($decodedImage);
    }

    foreach (['inspection_photos', 'certificate_documents'] as $table) {
        foreach (DB::table($table)->get(['storage_disk', 'storage_path', 'mime_type']) as $file) {
            Storage::disk($file->storage_disk)->assertExists($file->storage_path);
            if (str_starts_with($file->mime_type, 'image/')) {
                $bytes = Storage::disk($file->storage_disk)->get($file->storage_path);
                expect(getimagesizefromstring($bytes))->not->toBeFalse();
            }

            if ($file->mime_type === 'application/pdf') {
                $bytes = Storage::disk($file->storage_disk)->get($file->storage_path);
                preg_match_all('/\/Type \/Page\b/', $bytes, $pages);
                expect(count($pages[0]))->toBeGreaterThanOrEqual(3);
            }
        }
    }
    $activeComplianceDocuments = CertificateDocument::query()
        ->whereNull('pending_deletion_at')
        ->get(['certificate_id', 'mime_type']);
    expect($activeComplianceDocuments)->toHaveCount(6);
    expect($activeComplianceDocuments->where('mime_type', 'image/jpeg'))->toHaveCount(1);
    expect($activeComplianceDocuments->where('mime_type', 'application/pdf'))->toHaveCount(5);

    $documentsByCertificate = $activeComplianceDocuments->groupBy('certificate_id');
    expect($documentsByCertificate)->toHaveCount(6);
    foreach ($documentsByCertificate as $documents) {
        expect($documents)->toHaveCount(1);
    }

    $reports = GeneratedReport::query()->get();
    expect($reports->map(fn (GeneratedReport $report): string => $report->report_type->value)->all())
        ->toEqualCanonicalizing([
            'inspection',
            'inspection',
            'service',
            'maintenance',
            'audit',
        ]);

    foreach ($reports as $report) {
        Storage::disk($report->storage_disk)->assertExists($report->storage_path);
        $bytes = Storage::disk($report->storage_disk)->get($report->storage_path);
        expect($bytes)->toStartWith('%PDF-1.4')
            ->and($report->file_size)->toBe(strlen($bytes))
            ->and($report->sha256)->toBe(hash('sha256', $bytes));
    }

    $originalReport = GeneratedReport::query()
        ->where('storage_path', 'fire-tracker/demo/reports/inspection-original.pdf')
        ->firstOrFail();
    $regeneratedReport = GeneratedReport::query()
        ->where('storage_path', 'fire-tracker/demo/reports/inspection-regenerated.pdf')
        ->firstOrFail();
    expect($regeneratedReport->regenerated_from_id)->toBe($originalReport->id)
        ->and($regeneratedReport->include_entered_in_error)->toBeFalse();
    expect(GeneratedReport::query()->whereNull('site_id')->exists())->toBeTrue();

    expect(ReminderDelivery::query()->pluck('status')->all())
        ->toEqualCanonicalizing(['pending', 'failed', 'sent', 'uncertain']);
    expect(FireNotification::query()->exists())->toBeTrue();
    Mail::assertNothingOutgoing();
});

test('the seeder completes a recognized demo dataset already in the database', function () {
    Storage::fake('local');
    config()->set('fire-tracker.disk', 'local');
    config()->set('fire-tracker.report.disk', null);
    $this->seed(AssetTypeSeeder::class);
    $this->seed(CertificateTypeSeeder::class);
    $client = Client::factory()->create([
        'company_name' => 'Harbourside Logistics Ltd',
        'email' => null,
    ]);
    $site = Site::factory()->for($client)->create([
        'site_name' => 'Port Moresby Warehouse',
    ]);
    $type = AssetType::query()
        ->where('type_name', 'Fire Extinguisher - Dry Chemical')
        ->firstOrFail();
    $asset = Asset::factory()->for($site)->for($type)->create([
        'serial_number' => 'EX-2201',
    ]);
    $inspection = Inspection::factory()->for($asset)->create([
        'inspection_date' => '2026-06-20',
        'result' => 'pass',
    ]);

    $this->seed(FireTrackerDemoSeeder::class);

    expect($client->fresh()->email)->toBe('peter.kaupa@example.test');
    $this->assertModelExists($asset);
    $this->assertModelExists($inspection);
    $this->assertDatabaseCount('clients', 3);
    $this->assertDatabaseCount('assets', 13);
    $this->assertDatabaseCount('inspections', 8);
    $this->assertDatabaseCount('generated_reports', 5);
});

test('the seeder leaves unrelated populated tracker data alone', function () {
    Storage::fake('local');
    config()->set('fire-tracker.disk', 'local');
    $client = Client::factory()->create();

    $this->seed(FireTrackerDemoSeeder::class);

    $this->assertModelExists($client);
    $this->assertDatabaseCount('clients', 1);
    $this->assertDatabaseCount('assets', 0);
    $this->assertDatabaseCount('generated_reports', 0);
});
