<?php

use App\Models\Asset;
use App\Models\AssetType;
use App\Models\FireNotification;
use App\Models\Inspection;
use App\Models\Technician;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

function schedulePayload(Asset $asset, Technician $technician, string $date, array $changes = []): array
{
    return array_merge([
        'asset_id' => $asset->id,
        'technician_id' => $technician->id,
        'inspection_date' => $date,
        'result' => 'pass',
    ], $changes);
}

test('a completed inspection calculates and stores the current schedule', function () {
    $type = AssetType::factory()->create(['default_interval_months' => 12]);
    $asset = Asset::factory()->for($type)->create(['last_service_date' => null, 'next_service_due' => null]);
    $technician = Technician::factory()->create();

    $this->actingAs(User::factory()->create())->post(route('inspections.store'), schedulePayload($asset, $technician, '2026-10-10'))
        ->assertSessionHasNoErrors();

    $this->assertDatabaseHas('inspections', ['asset_id' => $asset->id, 'inspection_date' => '2026-10-10', 'next_due_date' => '2027-10-10']);
    $this->assertDatabaseHas('assets', ['id' => $asset->id, 'last_service_date' => '2026-10-10', 'next_service_due' => '2027-10-10']);
});

test('a manually entered next inspection date replaces the calculated date', function () {
    $asset = Asset::factory()->create();
    $technician = Technician::factory()->create();

    $this->actingAs(User::factory()->create())->post(route('inspections.store'), schedulePayload($asset, $technician, '2026-10-10', ['next_due_date' => '2027-04-10']))
        ->assertSessionHasNoErrors();

    $this->assertDatabaseHas('inspections', ['asset_id' => $asset->id, 'next_due_date' => '2027-04-10']);
    $this->assertDatabaseHas('assets', ['id' => $asset->id, 'next_service_due' => '2027-04-10']);
});

test('interval calculations clamp month ends and leap days', function (int $months, string $date, string $expected) {
    $type = AssetType::factory()->create(['default_interval_months' => $months]);
    $asset = Asset::factory()->for($type)->create();
    $technician = Technician::factory()->create();

    $this->actingAs(User::factory()->create())->post(route('inspections.store'), schedulePayload($asset, $technician, $date))
        ->assertSessionHasNoErrors();

    $this->assertDatabaseHas('assets', ['id' => $asset->id, 'last_service_date' => $date, 'next_service_due' => $expected]);
})->with([
    'six months' => [6, '2026-01-15', '2026-07-15'],
    'January month end' => [1, '2026-01-31', '2026-02-28'],
    'leap day to non-leap year' => [12, '2024-02-29', '2025-02-28'],
]);

test('out-of-order entry and editing old history cannot replace the newer schedule', function () {
    $asset = Asset::factory()->create();
    $technician = Technician::factory()->create();
    $this->actingAs(User::factory()->create());

    $this->post(route('inspections.store'), schedulePayload($asset, $technician, '2026-09-01'))->assertSessionHasNoErrors();
    $this->post(route('inspections.store'), schedulePayload($asset, $technician, '2026-03-01'))->assertSessionHasNoErrors();
    $older = Inspection::where('inspection_date', '2026-03-01')->firstOrFail();
    $this->put(route('inspections.update', $older), schedulePayload($asset, $technician, '2026-04-01', ['next_due_date' => '2027-05-01']))
        ->assertSessionHasNoErrors();

    $this->assertDatabaseHas('inspections', ['id' => $older->id, 'inspection_date' => '2026-04-01', 'next_due_date' => '2027-05-01']);
    $this->assertDatabaseHas('assets', ['id' => $asset->id, 'last_service_date' => '2026-09-01', 'next_service_due' => '2027-09-01']);
});

