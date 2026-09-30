<?php

use App\Models\Asset;
use App\Models\Certificate;
use App\Models\Client;
use App\Models\Inspection;
use App\Models\Site;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('client listing includes ordered sites and accurate counts', function () {
    $user = User::factory()->create();
    $client = Client::factory()->create(['company_name' => 'Alpha Client']);
    $laterSite = Site::factory()->for($client)->create(['site_name' => 'Warehouse']);
    $firstSite = Site::factory()->for($client)->create(['site_name' => 'Head Office']);
    Site::factory()->create();

    $this->actingAs($user)->get(route('clients.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('clients')
            ->where('records.data.0.id', $client->id)
            ->where('records.data.0.sites_count', 2)
            ->has('records.data.0.sites', 2)
            ->where('records.data.0.sites.0.id', $firstSite->id)
            ->where('records.data.0.sites.1.id', $laterSite->id));
});

test('client listing redirects an out of range page to the last valid page while preserving search', function () {
    $user = User::factory()->create();
    Client::factory()->create(['company_name' => 'Alpha Client']);

    $this->actingAs($user)->get(route('clients.index', ['search' => 'Alpha', 'page' => 2]))
        ->assertRedirect(route('clients.index', ['search' => 'Alpha']));
});

test('client pagination does not appear as an active search filter', function () {
    $user = User::factory()->create();
    Client::factory()->count(11)->create();

    $this->actingAs($user)->get(route('clients.index', ['page' => 2]))
        ->assertInertia(fn (Assert $page) => $page
            ->component('clients')
            ->where('records.current_page', 2)
            ->where('filters', []));
});

test('site updates preserve client ownership and related records', function () {
    $user = User::factory()->create();
    $client = Client::factory()->create();
    $otherClient = Client::factory()->create();
    $site = Site::factory()->for($client)->create();
    $asset = Asset::factory()->for($site)->create();

    $this->actingAs($user)->put(route('sites.update', $site), [
        'client_id' => $otherClient->id,
        'site_name' => 'Moved Site',
        'address' => 'New address',
    ])->assertSessionHasErrors(['client_id']);

    $this->assertDatabaseHas('sites', [
        'id' => $site->id,
        'client_id' => $client->id,
        'site_name' => $site->site_name,
    ]);
    $this->assertModelExists($asset);

    $this->put(route('sites.update', $site), [
        'client_id' => $client->id,
        'site_name' => 'Updated Site',
        'address' => 'New address',
        'suburb_region' => 'Port Moresby',
        'access_notes' => 'Use the side gate',
        'site_contact' => 'Site Manager',
        'site_phone' => '+675 7000 0000',
    ])->assertRedirect();

    $this->assertDatabaseHas('sites', [
        'id' => $site->id,
        'client_id' => $client->id,
        'site_name' => 'Updated Site',
        'address' => 'New address',
        'suburb_region' => 'Port Moresby',
        'access_notes' => 'Use the side gate',
        'site_contact' => 'Site Manager',
        'site_phone' => '+675 7000 0000',
    ]);
    $this->assertModelExists($asset);
});

test('deleting a site removes its dependents but preserves the client and other sites', function () {
    $user = User::factory()->create();
    $client = Client::factory()->create();
    $site = Site::factory()->for($client)->create();
    $otherSite = Site::factory()->for($client)->create();
    $asset = Asset::factory()->for($site)->create();
    $inspection = Inspection::factory()->for($asset)->create();
    $certificate = Certificate::factory()->for($site)->create(['asset_id' => $asset->id]);

    $this->actingAs($user)->delete(route('sites.destroy', $site))->assertRedirect();

    foreach ([$site, $asset, $inspection, $certificate] as $record) {
        $this->assertModelMissing($record);
    }
    $this->assertModelExists($client);
    $this->assertModelExists($otherSite);
});
