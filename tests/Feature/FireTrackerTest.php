<?php

use App\Models\Asset;
use App\Models\AssetType;
use App\Models\Certificate;
use App\Models\Client;
use App\Models\Inspection;
use App\Models\Site;
use App\Models\Technician;
use App\Models\User;
use Database\Seeders\AssetTypeSeeder;
use Database\Seeders\FireTrackerDemoSeeder;
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
    'certificate' => [Certificate::class, 'certificates', 'certificate_type', 'Updated Fire Safety Certificate', 'certificates.index'],
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
    'certificate' => ['certificates', 'certificates', ['site_id', 'certificate_type', 'issue_date', 'expiry_date']],
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
        'site_id' => $site->id, 'asset_id' => $asset->id, 'certificate_type' => 'Fire Safety',
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

test('dashboard uses active assets thirty day alerts ninety day calendar and real inspection results', function () {
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
        ->where('attention.total', 2)->where('certificates.total', 2)
        ->has('summary.upcomingDates', 2)
        ->where('summary.upcomingDates.0', '2026-10-29')
        ->where('summary.upcomingDates.1', '2026-12-28')
        ->where('calendar.month', '2026-10')
        ->where('calendar.events.total', 1)
        ->where('summary.complianceRate', ['pass' => 1, 'fail' => 1, 'needs_repair' => 1, 'total' => 3]));
});

test('equipment types and demo data can be seeded repeatedly without duplicating records', function () {
    $this->seed(AssetTypeSeeder::class);
    $this->seed(FireTrackerDemoSeeder::class);
    $this->seed(FireTrackerDemoSeeder::class);

    $this->assertDatabaseCount('asset_types', 9);
    $this->assertDatabaseCount('clients', 3);
    $this->assertDatabaseCount('sites', 4);
    $this->assertDatabaseCount('assets', 12);
    $this->assertDatabaseCount('inspections', 6);
    $this->assertDatabaseCount('certificates', 6);
});