test('editing the latest date recalculates its default and respects chronological order', function () {
    $asset = Asset::factory()->create();
    $technician = Technician::factory()->create();
    $this->actingAs(User::factory()->create());
    $this->post(route('inspections.store'), schedulePayload($asset, $technician, '2026-01-10'));
    $this->post(route('inspections.store'), schedulePayload($asset, $technician, '2026-10-10'));
    $latest = Inspection::where('inspection_date', '2026-10-10')->firstOrFail();

    $this->put(route('inspections.update', $latest), schedulePayload($asset, $technician, '2026-10-12', ['next_due_date' => '2027-10-10']))
        ->assertSessionHasNoErrors();

    $this->assertDatabaseHas('inspections', ['id' => $latest->id, 'next_due_date' => '2027-10-12']);
    $this->assertDatabaseHas('assets', ['id' => $asset->id, 'last_service_date' => '2026-10-12', 'next_service_due' => '2027-10-12']);
});

test('moving an inspection recalculates both assets from their remaining histories', function () {
    $original = Asset::factory()->create(['last_service_date' => null, 'next_service_due' => null]);
    $replacement = Asset::factory()->create(['last_service_date' => null, 'next_service_due' => null]);
    $technician = Technician::factory()->create();
    $this->actingAs(User::factory()->create());
    $this->post(route('inspections.store'), schedulePayload($original, $technician, '2026-02-01'));
    $this->post(route('inspections.store'), schedulePayload($original, $technician, '2026-08-01'));
    $moving = Inspection::where('inspection_date', '2026-08-01')->firstOrFail();

    $this->put(route('inspections.update', $moving), schedulePayload($replacement, $technician, '2026-08-01', ['next_due_date' => '2027-08-01']))
        ->assertSessionHasNoErrors();

    $this->assertDatabaseHas('assets', ['id' => $original->id, 'last_service_date' => '2026-02-01', 'next_service_due' => '2027-02-01']);
    $this->assertDatabaseHas('assets', ['id' => $replacement->id, 'last_service_date' => '2026-08-01', 'next_service_due' => '2027-08-01']);
});

test('same-day inspections use the later record as the schedule tie breaker', function () {
    $asset = Asset::factory()->create();
    $technician = Technician::factory()->create();
    $this->actingAs(User::factory()->create());

    $this->post(route('inspections.store'), schedulePayload($asset, $technician, '2026-10-10', ['next_due_date' => '2027-10-10']));
    $this->post(route('inspections.store'), schedulePayload($asset, $technician, '2026-10-10', ['next_due_date' => '2027-04-10']))
        ->assertSessionHasNoErrors();

    $this->assertDatabaseHas('assets', ['id' => $asset->id, 'last_service_date' => '2026-10-10', 'next_service_due' => '2027-04-10']);
});

test('an absent or invalid interval requires a manual next inspection date', function (?int $months) {
    $type = AssetType::factory()->create(['default_interval_months' => $months]);
    $asset = Asset::factory()->for($type)->create(['next_service_due' => null]);
    $technician = Technician::factory()->create();
    $this->actingAs(User::factory()->create());

    $this->post(route('inspections.store'), schedulePayload($asset, $technician, '2026-10-10'))
        ->assertSessionHasErrors(['next_due_date' => 'Enter a next inspection date because this asset type has no inspection interval.']);
    $this->assertDatabaseCount('inspections', 0);
    $this->post(route('inspections.store'), schedulePayload($asset, $technician, '2026-10-10', ['next_due_date' => '2027-04-10']))
        ->assertSessionHasNoErrors();
    $this->assertDatabaseHas('assets', ['id' => $asset->id, 'next_service_due' => '2027-04-10']);
})->with(['missing' => [null], 'zero' => [0]]);

test('editing a decommissioned asset inspection retains history without rescheduling or reactivating it', function () {
    $asset = Asset::factory()->create(['status' => 'decommissioned', 'last_service_date' => '2026-01-01', 'next_service_due' => '2027-01-01']);
    $technician = Technician::factory()->create();
    $inspection = Inspection::factory()->for($asset)->for($technician)->create(['inspection_date' => '2026-01-01', 'next_due_date' => '2027-01-01']);

    $this->actingAs(User::factory()->create())->put(route('inspections.update', $inspection), schedulePayload($asset, $technician, '2026-02-01', ['next_due_date' => '2027-01-01']))
        ->assertSessionHasNoErrors();

    $this->assertDatabaseHas('assets', ['id' => $asset->id, 'status' => 'decommissioned', 'last_service_date' => '2026-01-01', 'next_service_due' => '2027-01-01']);
    $this->assertDatabaseHas('inspections', ['id' => $inspection->id, 'inspection_date' => '2026-02-01']);
});

