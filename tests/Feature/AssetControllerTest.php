<?php

use App\Models\Asset;
use App\Models\AssetType;
use App\Models\Client;
use App\Models\Site;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('asset status and operational filters follow the inclusive fourteen day inspection boundary', function () {
    $this->travelTo(now()->setDate(2026, 10, 1)->startOfDay());
    $site = Site::factory()->create();
    $type = AssetType::factory()->create();
    $dates = [
        'overdue' => '2026-09-30',
        'today' => '2026-10-01',
        'tomorrow' => '2026-10-02',
        'boundary' => '2026-10-15',
        'current' => '2026-10-16',
        'unknown' => null,
    ];
    $assets = [];
    foreach ($dates as $key => $date) {
        $assets[$key] = Asset::factory()->for($site)->for($type)->create(['next_service_due' => $date]);
    }
    $retiredDue = Asset::factory()->for($site)->for($type)->create(['next_service_due' => '2026-10-15', 'status' => 'decommissioned']);
    $retiredOverdue = Asset::factory()->for($site)->for($type)->create(['next_service_due' => '2026-09-30', 'status' => 'decommissioned']);

    $this->actingAs(User::factory()->create())->get(route('assets.index'))
        ->assertInertia(fn (Assert $page) => $page->component('assets')
            ->where('records.data.0.service_status', 'overdue')
            ->where('records.data.1.service_status', 'due_soon')
            ->where('records.data.2.service_status', 'due_soon')
            ->where('records.data.3.service_status', 'due_soon')
            ->where('records.data.4.service_status', 'ok')
            ->where('records.data.5.service_status', 'unknown'));
    $this->get(route('assets.index', ['status' => 'due_soon']))
        ->assertInertia(fn (Assert $page) => $page->has('records.data', 3)
            ->where('records.data.2.id', $assets['boundary']->id));
    $this->get(route('assets.index', ['status' => 'overdue']))
        ->assertInertia(fn (Assert $page) => $page->has('records.data', 1)
            ->where('records.data.0.id', $assets['overdue']->id));
    $this->get(route('assets.index', ['status' => 'ok']))
        ->assertInertia(fn (Assert $page) => $page->has('records.data', 1)
            ->where('records.data.0.id', $assets['current']->id));
    $this->get(route('assets.index', ['status' => 'unknown']))
        ->assertInertia(fn (Assert $page) => $page->has('records.data', 1)
            ->where('records.data.0.id', $assets['unknown']->id));
    expect($retiredDue->refresh()->next_service_due->toDateString())->toBe('2026-10-15');
    expect($retiredOverdue->refresh()->next_service_due->toDateString())->toBe('2026-09-30');
});

test('asset search treats wildcard characters literally and combines with status and dates', function () {
    $this->travelTo(now()->setDate(2026, 9, 30)->startOfDay());
    $user = User::factory()->create();
    $client = Client::factory()->create(['company_name' => 'A!_% Fire']);
    $site = Site::factory()->for($client)->create();
    $type = AssetType::factory()->create();
    $target = Asset::factory()->for($site)->for($type)->create(['next_service_due' => '2026-10-14']);
    Asset::factory()->for($site)->for($type)->create(['next_service_due' => '2026-10-15']);
    Asset::factory()->create(['serial_number' => 'Axy% Fire', 'next_service_due' => '2026-10-14']);

    $this->actingAs($user)->get(route('assets.index', [
        'search' => 'A!_%', 'status' => 'due_soon', 'from' => '2026-10-14', 'to' => '2026-10-14',
    ]))->assertInertia(fn (Assert $page) => $page->component('assets')
        ->has('records.data', 1)
        ->where('records.data.0.id', $target->id)
        ->where('records.total', 1));
});

test('asset date filters accept a single endpoint and exclude undated records', function () {
    $user = User::factory()->create();
    $target = Asset::factory()->create(['next_service_due' => '2026-10-01']);
    Asset::factory()->create(['next_service_due' => '2026-10-03']);
    Asset::factory()->create(['next_service_due' => null]);

    $this->actingAs($user)->get(route('assets.index', ['to' => '2026-10-02']))
        ->assertInertia(fn (Assert $page) => $page->component('assets')
            ->has('records.data', 1)
            ->where('records.data.0.id', $target->id)
            ->where('records.total', 1));
});

