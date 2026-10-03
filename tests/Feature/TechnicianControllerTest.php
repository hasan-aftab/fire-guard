<?php

use App\Models\Inspection;
use App\Models\Technician;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('technicians page has zero overview counts when no records exist', function () {
    $this->actingAs(User::factory()->create())->get(route('technicians.index'))
        ->assertInertia(fn (Assert $page) => $page->component('technicians')
            ->has('records.data', 0)
            ->where('overview.active', 0)
            ->where('overview.expired', 0)
            ->where('overview.expiring_soon', 0)
            ->where('overview.missing', 0));
});

test('technicians can be searched by email with literal wildcard characters and filtered by status', function () {
    $user = User::factory()->create();
    $target = Technician::factory()->create(['full_name' => 'A!_% Crew', 'email' => 'crew+alpha@example.test', 'is_active' => false]);
    Technician::factory()->create(['full_name' => 'Axy Crew', 'email' => 'crew+beta@example.test', 'is_active' => false]);
    Technician::factory()->create(['full_name' => 'Other Crew', 'email' => 'crew+alpha@elsewhere.test', 'is_active' => true]);

    $this->actingAs($user)->get(route('technicians.index', ['search' => 'A!_%', 'status' => 'inactive']))
        ->assertInertia(fn (Assert $page) => $page->component('technicians')
            ->has('records.data', 1)
            ->where('records.data.0.id', $target->id)
            ->where('records.total', 1)
            ->where('filters.status', 'inactive'));

    $this->get(route('technicians.index', ['search' => 'ALPHA@EXAMPLE', 'status' => 'inactive']))
        ->assertInertia(fn (Assert $page) => $page->has('records.data', 1)
            ->where('records.data.0.id', $target->id));

    $this->get(route('technicians.index', ['status' => 'active']))
        ->assertInertia(fn (Assert $page) => $page->has('records.data', 1)
            ->where('records.data.0.is_active', true));
});

test('technician pagination stays bounded and excludes page from active filters', function () {
    $user = User::factory()->create();
    Technician::factory()->count(11)->create(['full_name' => 'Field Technician', 'is_active' => true]);
    $url = route('technicians.index', ['search' => 'Field', 'status' => 'active']);

    $this->actingAs($user)->get(route('technicians.index', ['search' => 'Field', 'status' => 'active', 'page' => 2]))
        ->assertInertia(fn (Assert $page) => $page->component('technicians')
            ->has('records.data', 1)
            ->where('records.current_page', 2)
            ->missing('filters.page'));

    $this->get(route('technicians.index', ['search' => 'Field', 'status' => 'active', 'page' => 3]))
        ->assertRedirect(route('technicians.index', ['search' => 'Field', 'status' => 'active', 'page' => 2]));

    Technician::query()->where('full_name', 'Field Technician')->firstOrFail()->delete();
    $this->get(route('technicians.index', ['search' => 'Field', 'status' => 'active', 'page' => 2]))
        ->assertRedirect($url);
});

test('technician licence status uses the server date at expiry boundaries', function () {
    $this->travelTo(now()->setDate(2026, 9, 30)->startOfDay());
    $user = User::factory()->create();
    $expired = Technician::factory()->create(['license_expiry' => '2026-09-29']);
    $today = Technician::factory()->create(['license_expiry' => '2026-09-30']);
    $boundary = Technician::factory()->create(['license_expiry' => '2026-10-30']);
    $valid = Technician::factory()->create(['license_expiry' => '2026-10-31']);
    $missing = Technician::factory()->create(['license_expiry' => null]);
    Technician::factory()->create(['license_expiry' => '2026-09-29', 'is_active' => false]);
    Technician::factory()->create(['license_expiry' => '2026-09-29', 'entered_in_error_at' => now()]);

    $response = $this->actingAs($user)->get(route('technicians.index'));
    $response->assertInertia(fn (Assert $page) => $page->component('technicians')
        ->where('overview.active', 5)
        ->where('overview.expired', 1)
        ->where('overview.expiring_soon', 2)
        ->where('overview.missing', 1)
        ->has('records.data', 6));

    foreach ([
        ['expired', $expired->id, 'expired'],
        ['valid', $valid->id, 'valid'],
        ['missing', $missing->id, 'unknown'],
    ] as [$filter, $id, $status]) {
        $this->get(route('technicians.index', ['license_status' => $filter, 'status' => 'active']))
            ->assertInertia(fn (Assert $page) => $page->where('records.data.0.id', $id)
                ->where('records.data.0.license_status', $status));
    }

    $this->get(route('technicians.index', ['license_status' => 'expiring_soon', 'status' => 'active']))
        ->assertInertia(fn (Assert $page) => $page->has('records.data', 2)
            ->where('records.total', 2));

    $this->get(route('technicians.index', ['from' => '2026-09-30', 'to' => '2026-09-30']))
        ->assertInertia(fn (Assert $page) => $page->has('records.data', 1)
            ->where('records.data.0.id', $today->id));

    $this->get(route('technicians.index', ['from' => '2026-10-30', 'to' => '2026-10-30']))
        ->assertInertia(fn (Assert $page) => $page->has('records.data', 1)
            ->where('records.data.0.id', $boundary->id));
});

