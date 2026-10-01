<?php

use App\Models\Inspection;
use App\Models\Technician;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

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

test('technician filters and fields reject invalid values without changing records', function () {
    $user = User::factory()->create();
    $technician = Technician::factory()->create();

    $this->actingAs($user)->get(route('technicians.index', ['status' => 'overdue']))
        ->assertSessionHasErrors(['status']);

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
