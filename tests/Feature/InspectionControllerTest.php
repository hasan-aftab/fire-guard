<?php

use App\Models\Asset;
use App\Models\Client;
use App\Models\FireNotification;
use App\Models\Inspection;
use App\Models\Site;
use App\Models\Technician;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('inspections page exposes the required setup choices and only the data it displays', function () {
    $this->travelTo(now()->setDate(2026, 10, 1)->startOfDay());
    $user = User::factory()->create();
    $asset = Asset::factory()->create(['serial_number' => 'EX-101', 'location_desc' => 'Hall']);
    $technician = Technician::factory()->create(['full_name' => 'Mara Kila']);
    $inspection = Inspection::factory()->for($asset)->for($technician)->create();

    $this->actingAs($user)->get(route('inspections.index'))
        ->assertInertia(fn (Assert $page) => $page->component('inspections')
            ->has('assets', 1)->where('assets.0.id', $asset->id)
            ->where('assets.0.asset_type.default_interval_months', 12)
            ->where('assets.0.suggested_next_due_date', '2027-10-01')
            ->where('assets.0.site.client.is_active', true)
            ->has('technicians', 1)->where('technicians.0.full_name', 'Mara Kila')
            ->where('assets.0.site.client.company_name', $asset->site->client->company_name)
            ->missing('technicians.0.email')
            ->has('records.data', 1)->where('records.data.0.id', $inspection->id)
            ->where('records.data.0.asset.serial_number', 'EX-101')
            ->where('records.data.0.asset.site.site_name', $asset->site->site_name)
            ->where('records.data.0.asset.site.client.company_name', $asset->site->client->company_name)
            ->where('records.data.0.technician.full_name', 'Mara Kila'));
});

test('inspections page has no creation choices until assets and technicians exist', function () {
    $user = User::factory()->create();
    Asset::factory()->create();

    $this->actingAs($user)->get(route('inspections.index'))
        ->assertInertia(fn (Assert $page) => $page->component('inspections')
            ->has('assets', 1)->has('technicians', 0)->has('records.data', 0));
});

test('inspection search matches asset location technician and notes while treating wildcards literally', function () {
    $user = User::factory()->create();
    $client = Client::factory()->create(['company_name' => 'A!_% Fire Services']);
    $site = Site::factory()->for($client)->create(['site_name' => 'Harbour depot']);
    $asset = Asset::factory()->for($site)->create(['serial_number' => 'EX-101', 'location_desc' => 'North stair']);
    $technician = Technician::factory()->create(['full_name' => 'Mara Kila']);
    $target = Inspection::factory()->for($asset)->for($technician)->create(['notes' => 'Pressure 80%']);
    Inspection::factory()->create(['notes' => 'Pressure 800']);

    foreach (['North stair', 'Mara Kila', '80%', 'Harbour depot', 'A!_% Fire'] as $search) {
        $this->actingAs($user)->get(route('inspections.index', ['search' => $search]))
            ->assertInertia(fn (Assert $page) => $page->has('records.data', 1)
                ->where('records.data.0.id', $target->id)->where('records.total', 1));
    }
});

test('inspection date filters accept a single endpoint and exclude other dates', function () {
    $user = User::factory()->create();
    $asset = Asset::factory()->create();
    $target = Inspection::factory()->for($asset)->create(['inspection_date' => '2026-09-20']);
    Inspection::factory()->for($asset)->create(['inspection_date' => '2026-09-19']);
    Inspection::factory()->for($asset)->create(['inspection_date' => '2026-09-21']);

    $this->actingAs($user)->get(route('inspections.index', ['from' => '2026-09-20', 'to' => '2026-09-20']))
        ->assertInertia(fn (Assert $page) => $page->has('records.data', 1)
            ->where('records.data.0.id', $target->id));
    $this->get(route('inspections.index', ['to' => '2026-09-19']))
        ->assertInertia(fn (Assert $page) => $page->has('records.data', 1)
            ->where('records.data.0.inspection_date', '2026-09-19'));
});

test('inspections can be narrowed by site and technician and ordered by date with stable ties', function () {
    $user = User::factory()->create();
    $site = Site::factory()->create(['site_name' => 'Harbour depot']);
    $asset = Asset::factory()->for($site)->create();
    $technician = Technician::factory()->create(['full_name' => 'Mara Kila']);
    $first = Inspection::factory()->for($asset)->for($technician)->create(['inspection_date' => '2026-09-01']);
    $second = Inspection::factory()->for($asset)->for($technician)->create(['inspection_date' => '2026-09-01']);
    Inspection::factory()->for($asset)->create(['inspection_date' => '2026-09-02']);
    Inspection::factory()->for($technician)->create(['inspection_date' => '2026-09-03']);

    $filters = ['site_id' => $site->id, 'technician_id' => $technician->id, 'sort' => 'oldest'];
    $this->actingAs($user)->get(route('inspections.index', $filters))
        ->assertInertia(fn (Assert $page) => $page->component('inspections')
            ->has('records.data', 2)->where('records.data.0.id', $first->id)
            ->where('records.data.1.id', $second->id)->where('records.total', 2)
            ->where('filters.site_id', (string) $site->id)->where('filters.technician_id', (string) $technician->id)
            ->where('filters.sort', 'oldest')->missing('filters.page'));
    $this->get(route('inspections.index', ['site_id' => $site->id, 'technician_id' => $technician->id]))
        ->assertInertia(fn (Assert $page) => $page->where('records.data.0.id', $second->id)
            ->where('records.data.1.id', $first->id));
});