test('technician inspection history and sorts stay server bounded', function () {
    $user = User::factory()->create();
    $alpha = Technician::factory()->create(['full_name' => 'Alpha', 'license_expiry' => null]);
    $beta = Technician::factory()->create(['full_name' => 'Beta', 'license_expiry' => '2027-01-01']);
    $gamma = Technician::factory()->create(['full_name' => 'Gamma', 'license_expiry' => '2026-12-01']);
    Inspection::factory()->for($beta)->count(2)->create();
    Inspection::factory()->for($gamma)->create();

    $this->actingAs($user)->get(route('technicians.index', ['inspection_activity' => 'with_inspections', 'sort' => 'most_inspections']))
        ->assertInertia(fn (Assert $page) => $page->has('records.data', 2)
            ->where('records.data.0.id', $beta->id)
            ->where('records.data.0.inspections_count', 2)
            ->where('records.data.1.id', $gamma->id));

    $this->get(route('technicians.index', ['inspection_activity' => 'without_inspections']))
        ->assertInertia(fn (Assert $page) => $page->has('records.data', 1)
            ->where('records.data.0.id', $alpha->id));

    $this->get(route('technicians.index', ['sort' => 'expiry_soonest']))
        ->assertInertia(fn (Assert $page) => $page->where('records.data.0.id', $gamma->id)
            ->where('records.data.1.id', $beta->id)
            ->where('records.data.2.id', $alpha->id));
});

test('technician filters and fields reject invalid values without changing records', function () {
    $user = User::factory()->create();
    $technician = Technician::factory()->create();

    $this->actingAs($user)->get(route('technicians.index', ['status' => 'overdue']))
        ->assertSessionHasErrors(['status']);

    foreach ([
        [['license_status' => 'overdue'], 'license_status'],
        [['inspection_activity' => 'recent'], 'inspection_activity'],
        [['sort' => 'drop table'], 'sort'],
        [['from' => '2026-02-30'], 'from'],
        [['from' => '2026-10-30', 'to' => '2026-09-30'], 'to'],
    ] as [$filters, $error]) {
        $this->get(route('technicians.index', $filters))->assertSessionHasErrors([$error]);
    }

    $this->put(route('technicians.update', $technician), [
        'full_name' => 'Updated Name',
        'phone' => 'office desk',
        'email' => 'not-an-email',
        'license_expiry' => '2026-02-30',
        'is_active' => 'maybe',
    ])->assertSessionHasErrors(['phone', 'email', 'license_expiry', 'is_active']);

    expect($technician->refresh()->full_name)->not->toBe('Updated Name');
});

test('technician edits retain licence data and deactivation keeps inspection attribution', function () {
    $user = User::factory()->create();
    $technician = Technician::factory()->create();
    $inspection = Inspection::factory()->for($technician)->create();

    $this->actingAs($user)->put(route('technicians.update', $technician), [
        'full_name' => 'Ruth Ovia',
        'phone' => '+675 7000 0000',
        'email' => 'ruth@example.test',
        'license_number' => 'PNG-417',
        'license_expiry' => '2027-04-30',
        'is_active' => '0',
    ])->assertSessionHasNoErrors()->assertRedirect();

    $this->assertDatabaseHas('technicians', [
        'id' => $technician->id,
        'full_name' => 'Ruth Ovia',
        'license_number' => 'PNG-417',
        'license_expiry' => '2027-04-30',
        'is_active' => false,
    ]);

    $this->delete(route('technicians.destroy', $technician))->assertRedirect();
    $this->assertModelExists($technician);
    expect($inspection->refresh()->technician_id)->toBe($technician->id);
    $this->assertModelExists($inspection);
});
