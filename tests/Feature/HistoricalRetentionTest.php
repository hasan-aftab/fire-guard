<?php

use App\Models\Asset;
use App\Models\Certificate;
use App\Models\Client;
use App\Models\Inspection;
use App\Models\Site;
use App\Models\Technician;
use App\Models\User;
use Illuminate\Database\QueryException;
use Inertia\Testing\AssertableInertia as Assert;

test('archived relationships remain accessible but do not create active work', function () {
    $this->travelTo(now()->setDate(2026, 9, 30)->startOfDay());
    $user = User::factory()->create();
    $activeSite = Site::factory()->create();
    $inactiveSite = Site::factory()->create(['is_active' => false]);
    $inactiveClientSite = Site::factory()->for(Client::factory()->create(['is_active' => false]))->create();
    $activeAsset = Asset::factory()->for($activeSite)->create(['next_service_due' => '2026-09-29']);
    $inactiveSiteAsset = Asset::factory()->for($inactiveSite)->create(['next_service_due' => '2026-09-29']);
    $inactiveClientAsset = Asset::factory()->for($inactiveClientSite)->create(['next_service_due' => '2026-09-29']);
    $retiredAsset = Asset::factory()->for($activeSite)->create(['status' => 'decommissioned', 'next_service_due' => '2026-09-29']);

    foreach ([$activeAsset, $inactiveSiteAsset, $inactiveClientAsset, $retiredAsset] as $asset) {
        Inspection::factory()->for($asset)->create(['result' => 'fail', 'next_due_date' => '2026-10-01']);
        Certificate::factory()->for($asset->site)->create(['asset_id' => $asset->id, 'issue_date' => '2026-01-01', 'expiry_date' => '2026-09-29']);
    }

    $this->actingAs($user)->get(route('assets.index'))
        ->assertInertia(fn (Assert $page) => $page->where('records.total', 4));
    $this->get(route('assets.index', ['status' => 'overdue']))
        ->assertInertia(fn (Assert $page) => $page->where('records.total', 1)->where('records.data.0.id', $activeAsset->id));
    $this->get(route('assets.index', ['equipment_status' => 'decommissioned']))
        ->assertInertia(fn (Assert $page) => $page->where('records.total', 1)->where('records.data.0.id', $retiredAsset->id));
    $this->get(route('inspections.index'))->assertInertia(fn (Assert $page) => $page->where('records.total', 4));
    $this->get(route('certificates.index'))->assertInertia(fn (Assert $page) => $page->where('records.total', 4));
    $this->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
        ->where('summary.stats.overdueInspections', 1)
        ->where('summary.stats.expiringCerts', 1)
        ->where('attention.total', 1)
        ->where('certificates.total', 1)
        ->where('calendar.events.total', 0));

    $this->post(route('notifications.generate'))->assertRedirect();
    $this->assertDatabaseCount('fire_notifications', 3);
});

test('inactive relationships cannot be used for new operational records but existing history can be corrected', function () {
    $user = User::factory()->create();
    $site = Site::factory()->create(['is_active' => false]);
    $asset = Asset::factory()->for($site)->create(['status' => 'decommissioned']);
    $technician = Technician::factory()->create(['is_active' => false]);
    $inspection = Inspection::factory()->for($asset)->for($technician)->create();

    $this->actingAs($user)->post(route('assets.store'), ['site_id' => $site->id, 'asset_type_id' => $asset->asset_type_id])
        ->assertSessionHasErrors(['site_id']);
    $this->post(route('inspections.store'), [
        'asset_id' => $asset->id,
        'technician_id' => $technician->id,
        'inspection_date' => '2026-09-30',
        'result' => 'pass',
    ])->assertSessionHasErrors(['asset_id', 'technician_id']);
    $this->post(route('certificates.store'), [
        'site_id' => $site->id,
        'certificate_type' => 'Historical certificate',
        'issue_date' => '2026-01-01',
        'expiry_date' => '2026-09-30',
    ])->assertSessionHasErrors(['site_id']);
    $this->post(route('sites.store'), [
        'client_id' => Client::factory()->create(['is_active' => false])->id,
        'site_name' => 'New site',
        'address' => 'Old client address',
    ])->assertSessionHasErrors(['client_id']);

    $this->put(route('inspections.update', $inspection), [
        'asset_id' => $asset->id,
        'technician_id' => $technician->id,
        'inspection_date' => '2026-09-30',
        'result' => 'pass',
    ])->assertSessionHasNoErrors();
    expect($inspection->refresh()->result)->toBe('pass');
});

