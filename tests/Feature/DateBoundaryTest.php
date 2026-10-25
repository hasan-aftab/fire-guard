<?php

use App\Actions\BuildScheduleAgenda;
use App\Models\Asset;
use App\Models\AssetType;
use App\Models\Certificate;
use App\Models\Site;
use App\Models\User;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->travelTo(now()->setDate(2026, 10, 4)->startOfDay());
    $this->site = Site::factory()->create();
});

function assetDue(?string $due): Asset
{
    return Asset::factory()
        ->for(test()->site)
        ->for(AssetType::factory())
        ->create(['next_service_due' => $due]);
}

function certificateDue(string $due): Certificate
{
    return Certificate::factory()
        ->for(test()->site)
        ->create(['expiry_date' => $due]);
}

test('an asset is due soon through the fourteenth day and fine from the fifteenth', function (string $offset, string $expected) {
    expect(assetDue(today()->addDays((int) $offset))->service_status)->toBe($expected);
})->with([
    'yesterday is overdue' => ['-1', 'overdue'],
    'a week ago is overdue' => ['-7', 'overdue'],
    'today is not yet overdue' => ['0', 'due_soon'],
    'tomorrow is due soon' => ['1', 'due_soon'],
    'day fourteen is still due soon' => ['14', 'due_soon'],
    'day fifteen is fine' => ['15', 'ok'],
    'day thirty is fine' => ['30', 'ok'],
]);

test('an asset with no service date has an unknown service status', function () {
    expect(assetDue(null)->service_status)->toBe('unknown');
});

test('the default window covers every day the due-soon status calls due soon', function () {
    $atTheEdge = assetDue(today()->addDays(14)->toDateString());
    $justOutside = assetDue(today()->addDays(15)->toDateString());

    $schedule = app(BuildScheduleAgenda::class)->handle(today()->toImmutable());

    expect($schedule['to'])->toBe(today()->addDays(Asset::DUE_SOON_DAYS)->toDateString())
        // Day fourteen is due soon, so it has to be inside the default window: a
        // window that stopped one day short would hide work the status calls urgent.
        ->and($atTheEdge->service_status)->toBe('due_soon')
        ->and(scheduleDayBucket($schedule, $atTheEdge->next_service_due->toDateString()))->not->toBeEmpty()
        // Day fifteen is already fine, so it must fall outside the default window.
        ->and($justOutside->service_status)->toBe('ok')
        ->and(scheduleDayBucket($schedule, $justOutside->next_service_due->toDateString()))->toBeEmpty();
});

test('the default window leaves day fifteen out entirely', function () {
    $schedule = app(BuildScheduleAgenda::class)->handle(today()->toImmutable());

    expect(collect($schedule['days'])->pluck('date'))->not->toContain(today()->addDays(15)->toDateString())
        ->and($schedule['days'])->toHaveCount(Asset::DUE_SOON_DAYS + 1);
});

/**
 * The items bucketed under one date, or an empty array when the date is
 * outside the window entirely.
 *
 * @return array<array<string, mixed>>
 */
function scheduleDayBucket(array $schedule, string $date): array
{
    return collect($schedule['groups'])
        ->flatMap(fn (array $group): array => $group['items'])
        ->where('dueDate', $date)
        ->values()
        ->all();
}

test('a certificate expires at thirty days and is still expiring soon on that day', function (string $offset, string $expected) {
    expect(certificateDue(today()->addDays((int) $offset))->status)->toBe($expected);
})->with([
    'yesterday has expired' => ['-1', 'expired'],
    'a month ago has expired' => ['-30', 'expired'],
    'today is expiring soon' => ['0', 'expiring_soon'],
    'day thirty is still expiring soon' => ['30', 'expiring_soon'],
    'day thirty one is valid' => ['31', 'valid'],
]);

test('the boundary is not affected by the time of day the record is read at', function () {
    // A date-only record read at the last instant of the day must not flip to
    // overdue, and the same record read the next morning must be.
    $this->travelTo(now()->setDate(2026, 10, 4)->setTime(23, 59));
    expect(assetDue('2026-10-04')->service_status)->toBe('due_soon');

    $this->travelTo(now()->setDate(2026, 10, 5)->startOfDay());
    expect(assetDue('2026-10-04')->service_status)->toBe('overdue');
});

test('today is read in the application timezone rather than utc', function () {
    // Both instants are still the 3rd of October in UTC, but already the 4th
    // and the 1st of January in the application timezone (pinned to UTC+10 for
    // the test suite in phpunit.xml).
    $this->travelTo(CarbonImmutable::parse('2026-10-03 20:00:00', 'UTC'));
    expect(today()->toDateString())->toBe('2026-10-04');

    $this->travelTo(CarbonImmutable::parse('2026-09-30 20:00:00', 'UTC'));
    expect(today()->toDateString())->toBe('2026-10-01');

    $this->travelTo(CarbonImmutable::parse('2025-12-31 20:00:00', 'UTC'));
    expect(today()->toDateString())->toBe('2026-01-01');
});

test('the shared today prop is the application timezone date', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-03 20:00:00', 'UTC'));

    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('today', today()->toDateString()));

    expect(config('app.timezone'))->toBe('Etc/GMT-10');
});

test('status computed for an instant just after midnight in utc uses the application date', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-03 20:00:00', 'UTC'));

    // In the application timezone it is already the 4th, so this is due today.
    expect(assetDue('2026-10-04')->service_status)->toBe('due_soon')
        ->and(assetDue('2026-10-03')->service_status)->toBe('overdue');
});