test('marking the latest inspection entered in error switches to the previous valid schedule', function () {
    $asset = Asset::factory()->create();
    $technician = Technician::factory()->create();
    $this->actingAs(User::factory()->create());
    $this->post(route('inspections.store'), schedulePayload($asset, $technician, '2026-01-01'));
    $this->post(route('inspections.store'), schedulePayload($asset, $technician, '2026-07-01'));
    $latest = Inspection::where('inspection_date', '2026-07-01')->firstOrFail();

    $this->patch(route('inspections.mark-error', $latest))->assertRedirect();
    $this->assertDatabaseHas('assets', ['id' => $asset->id, 'last_service_date' => '2026-01-01', 'next_service_due' => '2027-01-01']);
    $this->patch(route('inspections.restore-error', $latest))->assertRedirect();
    $this->assertDatabaseHas('assets', ['id' => $asset->id, 'last_service_date' => '2026-07-01', 'next_service_due' => '2027-07-01']);
});

test('an asset schedule update failure rolls back the inspection write', function () {
    $asset = Asset::factory()->create(['last_service_date' => null, 'next_service_due' => null]);
    $technician = Technician::factory()->create();
    DB::unprepared("CREATE TRIGGER prevent_asset_schedule BEFORE UPDATE ON assets BEGIN SELECT RAISE(ABORT, 'schedule unavailable'); END");

    try {
        $this->withoutExceptionHandling()->actingAs(User::factory()->create())
            ->post(route('inspections.store'), schedulePayload($asset, $technician, '2026-10-10'));
    } catch (QueryException $exception) {
        expect($exception->getMessage())->toContain('schedule unavailable');
    } finally {
        DB::unprepared('DROP TRIGGER prevent_asset_schedule');
    }

    $this->assertDatabaseCount('inspections', 0);
    $this->assertDatabaseHas('assets', ['id' => $asset->id, 'last_service_date' => null, 'next_service_due' => null]);
});

test('a completed inspection updates dashboard due status without duplicate calendar work', function () {
    $this->travelTo(now()->setDate(2026, 10, 1)->startOfDay());
    $asset = Asset::factory()->create(['next_service_due' => '2026-09-30']);
    $technician = Technician::factory()->create();
    $user = User::factory()->create();
    $this->actingAs($user);

    $this->post(route('inspections.store'), schedulePayload($asset, $technician, '2026-10-01', ['next_due_date' => '2026-10-10']))
        ->assertSessionHasNoErrors();

    $this->assertDatabaseHas('fire_notifications', ['user_id' => $user->id, 'type' => 'due_soon_asset', 'entity_type' => 'asset', 'entity_id' => $asset->id]);
    expect(FireNotification::where('user_id', $user->id)->where('type', 'overdue_asset')->count())->toBe(0);

    $this->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('kpis.overdue.total', 0)
            ->where('schedule.totalCount', 1)
            // Day buckets are contiguous from the start of the window, so the
            // 10th is the ninth cell rather than the first.
            ->where('schedule.days.0.date', '2026-10-01')
            ->where('schedule.days.0.itemCount', 0)
            ->where('schedule.days.9.date', '2026-10-10')
            // Strip cells carry a count, not the rows: the rows live in the
            // grouped agenda so a day cannot repeat one record many times over.
            ->where('schedule.days.9.itemCount', 1)
            // 10 Oct is offset 9 from a window starting 1 Oct, which is past the
            // offset-7 boundary, so the row belongs to "later", not "next".
            ->has('schedule.groups.2.items', 1)
            ->where('schedule.groups.2.id', 'later')
            ->where('schedule.groups.2.items.0.kind', 'asset')
            ->where('schedule.groups.2.items.0.dueDate', '2026-10-10'));
});