test('foreign keys prevent direct parent deletion from erasing retained safety records', function () {
    $client = Client::factory()->create();
    $site = Site::factory()->for($client)->create();
    $asset = Asset::factory()->for($site)->create();
    $technician = Technician::factory()->create();
    $inspection = Inspection::factory()->for($asset)->for($technician)->create();
    $certificate = Certificate::factory()->for($site)->create(['asset_id' => $asset->id]);

    foreach ([$client, $site, $asset, $technician] as $parent) {
        expect(fn () => $parent->delete())->toThrow(QueryException::class);
    }
    $certificateOnlyAsset = Asset::factory()->for($site)->create();
    $linkedCertificate = Certificate::factory()->for($site)->create(['asset_id' => $certificateOnlyAsset->id]);
    expect(fn () => $certificateOnlyAsset->delete())->toThrow(QueryException::class);
    foreach ([$client, $site, $asset, $technician, $inspection, $certificate] as $record) {
        $this->assertModelExists($record);
    }
    $this->assertModelExists($linkedCertificate);
    expect($linkedCertificate->refresh()->asset_id)->toBe($certificateOnlyAsset->id);
});

test('deactivating and reactivating a client synchronizes active alerts without losing records', function () {
    $this->travelTo(now()->setDate(2026, 9, 30)->startOfDay());
    $user = User::factory()->create();
    $client = Client::factory()->create();
    $site = Site::factory()->for($client)->create();
    $asset = Asset::factory()->for($site)->create(['next_service_due' => '2026-09-29']);
    $inspection = Inspection::factory()->for($asset)->create(['result' => 'fail']);
    $certificate = Certificate::factory()->for($site)->create(['asset_id' => $asset->id, 'issue_date' => '2026-01-01', 'expiry_date' => '2026-09-29']);

    $this->actingAs($user)->post(route('notifications.generate'))->assertRedirect();
    $this->assertDatabaseCount('fire_notifications', 3);

    $this->delete(route('clients.destroy', $client))->assertRedirect();
    $this->assertDatabaseCount('fire_notifications', 0);
    foreach ([$client, $site, $asset, $inspection, $certificate] as $record) {
        $this->assertModelExists($record);
    }

    $this->patch(route('clients.restore', $client))->assertRedirect();
    $this->assertDatabaseCount('fire_notifications', 3);
});

test('unlinked clients sites assets and technicians can be permanently deleted', function () {
    $this->actingAs(User::factory()->create());

    $client = Client::factory()->create();
    $site = Site::factory()->create();
    $asset = Asset::factory()->create();
    $technician = Technician::factory()->create();

    foreach ([
        ['clients.purge', $client],
        ['sites.purge', $site],
        ['assets.purge', $asset],
        ['technicians.purge', $technician],
    ] as [$routeName, $record]) {
        $this->delete(route($routeName, $record))->assertRedirect();
        $this->assertModelMissing($record);
    }
});

test('permanent deletion is blocked for every kind of linked history', function () {
    $this->actingAs(User::factory()->create());
    $client = Client::factory()->create();
    $site = Site::factory()->for($client)->create();
    $asset = Asset::factory()->for($site)->create();
    $technician = Technician::factory()->create();
    $inspection = Inspection::factory()->for($asset)->for($technician)->create();
    $certificate = Certificate::factory()->for($site)->create(['asset_id' => $asset->id]);

    foreach ([
        ['clients.purge', $client],
        ['sites.purge', $site],
        ['assets.purge', $asset],
        ['technicians.purge', $technician],
    ] as [$routeName, $record]) {
        $this->delete(route($routeName, $record))->assertConflict();
        $this->assertModelExists($record);
    }

    $this->assertModelExists($inspection);
    $this->assertModelExists($certificate);

    $certificateOnlySite = Site::factory()->create();
    $certificateOnlyAsset = Asset::factory()->for($certificateOnlySite)->create();
    $this->delete(route('assets.purge', $certificateOnlyAsset))->assertRedirect();
    $certificateOnlyAsset = Asset::factory()->for($certificateOnlySite)->create();
    Certificate::factory()->for($certificateOnlySite)->create(['asset_id' => $certificateOnlyAsset->id]);
    $this->delete(route('assets.purge', $certificateOnlyAsset))->assertConflict();

    $siteWithCertificate = Site::factory()->create();
    Certificate::factory()->for($siteWithCertificate)->create();
    $this->delete(route('sites.purge', $siteWithCertificate))->assertConflict();
});