test('asset list retains a decommissioned record on its original page', function () {
    $user = User::factory()->create();
    $site = Site::factory()->create();
    $type = AssetType::factory()->create();
    $assets = Asset::factory()->count(11)->for($site)->for($type)->create(['serial_number' => 'FX-ASSET']);
    $filteredUrl = route('assets.index', ['search' => 'FX-ASSET']);

    $this->actingAs($user)->get(route('assets.index', ['search' => 'FX-ASSET', 'page' => 2]))
        ->assertInertia(fn (Assert $page) => $page->component('assets')
            ->has('records.data', 1)
            ->where('records.current_page', 2)
            ->missing('filters.page'));
    $this->from(route('assets.index', ['search' => 'FX-ASSET', 'page' => 2]))
        ->delete(route('assets.destroy', $assets->last()))
        ->assertRedirect();

    $this->get(route('assets.index', ['search' => 'FX-ASSET', 'page' => 2]))
        ->assertInertia(fn (Assert $page) => $page->component('assets')
            ->has('records.data', 1)
            ->where('records.data.0.status', 'decommissioned')
            ->where('records.current_page', 2)
            ->where('records.total', 11));
});

test('assets page exposes missing setup choices so creation can be disabled', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('assets.index'))
        ->assertInertia(fn (Assert $page) => $page->component('assets')
            ->has('sites', 0)
            ->has('assetTypes', 0)
            ->has('records.data', 0));
});

test('assets can be filtered by site type and equipment status and searched by type', function () {
    $user = User::factory()->create();
    $site = Site::factory()->create();
    $otherSite = Site::factory()->create();
    $type = AssetType::factory()->create(['type_name' => 'Hydrant!_%']);
    $otherType = AssetType::factory()->create(['type_name' => 'Extinguisher']);
    $target = Asset::factory()->for($site)->for($type)->create(['status' => 'needs_replacement']);
    Asset::factory()->for($site)->for($otherType)->create(['status' => 'needs_replacement']);
    Asset::factory()->for($otherSite)->for($type)->create(['status' => 'active']);

    $this->actingAs($user)->get(route('assets.index', [
        'search' => 'Hydrant!_%',
        'site_id' => $site->id,
        'asset_type_id' => $type->id,
        'equipment_status' => 'needs_replacement',
    ]))->assertInertia(fn (Assert $page) => $page->component('assets')
        ->has('records.data', 1)
        ->where('records.data.0.id', $target->id)
        ->where('records.data.0.asset_type.type_name', 'Hydrant!_%')
        ->where('records.data.0.site.client.company_name', $site->client->company_name)
        ->where('filters.asset_type_id', (string) $type->id)
        ->missing('filters.page'));
});

test('assets sort due dates with undated assets last and stable id ties', function () {
    $user = User::factory()->create();
    $site = Site::factory()->create();
    $type = AssetType::factory()->create();
    $undated = Asset::factory()->for($site)->for($type)->create(['next_service_due' => null]);
    $late = Asset::factory()->for($site)->for($type)->create(['next_service_due' => '2026-10-20']);
    $early = Asset::factory()->for($site)->for($type)->create(['next_service_due' => '2026-10-01']);
    $earlyTie = Asset::factory()->for($site)->for($type)->create(['next_service_due' => '2026-10-01']);

    $this->actingAs($user)->get(route('assets.index', ['sort' => 'due_soonest']))
        ->assertInertia(fn (Assert $page) => $page->component('assets')
            ->where('records.data.0.id', $early->id)
            ->where('records.data.1.id', $earlyTie->id)
            ->where('records.data.2.id', $late->id)
            ->where('records.data.3.id', $undated->id));

    $this->get(route('assets.index', ['sort' => 'due_latest']))
        ->assertInertia(fn (Assert $page) => $page->component('assets')
            ->where('records.data.0.id', $late->id)
            ->where('records.data.3.id', $undated->id));
});

test('assets reject invalid relation state and sort filters', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('assets.index', [
        'site_id' => 9999,
        'asset_type_id' => 9999,
        'equipment_status' => 'broken',
        'sort' => 'status;drop',
    ]))->assertSessionHasErrors(['site_id', 'asset_type_id', 'equipment_status', 'sort']);
});

test('asset dates must follow installation and last service dates', function () {
    $user = User::factory()->create();
    $site = Site::factory()->create();
    $type = AssetType::factory()->create();

    $this->actingAs($user)->post(route('assets.store'), [
        'site_id' => $site->id,
        'asset_type_id' => $type->id,
        'install_date' => '2026-09-20',
        'last_service_date' => '2026-09-19',
        'next_service_due' => '2026-09-18',
    ])->assertSessionHasErrors(['last_service_date', 'next_service_due']);
    $this->assertDatabaseCount('assets', 0);

    $this->post(route('assets.store'), [
        'site_id' => $site->id,
        'asset_type_id' => $type->id,
        'install_date' => '2026-09-20',
        'last_service_date' => '2026-09-21',
        'next_service_due' => '2026-09-22',
    ])->assertRedirect();
    $this->assertDatabaseHas('assets', ['site_id' => $site->id, 'next_service_due' => '2026-09-22']);
});
