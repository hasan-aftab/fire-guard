<?php

use App\Models\Asset;
use App\Models\Certificate;
use App\Models\Client;
use App\Models\Inspection;
use App\Models\Site;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('client listing bounds site previews and paginates the selected client sites', function () {
    $user = User::factory()->create();
    $client = Client::factory()->create(['company_name' => 'Alpha Client']);
    $laterSite = Site::factory()->for($client)->create(['site_name' => 'Warehouse']);
    $firstSite = Site::factory()->for($client)->create(['site_name' => 'Head Office']);
    Site::factory()->for($client)->count(9)->create(['site_name' => 'Middle Site']);
    $otherClient = Client::factory()->create(['company_name' => 'Zulu Client']);
    $otherSite = Site::factory()->for($otherClient)->create();

    $this->actingAs($user)->get(route('clients.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('clients')
            ->where('records.data.0.id', $client->id)
            ->where('records.data.0.sites_count', 11)
            ->has('records.data.0.sites', 5)
            ->where('records.data.0.sites.0.id', $firstSite->id)
            ->missing('records.data.0.sites.0.access_notes')
            ->where('records.data.1.sites_count', 1)
            ->where('records.data.1.sites.0.id', $otherSite->id)
            ->where('selectedClient', null)
            ->where('selectedSites', null));

    $this->actingAs($user)->get(route('clients.index', ['site_client' => $client->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->component('clients')
            ->where('selectedClient.id', $client->id)
            ->where('selectedSites.total', 11)
            ->has('selectedSites.data', 10)
            ->where('selectedSites.data.0.id', $firstSite->id));

    $this->actingAs($user)->get(route('clients.index', ['site_client' => $client->id, 'site_page' => 2]))
        ->assertInertia(fn (Assert $page) => $page
            ->component('clients')
            ->where('selectedSites.current_page', 2)
            ->has('selectedSites.data', 1)
            ->where('selectedSites.data.0.id', $laterSite->id));
});

test('client filters search site names and addresses literally and combine with status and site presence', function () {
    $user = User::factory()->create();
    $matching = Client::factory()->create(['company_name' => 'Alpha', 'is_active' => true]);
    $inactive = Client::factory()->create(['company_name' => 'Beta', 'is_active' => false]);
    Client::factory()->create(['company_name' => 'No Sites', 'is_active' => true]);
    Site::factory()->for($matching)->create(['site_name' => 'Depot 100%', 'address' => 'Harbour Road']);
    Site::factory()->for($inactive)->create(['site_name' => 'Other', 'address' => 'Harbour Road']);

    $this->actingAs($user)->get(route('clients.index', ['search' => '100%', 'active' => 'active', 'site_presence' => 'with_sites']))
        ->assertInertia(fn (Assert $page) => $page
            ->component('clients')
            ->has('records.data', 1)
            ->where('records.data.0.id', $matching->id)
            ->where('filters.active', 'active')
            ->where('filters.site_presence', 'with_sites'));

    $this->actingAs($user)->get(route('clients.index', ['search' => 'Harbour', 'active' => 'inactive']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('records.data', 1)
            ->where('records.data.0.id', $inactive->id));

    $this->actingAs($user)->get(route('clients.index', ['site_presence' => 'without_sites']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('records.data', 1)
            ->where('records.data.0.company_name', 'No Sites'));
});

test('client sorting uses a stable name or site-count order', function () {
    $user = User::factory()->create();
    $alpha = Client::factory()->create(['company_name' => 'Alpha']);
    $beta = Client::factory()->create(['company_name' => 'Beta']);
    Site::factory()->for($beta)->count(2)->create();

    $this->actingAs($user)->get(route('clients.index', ['sort' => 'name_desc']))->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('records.data.0.id', $beta->id)
            ->where('records.data.1.id', $alpha->id));

    $this->actingAs($user)->get(route('clients.index', ['sort' => 'most_sites']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('records.data.0.id', $beta->id));
});

test('client filters reject invalid options and selected site pages return to the last populated page', function () {
    $user = User::factory()->create();
    $client = Client::factory()->create();
    Site::factory()->for($client)->count(11)->create();

    $this->actingAs($user)->get(route('clients.index', ['active' => 'hidden']))
        ->assertSessionHasErrors(['active']);

    $this->actingAs($user)->get(route('clients.index', [
        'site_client' => $client->id,
        'site_page' => 3,
        'active' => 'active',
    ]))->assertRedirect(route('clients.index', [
        'active' => 'active',
        'site_client' => $client->id,
        'site_page' => 2,
    ]));
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
    ])->assertSessionHasErrors([
        'client_id' => 'This site belongs to another client and cannot be moved here.',
    ]);

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

test('client forms reject malformed contact details and retain the existing record', function () {
    $user = User::factory()->create();
    $client = Client::factory()->create(['company_name' => 'Original Client']);

    $this->actingAs($user)->put(route('clients.update', $client), [
        'company_name' => 'Updated Client',
        'email' => 'not-an-email',
        'phone' => 'call the manager',
    ])->assertSessionHasErrors([
        'email',
        'phone' => 'Enter a phone number with 7 to 20 digits using common separators or an extension.',
    ]);

    $this->assertDatabaseHas('clients', ['id' => $client->id, 'company_name' => 'Original Client']);

    $this->put(route('clients.update', $client), [
        'company_name' => 'Updated Client',
        'email' => 'manager@example.com',
        'phone' => '+675 7000 0000',
    ])->assertSessionHasNoErrors();

    $this->assertDatabaseHas('clients', [
        'id' => $client->id,
        'company_name' => 'Updated Client',
        'phone' => '+675 7000 0000',
    ]);
});

test('site forms reject malformed phone numbers and blank required fields', function () {
    $user = User::factory()->create();
    $client = Client::factory()->create();

    $this->actingAs($user)->post(route('sites.store'), [
        'client_id' => $client->id,
        'site_name' => ' ',
        'address' => ' ',
        'site_phone' => '123',
    ])->assertSessionHasErrors(['site_name', 'address', 'site_phone']);

    $this->assertDatabaseCount('sites', 0);

    $this->post(route('sites.store'), [
        'client_id' => $client->id,
        'site_name' => 'Warehouse',
        'address' => 'Port Moresby',
        'site_phone' => '(675) 7000 0000 ext. 12',
    ])->assertSessionHasNoErrors();

    $this->assertDatabaseHas('sites', [
        'client_id' => $client->id,
        'site_name' => 'Warehouse',
        'site_phone' => '(675) 7000 0000 ext. 12',
    ]);
});

test('client field limits and formats reject invalid input', function (string $field, mixed $value) {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('clients.store'), [
        'company_name' => 'Valid Client',
        $field => $value,
    ])->assertSessionHasErrors([$field]);

    $this->assertDatabaseCount('clients', 0);
})->with([
    'blank company' => ['company_name', '   '],
    'long company' => ['company_name', str_repeat('C', 256)],
    'long contact' => ['contact_name', str_repeat('C', 256)],
    'short phone' => ['phone', '123'],
    'unbalanced phone' => ['phone', '(675 7000 0000'],
    'long phone' => ['phone', str_repeat('7', 21)],
    'invalid email' => ['email', 'not-an-email'],
    'long address' => ['billing_address', str_repeat('A', 5001)],
    'long notes' => ['notes', str_repeat('N', 10001)],
    'invalid status' => ['is_active', 'yes'],
]);

test('site field limits and formats reject invalid input', function (string $field, mixed $value) {
    $user = User::factory()->create();
    $client = Client::factory()->create();

    $this->actingAs($user)->post(route('sites.store'), [
        'client_id' => $client->id,
        'site_name' => 'Valid Site',
        'address' => 'Valid Address',
        $field => $value,
    ])->assertSessionHasErrors([$field]);

    $this->assertDatabaseCount('sites', 0);
})->with([
    'unknown client' => ['client_id', 999999],
    'blank site' => ['site_name', '   '],
    'long site' => ['site_name', str_repeat('S', 256)],
    'blank address' => ['address', '   '],
    'long address' => ['address', str_repeat('A', 5001)],
    'long suburb' => ['suburb_region', str_repeat('S', 256)],
    'long access notes' => ['access_notes', str_repeat('N', 10001)],
    'long contact' => ['site_contact', str_repeat('C', 256)],
    'unbalanced phone' => ['site_phone', '(675 7000 0000'],
]);

test('client and site edits can clear optional details without changing site ownership', function () {
    $user = User::factory()->create();
    $client = Client::factory()->create([
        'contact_name' => 'Existing Contact',
        'phone' => '+675 7000 0000',
        'email' => 'existing@example.test',
        'billing_address' => 'Previous address',
        'notes' => 'Previous notes',
    ]);
    $site = Site::factory()->for($client)->create([
        'suburb_region' => 'Boroko',
        'access_notes' => 'Previous access',
        'site_contact' => 'Existing Manager',
        'site_phone' => '+675 7111 2222',
    ]);

    $this->actingAs($user)->put(route('clients.update', $client), [
        'company_name' => $client->company_name,
        'contact_name' => '',
        'phone' => '',
        'email' => '',
        'billing_address' => '',
        'notes' => '',
        'is_active' => '0',
    ])->assertSessionHasNoErrors();

    $this->put(route('sites.update', $site), [
        'client_id' => $client->id,
        'site_name' => $site->site_name,
        'address' => $site->address,
        'suburb_region' => '',
        'access_notes' => '',
        'site_contact' => '',
        'site_phone' => '',
    ])->assertSessionHasNoErrors();

    $this->assertDatabaseHas('clients', [
        'id' => $client->id,
        'contact_name' => null,
        'phone' => null,
        'email' => null,
        'billing_address' => null,
        'notes' => null,
        'is_active' => false,
    ]);
    $this->assertDatabaseHas('sites', [
        'id' => $site->id,
        'client_id' => $client->id,
        'suburb_region' => null,
        'access_notes' => null,
        'site_contact' => null,
        'site_phone' => null,
    ]);
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