test('entered in error records leave normal lists and alerts and can be restored', function () {
    $this->travelTo(now()->setDate(2026, 9, 30)->startOfDay());
    $this->actingAs(User::factory()->create());
    $site = Site::factory()->create();
    $asset = Asset::factory()->for($site)->create(['next_service_due' => '2026-09-29']);
    $inspection = Inspection::factory()->for($asset)->create(['result' => 'fail']);
    $certificate = Certificate::factory()->for($site)->create(['asset_id' => $asset->id, 'issue_date' => '2026-01-01', 'expiry_date' => '2026-09-29']);

    $this->post(route('notifications.generate'))->assertRedirect();
    $this->assertDatabaseCount('fire_notifications', 3);

    $this->patch(route('inspections.mark-error', $inspection))->assertRedirect();
    $this->patch(route('certificates.mark-error', $certificate))->assertRedirect();
    $this->patch(route('assets.mark-error', $asset))->assertRedirect();
    $this->assertDatabaseCount('fire_notifications', 0);
    $this->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
        ->where('summary.stats.totalAssets', 0)
        ->where('summary.complianceRate.total', 0));
    $this->get(route('inspections.index'))->assertInertia(fn (Assert $page) => $page->where('records.total', 0));
    $this->get(route('certificates.index'))->assertInertia(fn (Assert $page) => $page->where('records.total', 0));
    $this->get(route('assets.index'))->assertInertia(fn (Assert $page) => $page->where('records.total', 0));
    $this->get(route('inspections.index', ['record_state' => 'entered_in_error']))->assertInertia(fn (Assert $page) => $page->where('records.data.0.id', $inspection->id));
    $this->get(route('certificates.index', ['record_state' => 'entered_in_error']))->assertInertia(fn (Assert $page) => $page->where('records.data.0.id', $certificate->id));
    $this->get(route('assets.index', ['record_state' => 'entered_in_error']))->assertInertia(fn (Assert $page) => $page->where('records.data.0.id', $asset->id)->has('records.data.0.entered_in_error_at'));

    $this->patch(route('assets.restore-error', $asset))->assertRedirect();
    $this->patch(route('inspections.restore-error', $inspection))->assertRedirect();
    $this->patch(route('certificates.restore-error', $certificate))->assertRedirect();
    $this->assertDatabaseCount('fire_notifications', 3);
    foreach ([$asset, $inspection, $certificate] as $record) {
        expect($record->refresh()->entered_in_error_at)->toBeNull();
    }
});

test('completed inspections and certificates have no permanent delete route', function () {
    $this->actingAs(User::factory()->create());
    $inspection = Inspection::factory()->create();
    $certificate = Certificate::factory()->create();

    $this->delete('/inspections/'.$inspection->id)->assertMethodNotAllowed();
    $this->delete('/certificates/'.$certificate->id)->assertMethodNotAllowed();
    $this->assertModelExists($inspection);
    $this->assertModelExists($certificate);
});

test('entered in error clients sites and technicians stay recoverable and cannot be used for new work', function () {
    $this->actingAs(User::factory()->create());
    $client = Client::factory()->create();
    $site = Site::factory()->for($client)->create();
    $asset = Asset::factory()->for($site)->create();
    $technician = Technician::factory()->create();

    $this->patch(route('sites.mark-error', $site))->assertRedirect();
    $this->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page->where('summary.stats.totalAssets', 0));
    $this->get(route('clients.index', ['site_client' => $client->id]))
        ->assertInertia(fn (Assert $page) => $page->where('selectedSites.total', 0)->where('records.data.0.current_sites_count', 0));
    $this->get(route('clients.index', ['site_client' => $client->id, 'site_state' => 'entered_in_error']))
        ->assertInertia(fn (Assert $page) => $page->where('selectedSites.data.0.id', $site->id));
    $this->post(route('assets.store'), ['site_id' => $site->id, 'asset_type_id' => $asset->asset_type_id])
        ->assertSessionHasErrors(['site_id']);
    $this->patch(route('sites.restore-error', $site))->assertRedirect();
    $this->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page->where('summary.stats.totalAssets', 1));

    $this->patch(route('clients.mark-error', $client))->assertRedirect();
    $this->get(route('clients.index'))->assertInertia(fn (Assert $page) => $page->where('records.total', 0));
    $this->get(route('clients.index', ['record_state' => 'entered_in_error']))
        ->assertInertia(fn (Assert $page) => $page->where('records.data.0.id', $client->id));
    $this->post(route('sites.store'), ['client_id' => $client->id, 'site_name' => 'New site', 'address' => 'New address'])
        ->assertSessionHasErrors(['client_id']);
    $this->patch(route('clients.restore-error', $client))->assertRedirect();

    $this->patch(route('technicians.mark-error', $technician))->assertRedirect();
    $this->get(route('technicians.index'))->assertInertia(fn (Assert $page) => $page->where('records.total', 0));
    $this->get(route('technicians.index', ['record_state' => 'entered_in_error']))
        ->assertInertia(fn (Assert $page) => $page->where('records.data.0.id', $technician->id));
    $this->post(route('inspections.store'), [
        'asset_id' => $asset->id,
        'technician_id' => $technician->id,
        'inspection_date' => '2026-09-30',
        'result' => 'pass',
    ])->assertSessionHasErrors(['technician_id']);
    $this->patch(route('technicians.restore-error', $technician))->assertRedirect();
    foreach ([$client, $site, $technician] as $record) {
        expect($record->refresh()->entered_in_error_at)->toBeNull();
    }
});