test('inspection relation filters and sorting reject invalid values', function () {
    $this->actingAs(User::factory()->create())->get(route('inspections.index', [
        'site_id' => 999999,
        'technician_id' => 999999,
        'sort' => 'due_soonest',
    ]))->assertSessionHasErrors(['site_id', 'technician_id', 'sort']);
});

test('inspection filtering preserves the last populated page after results shrink', function () {
    $user = User::factory()->create();
    $site = Site::factory()->create();
    $asset = Asset::factory()->for($site)->create();
    $technician = Technician::factory()->create();
    Inspection::factory()->count(11)->for($asset)->for($technician)->create(['inspection_date' => '2026-09-20']);
    $filters = ['site_id' => $site->id, 'technician_id' => $technician->id, 'sort' => 'oldest'];

    $response = $this->actingAs($user)->get(route('inspections.index', $filters + ['page' => 3]))
        ->assertRedirect();
    parse_str((string) parse_url((string) $response->headers->get('Location'), PHP_URL_QUERY), $redirectFilters);
    expect($redirectFilters)->toEqual([
        'site_id' => (string) $site->id,
        'technician_id' => (string) $technician->id,
        'sort' => 'oldest',
        'page' => '2',
    ]);
    $this->get(route('inspections.index', $filters + ['page' => 2]))
        ->assertInertia(fn (Assert $page) => $page->has('records.data', 1)
            ->where('records.current_page', 2)->where('records.total', 11));
});

test('completed inspections remain on their filtered page after a forbidden delete', function () {
    $user = User::factory()->create();
    $asset = Asset::factory()->create(['serial_number' => 'EX-PAGED']);
    $technician = Technician::factory()->create();
    $inspections = Inspection::factory()->count(11)->for($asset)->for($technician)
        ->create(['inspection_date' => '2026-09-20', 'result' => 'pass']);
    $filters = ['search' => 'EX-PAGED', 'result' => 'pass', 'from' => '2026-09-20'];
    $pageTwoUrl = route('inspections.index', $filters + ['page' => 2]);

    $this->actingAs($user)->get($pageTwoUrl)
        ->assertInertia(fn (Assert $page) => $page->has('records.data', 1)
            ->where('records.current_page', 2)->missing('filters.page'));
    $this->from($pageTwoUrl)->delete('/inspections/'.$inspections->first()->id)
        ->assertStatus(405);

    $this->get($pageTwoUrl)
        ->assertInertia(fn (Assert $page) => $page->has('records.data', 1)
            ->where('records.current_page', 2)->where('records.total', 11));
});

test('inspection writes reject invalid relationships and due dates', function () {
    $user = User::factory()->create();
    $asset = Asset::factory()->create();
    $technician = Technician::factory()->create();

    $this->actingAs($user)->post(route('inspections.store'), [
        'asset_id' => $asset->id,
        'technician_id' => 999999,
        'inspection_date' => '2026-09-20',
        'result' => 'pass',
        'next_due_date' => '2026-09-19',
    ])->assertSessionHasErrors(['technician_id', 'next_due_date']);
    $this->assertDatabaseCount('inspections', 0);

    $this->post(route('inspections.store'), [
        'asset_id' => $asset->id,
        'technician_id' => $technician->id,
        'inspection_date' => '2026-09-20',
        'result' => 'pass',
        'next_due_date' => '2026-10-20',
    ])->assertSessionHasNoErrors();
    $this->assertDatabaseHas('inspections', [
        'asset_id' => $asset->id,
        'next_due_date' => '2026-10-20',
    ]);
});

test('inactive technicians remain attributed in inspection history', function () {
    $user = User::factory()->create();
    $technician = Technician::factory()->create();
    $inspection = Inspection::factory()->for($technician)->create();
    $this->actingAs($user)->delete(route('technicians.destroy', $technician))->assertRedirect();

    $this->actingAs($user)->get(route('inspections.index'))
        ->assertInertia(fn (Assert $page) => $page->component('inspections')
            ->where('records.data.0.id', $inspection->id)
            ->where('records.data.0.technician.full_name', $technician->full_name));
});

test('logging a failed inspection creates an alert and passing it clears the alert', function () {
    $user = User::factory()->create();
    $asset = Asset::factory()->create(['next_service_due' => null]);
    $technician = Technician::factory()->create();
    $payload = [
        'asset_id' => $asset->id,
        'technician_id' => $technician->id,
        'inspection_date' => '2026-09-20',
        'result' => 'fail',
    ];

    $this->actingAs($user)->post(route('inspections.store'), $payload)->assertSessionHasNoErrors();
    $inspection = Inspection::query()->firstOrFail();
    $this->assertDatabaseHas('fire_notifications', [
        'user_id' => $user->id,
        'type' => 'failed_inspection',
        'entity_id' => $inspection->id,
    ]);

    $this->put(route('inspections.update', $inspection), array_merge($payload, ['result' => 'pass']))
        ->assertSessionHasNoErrors();
    expect($inspection->refresh()->result)->toBe('pass');
    expect(FireNotification::where('user_id', $user->id)->count())->toBe(0);
});
