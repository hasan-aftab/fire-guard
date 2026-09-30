<?php

use App\Models\Asset;
use App\Models\AssetType;
use App\Models\Client;
use App\Models\Site;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

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

test('asset list falls back to the last populated page after a deletion', function () {
    $user = User::factory()->create();
    $site = Site::factory()->create();
    $type = AssetType::factory()->create();
    $assets = Asset::factory()->count(11)->for($site)->for($type)->create(['serial_number' => 'PNG-ASSET']);
    $filteredUrl = route('assets.index', ['search' => 'PNG-ASSET']);

    $this->actingAs($user)->get(route('assets.index', ['search' => 'PNG-ASSET', 'page' => 2]))
        ->assertInertia(fn (Assert $page) => $page->component('assets')
            ->has('records.data', 1)
            ->where('records.current_page', 2)
            ->missing('filters.page'));
    $this->from(route('assets.index', ['search' => 'PNG-ASSET', 'page' => 2]))
        ->delete(route('assets.destroy', $assets->last()))
        ->assertRedirect();

    $this->get(route('assets.index', ['search' => 'PNG-ASSET', 'page' => 2]))
        ->assertRedirect($filteredUrl);
    $this->get($filteredUrl)
        ->assertInertia(fn (Assert $page) => $page->component('assets')
            ->has('records.data', 10)
            ->where('records.current_page', 1)
            ->where('records.total', 10));
});

test('assets page exposes missing setup choices so creation can be disabled', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('assets.index'))
        ->assertInertia(fn (Assert $page) => $page->component('assets')
            ->has('sites', 0)
            ->has('assetTypes', 0)
            ->has('records.data', 0));
});
