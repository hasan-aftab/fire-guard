<?php

use App\Actions\BuildDashboardKpis;
use App\Actions\BuildScheduleAgenda;
use App\Models\Asset;
use App\Models\AssetType;
use App\Models\Certificate;
use App\Models\CertificateType;
use App\Models\Client;
use App\Models\Site;
use App\Models\User;
use Illuminate\Support\Collection;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->travelTo(now()->setDate(2026, 10, 4)->startOfDay());
    $this->agenda = fn (?string $from = null, ?string $to = null, string $type = 'all', ?string $horizon = null, string $overdue = 'include', ?string $day = null): array => app(BuildScheduleAgenda::class)
        ->handle(today()->toImmutable(), $from, $to, $type, $horizon, $overdue, $day);
});

/**
 * Creates an actionable asset belonging to a fresh, active client.
 */
function scheduleAsset(?string $due, array $attributes = []): Asset
{
    $site = Site::factory()->create();

    return Asset::factory()
        ->for($site)
        ->for(AssetType::factory())
        ->create(['next_service_due' => $due, ...$attributes]);
}

/**
 * Every row the agenda returns, overdue block first, in render order.
 *
 * @return Collection<int, array<string, mixed>>
 */
function scheduleRows(array $schedule): Collection
{
    return collect($schedule['groups'])
        ->flatMap(fn (array $group): array => $group['items'])
        ->values();
}

/**
 * The dates the agenda claims to cover, one entry per strip cell.
 *
 * @return array<int, string>
 */
function scheduleDayDates(array $schedule): array
{
    return collect($schedule['days'])->pluck('date')->all();
}

test('the default window runs from today through today plus fourteen days', function () {
    $schedule = ($this->agenda)();

    expect($schedule['from'])->toBe('2026-10-04')
        ->and($schedule['to'])->toBe('2026-10-18')
        ->and($schedule['horizonDays'])->toBe(15)
        ->and($schedule['type'])->toBe('all')
        ->and($schedule['truncated'])->toBeFalse();
});

test('the default window is the preset the models call due soon', function () {
    $presets = BuildScheduleAgenda::horizonPresets();

    expect($presets[BuildScheduleAgenda::defaultHorizonPreset()])->toBe(Asset::DUE_SOON_DAYS)
        ->and(BuildScheduleAgenda::presetEnd(today()->toImmutable(), null))
        ->toEqual(today()->toImmutable()->addDays(Asset::DUE_SOON_DAYS));
});

test('a preset offset is inclusive at both ends', function (string $preset, string $to, int $cells) {
    $schedule = ($this->agenda)(null, null, 'all', $preset);

    expect($schedule['from'])->toBe('2026-10-04')
        ->and($schedule['to'])->toBe($to)
        ->and($schedule['days'])->toHaveCount($cells)
        ->and($schedule['horizonOffset'])->toBe($cells - 1)
        ->and($schedule['horizonDays'])->toBe($cells)
        ->and($schedule['horizonPreset'])->toBe($preset);
})->with([
    'seven days is eight cells' => ['7d', '2026-10-11', 8],
    'fourteen days is fifteen cells' => ['14d', '2026-10-18', 15],
    'thirty days is thirty-one cells' => ['30d', '2026-11-03', 31],
]);

test('every preset ships to the client so the strip can derive the active one', function () {
    $schedule = ($this->agenda)();

    expect($schedule['horizonPresets'])->toBe(['7d' => 7, '14d' => 14, '30d' => 30])
        ->and($schedule['defaultHorizonPreset'])->toBe('14d');
});

test('an unknown preset falls back to the default rather than widening the window', function () {
    $schedule = ($this->agenda)(null, null, 'all', 'not-a-preset');

    expect($schedule['to'])->toBe('2026-10-18')
        ->and($schedule['horizonPreset'])->toBe('14d');
});

test('a window that starts after today reports no preset even when it ends on a preset offset', function () {
    $schedule = ($this->agenda)('2026-10-04', '2026-10-11');

    expect($schedule['days'])->toHaveCount(8)
        ->and($schedule['horizonPreset'])->toBe('7d');

    // A narrower range ending on the same offset is a custom window, not 7d.
    $narrower = ($this->agenda)('2026-10-08', '2026-10-11');

    expect($narrower['days'])->toHaveCount(4)
        ->and($narrower['horizonPreset'])->toBeNull();
});

test('today is resolved against the as-of date, not the window start', function () {
    $schedule = ($this->agenda)('2026-10-08', '2026-10-11');

    expect($schedule['days'][0]['date'])->toBe('2026-10-08')
        ->and($schedule['days'][0]['isToday'])->toBeFalse()
        ->and(collect($schedule['days'])->where('isToday', true))->toBeEmpty();
});

test('the window item count excludes overdue work', function () {
    scheduleAsset('2026-09-01');
    scheduleAsset('2026-10-05');
    scheduleAsset('2026-10-06');

    $schedule = ($this->agenda)();

    expect($schedule['overdueCount'])->toBe(1)
        ->and($schedule['windowItemCount'])->toBe(2)
        ->and($schedule['totalCount'])->toBe(3);
});

test('the day strip always gets one cell per day of the window even with no work', function () {
    $schedule = ($this->agenda)();

    $dates = scheduleDayDates($schedule);

    expect($dates)->toHaveCount(15)
        ->and($dates)->toBe(collect(range(4, 18))
        ->map(fn (int $day): string => '2026-10-'.str_pad((string) $day, 2, '0', STR_PAD_LEFT))
        ->all())
        ->and(collect($schedule['days'])->pluck('itemCount')->unique()->all())->toBe([0])
        ->and($schedule['days'][0]['isToday'])->toBeTrue()
        ->and(collect($schedule['days'])->pluck('isToday')->sum())->toBe(1);
});

test('the day count always equals the window the label states', function (array $window) {
    $schedule = ($this->agenda)(...$window);

    expect($schedule['days'])->toHaveCount($schedule['horizonDays'])
        ->and($schedule['days'][0]['date'])->toBe($schedule['from'])
        ->and($schedule['days'][array_key_last($schedule['days'])]['date'])->toBe($schedule['to']);
})->with([
    'default' => [[]],
    'narrow' => [['2026-10-10', '2026-10-12']],
    'single day' => [['2026-10-10', '2026-10-10']],
    'at the ceiling' => [[null, '2027-06-01']],
]);

test('overdue work is lifted out of the day buckets into one aggregate block', function () {
    scheduleAsset('2026-08-01');
    scheduleAsset('2026-08-15');
    scheduleAsset('2026-10-05');

    $schedule = ($this->agenda)();

    expect(collect($schedule['groups'][0]['items'])->pluck('dueDate')->all())->toBe(['2026-08-01', '2026-08-15'])
        ->and($schedule['overdueCount'])->toBe(2)
        ->and($schedule['overdueSince'])->toBe('2026-08-01')
        ->and($schedule['totalCount'])->toBe(3)
        ->and(scheduleRows($schedule)->pluck('dueDate')->all())->toBe(['2026-08-01', '2026-08-15', '2026-10-05'])
        ->and(scheduleDayDates($schedule))->not->toContain('2026-08-01');
});

test('no overdue work reports no overdue-since date and an empty block', function () {
    scheduleAsset('2026-10-05');

    $schedule = ($this->agenda)();

    expect($schedule['groups'][0]['items'])->toBe([])
        ->and($schedule['overdueCount'])->toBe(0)
        ->and($schedule['overdueSince'])->toBeNull()
        ->and($schedule['overdueByKind'])->toBe(['asset' => 0, 'certificate' => 0]);
});

test('the overdue block reports how many records of each kind are late', function () {
    $site = Site::factory()->create();
    scheduleAsset('2026-09-01');
    scheduleAsset('2026-09-02');
    scheduleAsset('2026-10-06');
    Certificate::factory()->for($site)->create(['expiry_date' => '2026-09-03']);

    expect(($this->agenda)()['overdueByKind'])->toBe(['asset' => 2, 'certificate' => 1])
        ->and(($this->agenda)(null, null, 'certificate')['overdueByKind'])->toBe(['asset' => 0, 'certificate' => 1])
        ->and(($this->agenda)(null, null, 'asset')['overdueByKind'])->toBe(['asset' => 2, 'certificate' => 0]);
});

test('overdue work is still included when the forward window is narrowed', function () {
    scheduleAsset('2026-08-01');
    scheduleAsset('2026-10-11');

    $schedule = ($this->agenda)('2026-10-10', '2026-10-12');

    expect($schedule['from'])->toBe('2026-10-10')
        ->and($schedule['to'])->toBe('2026-10-12')
        ->and($schedule['horizonDays'])->toBe(3)
        ->and($schedule['overdueCount'])->toBe(1)
        ->and($schedule['overdueSince'])->toBe('2026-08-01')
        ->and(scheduleDayDates($schedule))->toBe(['2026-10-10', '2026-10-11', '2026-10-12'])
        ->and($schedule['days'][1]['itemCount'])->toBe(1);
});

test('the schedule merges asset service dates with certificate expiry dates', function () {
    $site = Site::factory()->create();
    scheduleAsset('2026-10-06');
    Certificate::factory()->for($site)->create(['expiry_date' => '2026-10-07']);

    $schedule = ($this->agenda)();

    expect($schedule['totalCount'])->toBe(2)
        ->and(collect($schedule['groups'])->pluck('items.*.kind')->flatten()->sort()->values()->all())
        ->toBe(['asset', 'certificate']);
});

test('work outside the requested forward window is excluded', function () {
    scheduleAsset('2026-10-05');
    scheduleAsset('2026-10-20');

    $schedule = ($this->agenda)(null, '2026-10-10');

    expect($schedule['totalCount'])->toBe(1)
        ->and($schedule['groups'][1]['items'][0]['dueDate'])->toBe('2026-10-05');
});

test('a horizon beyond the ceiling is clamped rather than rejected', function () {
    $schedule = ($this->agenda)(null, '2027-06-01');

    expect($schedule['to'])->toBe(today()->addDays(BuildScheduleAgenda::MAX_HORIZON_DAYS - 1)->toDateString())
        ->and($schedule['maxHorizonDays'])->toBe(90)
        ->and($schedule['days'])->toHaveCount(90);
});

test('a requested window that ends before it starts collapses instead of inverting', function () {
    $schedule = ($this->agenda)('2026-10-12', '2026-10-08');

    expect($schedule['from'])->toBe('2026-10-12')
        ->and($schedule['to'])->toBe('2026-10-12')
        ->and($schedule['horizonDays'])->toBe(1);
});

test('the type filter narrows the agenda to a single record type', function () {
    $site = Site::factory()->create();
    scheduleAsset('2026-10-06');
    Certificate::factory()->for($site)->create(['expiry_date' => '2026-10-06']);

    expect(($this->agenda)(null, null, 'asset')['groups'][1]['items'][0]['kind'])->toBe('asset')
        ->and(($this->agenda)(null, null, 'certificate')['groups'][1]['items'][0]['kind'])->toBe('certificate')
        ->and(($this->agenda)(null, null, 'asset')['totalCount'])->toBe(1);
});

test('the schedule excludes decommissioned assets and records under an inactive client', function () {
    scheduleAsset('2026-10-06', ['status' => 'decommissioned']);

    $inactiveClient = Client::factory()->create(['is_active' => false]);
    $inactiveSite = Site::factory()->for($inactiveClient)->create(['is_active' => true]);
    Asset::factory()->for($inactiveSite)->for(AssetType::factory())->create(['next_service_due' => '2026-10-06']);
    Certificate::factory()->for($inactiveSite)->create(['expiry_date' => '2026-10-06']);

    $schedule = ($this->agenda)();

    expect($schedule['totalCount'])->toBe(0)
        ->and($schedule['groups'][0]['items'])->toBe([])
        // The window is still reported honestly even when nothing falls in it.
        ->and($schedule['days'])->toHaveCount(15);
});

test('records with no due date never reach the agenda', function () {
    scheduleAsset('2026-10-06');
    scheduleAsset(null);

    expect(($this->agenda)()['totalCount'])->toBe(1);
});

test('days are grouped in ascending date order with today flagged exactly once', function () {
    scheduleAsset('2026-09-20');
    scheduleAsset('2026-10-20');
    scheduleAsset('2026-10-04');
    scheduleAsset('2026-10-18');
    scheduleAsset('2026-10-05');

    $schedule = ($this->agenda)(null, '2026-10-20');
    $populated = collect($schedule['days'])->filter(fn (array $day): bool => $day['itemCount'] > 0);

    expect($populated->pluck('date')->all())->toBe([
        '2026-10-04',
        '2026-10-05',
        '2026-10-18',
        '2026-10-20',
    ])
        ->and($populated->pluck('itemCount')->all())->toBe([1, 1, 1, 1])
        ->and($schedule['days'][0]['isToday'])->toBeTrue()
        ->and(collect($schedule['days'])->where('isToday', true)->count())->toBe(1);
});

test('status comes from the model thresholds so assets and certificates diverge', function () {
    $site = Site::factory()->create();
    scheduleAsset('2026-10-18');
    scheduleAsset('2026-10-19');
    scheduleAsset('2026-11-03');
    Certificate::factory()->for($site)->create(['expiry_date' => '2026-10-18']);
    Certificate::factory()->for($site)->create(['expiry_date' => '2026-10-19']);
    Certificate::factory()->for($site)->create(['expiry_date' => '2026-11-03']);
    Certificate::factory()->for($site)->create(['expiry_date' => '2026-11-04']);

    $rows = scheduleRows(($this->agenda)(null, '2026-11-10'));

    $assetStatus = $rows->where('kind', 'asset')->pluck('status', 'dueDate')->all();
    $certificateStatus = $rows->where('kind', 'certificate')->pluck('status', 'dueDate')->all();

    // Fourteen days out an asset is fine but a certificate is already due soon.
    expect($assetStatus)->toBe([
        '2026-10-18' => 'due_soon',
        '2026-10-19' => 'ok',
        '2026-11-03' => 'ok',
    ])
        ->and($certificateStatus)->toBe([
            '2026-10-18' => 'due_soon',
            '2026-10-19' => 'due_soon',
            '2026-11-03' => 'due_soon',
            '2026-11-04' => 'ok',
        ]);
});

test('overdue items report negative days until and a positive offset in the future', function () {
    scheduleAsset('2026-09-28');
    scheduleAsset('2026-10-09');

    $rows = scheduleRows(($this->agenda)());
    $overdue = $rows->firstWhere('dueDate', '2026-09-28');
    $upcoming = $rows->firstWhere('dueDate', '2026-10-09');

    expect($overdue['daysUntil'])->toBe(-6)
        ->and($overdue['isOverdue'])->toBeTrue()
        ->and($overdue['status'])->toBe('overdue')
        ->and($upcoming['daysUntil'])->toBe(5)
        ->and($upcoming['isOverdue'])->toBeFalse();
});

test('rows deep link to the destination index using validated filters', function () {
    $assetSite = Site::factory()->create();
    $certificateSite = Site::factory()->create();
    scheduleAsset('2026-10-06', ['serial_number' => 'EX-9001', 'site_id' => $assetSite->id]);
    $certificate = Certificate::factory()
        ->for($certificateSite)
        ->create(['expiry_date' => '2026-10-06']);

    $rows = scheduleRows(($this->agenda)());

    expect($rows)->toHaveCount(2);

    $assetRow = $rows->firstWhere('kind', 'asset');
    $certificateRow = $rows->firstWhere('kind', 'certificate');

    expect($assetRow['reference'])->toBe('EX-9001')
        ->and($assetRow['href'])->toBe(route('assets.index', ['search' => 'EX-9001']))
        ->and($certificateRow['reference'])->toBeNull()
        ->and($certificateRow['href'])->toBe(route('certificates.index', [
            'certificate_type_id' => $certificate->certificate_type_id,
            'site_id' => $certificateSite->id,
        ]));
});

test('asset rows carry the location description', function () {
    scheduleAsset('2026-10-06', ['location_desc' => 'Level 2 pump room']);

    $row = scheduleRows(($this->agenda)())->firstWhere('kind', 'asset');

    expect($row['locationDesc'])->toBe('Level 2 pump room');
});

test('long agendas are capped but still report the true total', function () {
    $site = Site::factory()->create();
    $type = AssetType::factory()->create();
    Asset::factory()->for($site)->for($type)->count(65)->create(['next_service_due' => '2026-10-08']);

    $schedule = ($this->agenda)();

    expect($schedule['totalCount'])->toBe(65)
        ->and($schedule['returnedCount'])->toBe(BuildScheduleAgenda::MAX_ITEMS)
        ->and($schedule['truncated'])->toBeTrue()
        ->and($schedule['days'][4]['itemCount'])->toBe(65)
        ->and(collect($schedule['days'])->pluck('itemCount')->sum())->toBe(65);
});

test('the overdue count stays true when the overdue rows alone are capped', function () {
    $site = Site::factory()->create();
    $type = AssetType::factory()->create();
    $certificateType = CertificateType::factory()->create();

    // 70 assets and 5 certificates, every one of them late, so the overdue
    // block on its own overruns the cap.
    Asset::factory()->for($site)->for($type)->count(70)->create(['next_service_due' => '2026-09-01']);
    Certificate::factory()->for($site)->for($certificateType)->count(5)->create(['expiry_date' => '2026-09-02']);

    $schedule = ($this->agenda)();

    expect($schedule['overdueCount'])->toBe(75)
        ->and($schedule['overdueByKind'])->toBe(['asset' => 70, 'certificate' => 5])
        ->and($schedule['totalCount'])->toBe(75)
        ->and($schedule['returnedCount'])->toBe(BuildScheduleAgenda::MAX_ITEMS)
        ->and($schedule['truncated'])->toBeTrue()
        ->and(count($schedule['groups'][0]['items']))->toBe(BuildScheduleAgenda::MAX_ITEMS)
        // The oldest late record is still reachable inside the cap, so the
        // header can say how far back the backlog reaches.
        ->and($schedule['overdueSince'])->toBe('2026-09-01');
});

test('the dashboard exposes the schedule and validates its filters', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->component('dashboard')
            ->where('schedule.type', 'all')
            ->where('schedule.overdueCount', 0)
            ->where('schedule.totalCount', 0)
            ->where('schedule.overdueSince', null)
            ->has('schedule.groups.0.items', 0)
            ->has('schedule.days', 15)
            ->has('schedule.maxHorizonDays'));

    $this->actingAs(User::factory()->create())
        ->get(route('dashboard', ['schedule_type' => 'bogus']))
        ->assertSessionHasErrors('schedule_type');

    $this->actingAs(User::factory()->create())
        ->get(route('dashboard', ['schedule_from' => '04-10-2026']))
        ->assertSessionHasErrors('schedule_from');

    $this->actingAs(User::factory()->create())
        ->get(route('dashboard', ['schedule_from' => '2026-10-20', 'schedule_to' => '2026-10-01']))
        ->assertSessionHasErrors('schedule_to');
});

test('the schedule type filter reaches the dashboard prop', function () {
    $site = Site::factory()->create();
    scheduleAsset('2026-10-06');
    Certificate::factory()->for($site)->create(['expiry_date' => '2026-10-06']);

    $this->actingAs(User::factory()->create())
        ->get(route('dashboard', ['schedule_type' => 'certificate']))
        ->assertInertia(fn (Assert $page) => $page->component('dashboard')
            ->where('schedule.type', 'certificate')
            ->where('schedule.totalCount', 1)
            ->where('schedule.groups.1.items.0.kind', 'certificate'));
});

test('all three agenda groups are always present in a fixed order', function () {
    scheduleAsset('2026-10-06');

    $groups = ($this->agenda)()['groups'];

    // The empty groups are present with an empty item list rather than missing,
    // so the view and the tests can index by position without first checking
    // that a group happens to hold something.
    expect(collect($groups)->pluck('id')->all())->toBe(['overdue', 'next', 'later'])
        ->and(collect($groups)->pluck('itemCount')->all())->toBe([0, 1, 0])
        ->and($groups[0]['items'])->toBe([])
        ->and($groups[0]['firstDate'])->toBeNull()
        ->and($groups[1]['firstDate'])->toBe('2026-10-06');
});

test('the agenda next group shares the two-week KPI boundary', function () {
    $schedule = ($this->agenda)();

    expect($schedule['groupBoundaryDays'])
        ->toBe(BuildScheduleAgenda::horizonPresets()[BuildScheduleAgenda::NEXT_GROUP_PRESET])
        ->and(BuildDashboardKpis::DUE_SOON_PRESET)->toBe('14d')
        ->and($schedule['groups'][1]['label'])->toBe('Next 14 days');
});

test('rows are split into overdue, next and later by their distance from today', function (int $offset, string $group, int $index) {
    scheduleAsset(today()->addDays($offset)->toDateString());

    $groups = ($this->agenda)(null, null, 'all', '30d')['groups'];

    expect($groups[$index]['id'])->toBe($group)
        ->and($groups[$index]['itemCount'])->toBe(1);
})->with([
    // The boundary is inclusive at both ends: today + 14 is still "next", and
    // only today + 15 falls out of it.
    'yesterday is overdue' => [-1, 'overdue', 0],
    'today is next' => [0, 'next', 1],
    'day seven is next' => [7, 'next', 1],
    'day fourteen is still next' => [14, 'next', 1],
    'day fifteen is later' => [15, 'later', 2],
]);

test('each group reports its own kind breakdown and first date', function () {
    scheduleAsset('2026-09-20');
    scheduleAsset('2026-10-05');
    $site = Site::factory()->create();
    Certificate::factory()->for($site)->create(['expiry_date' => '2026-10-06']);

    $groups = ($this->agenda)()['groups'];

    expect($groups[0]['byKind'])->toBe(['asset' => 1, 'certificate' => 0])
        ->and($groups[0]['firstDate'])->toBe('2026-09-20')
        ->and($groups[1]['byKind'])->toBe(['asset' => 1, 'certificate' => 1])
        ->and($groups[1]['firstDate'])->toBe('2026-10-05')
        ->and($groups[2]['itemCount'])->toBe(0);
});

test('overdue mode include is the default and keeps both sides of the queue', function () {
    scheduleAsset('2026-09-20');
    scheduleAsset('2026-10-06');

    $schedule = ($this->agenda)();

    expect($schedule['overdueMode'])->toBe('include')
        ->and($schedule['windowHidden'])->toBeFalse()
        ->and($schedule['totalCount'])->toBe(2)
        ->and($schedule['groups'][0]['itemCount'])->toBe(1)
        ->and($schedule['groups'][1]['itemCount'])->toBe(1);
});

test('overdue mode only answers the overdue question and suppresses the window', function () {
    scheduleAsset('2026-09-20');
    scheduleAsset('2026-09-25');
    scheduleAsset('2026-10-06');

    $schedule = ($this->agenda)(null, null, 'all', null, 'only');

    expect($schedule['overdueMode'])->toBe('only')
        // There is no forward window to show, so the strip and the range control
        // are suppressed rather than rendered as something inert.
        ->and($schedule['windowHidden'])->toBeTrue()
        ->and($schedule['days'])->toBe([])
        ->and($schedule['nextAfter'])->toBeNull()
        ->and($schedule['totalCount'])->toBe(2)
        ->and($schedule['groups'][0]['itemCount'])->toBe(2)
        ->and($schedule['groups'][1]['itemCount'])->toBe(0)
        ->and($schedule['groups'][2]['itemCount'])->toBe(0)
        // The overdue group still reports its own shape, which is the whole
        // point of asking for it in isolation.
        ->and($schedule['groups'][0]['byKind'])->toBe(['asset' => 2, 'certificate' => 0]);
});

test('overdue mode exclude drops the overdue rows without touching the window', function () {
    scheduleAsset('2026-09-20');
    scheduleAsset('2026-10-06');

    $schedule = ($this->agenda)(null, null, 'all', null, 'exclude');

    expect($schedule['overdueMode'])->toBe('exclude')
        ->and($schedule['windowHidden'])->toBeFalse()
        ->and($schedule['totalCount'])->toBe(1)
        ->and($schedule['groups'][0]['itemCount'])->toBe(0)
        ->and($schedule['groups'][1]['itemCount'])->toBe(1)
        // The strip still covers the window, and a cell can no longer report
        // work that the mode has hidden.
        ->and($schedule['days'])->toHaveCount(15)
        ->and(collect($schedule['days'])->sum('itemCount'))->toBe(1);
});

test('an unrecognised overdue mode falls back to the default', function () {
    scheduleAsset('2026-09-20');

    expect(($this->agenda)(null, null, 'all', null, 'sideways')['overdueMode'])->toBe('include');
});

test('a selected day narrows every group at once', function () {
    scheduleAsset('2026-09-20');
    scheduleAsset('2026-10-06');
    scheduleAsset('2026-10-07');
    $site = Site::factory()->create();
    Certificate::factory()->for($site)->create(['expiry_date' => '2026-10-06']);

    $schedule = ($this->agenda)(null, null, 'all', null, 'include', '2026-10-06');

    expect($schedule['day'])->toBe('2026-10-06')
        ->and($schedule['totalCount'])->toBe(2)
        ->and($schedule['groups'][0]['itemCount'])->toBe(0)
        ->and($schedule['groups'][1]['itemCount'])->toBe(2)
        ->and($schedule['groups'][2]['itemCount'])->toBe(0)
        // The window itself is unchanged, so the strip still shows every day and
        // can offer another one without a fresh window request.
        ->and($schedule['days'])->toHaveCount(15);
});

test('a selected day with no work is an empty agenda rather than an error', function () {
    scheduleAsset('2026-10-06');

    $schedule = ($this->agenda)(null, null, 'all', null, 'include', '2026-10-08');

    expect($schedule['day'])->toBe('2026-10-08')
        ->and($schedule['totalCount'])->toBe(0)
        ->and(collect($schedule['groups'])->sum('itemCount'))->toBe(0);
});

test('a malformed selected day is ignored rather than treated as a filter', function () {
    scheduleAsset('2026-10-06');

    expect(($this->agenda)(null, null, 'all', null, 'include', '06-10-2026')['day'])->toBeNull();
});

test('type counts reflect the other dimensions while one type is selected', function () {
    $site = Site::factory()->create();
    scheduleAsset('2026-09-20');
    scheduleAsset('2026-10-06');
    Certificate::factory()->for($site)->create(['expiry_date' => '2026-09-25']);
    Certificate::factory()->for($site)->create(['expiry_date' => '2026-10-07']);

    $counts = ($this->agenda)(null, null, 'asset')['counts'];

    // The certificate tab has to keep telling the truth while the asset tab is
    // the selected one, so a count is resolved against every dimension except
    // the one it describes.
    expect($counts)->toBe(['all' => 4, 'asset' => 2, 'certificate' => 2]);
});

test('type counts follow the overdue mode and the selected day too', function () {
    $site = Site::factory()->create();
    scheduleAsset('2026-09-20');
    scheduleAsset('2026-10-06');
    Certificate::factory()->for($site)->create(['expiry_date' => '2026-09-25']);
    Certificate::factory()->for($site)->create(['expiry_date' => '2026-10-07']);

    expect(($this->agenda)(null, null, 'all', null, 'exclude')['counts'])
        ->toBe(['all' => 2, 'asset' => 1, 'certificate' => 1])
        ->and(($this->agenda)(null, null, 'all', null, 'include', '2026-10-06')['counts'])
        ->toBe(['all' => 1, 'asset' => 1, 'certificate' => 0]);
});

test('the window reports the soonest work it is leaving out', function () {
    scheduleAsset('2026-10-06');
    scheduleAsset('2026-11-20');

    $next = ($this->agenda)()['nextAfter'];

    expect($next['date'])->toBe('2026-11-20')
        ->and($next['daysUntil'])->toBe(47);
});

test('the soonest work beyond the window respects the selected type', function () {
    scheduleAsset('2026-11-20');
    $site = Site::factory()->create();
    Certificate::factory()->for($site)->create(['expiry_date' => '2026-10-25']);

    expect(($this->agenda)(null, null, 'certificate')['nextAfter']['date'])->toBe('2026-10-25')
        ->and(($this->agenda)(null, null, 'asset')['nextAfter']['date'])->toBe('2026-11-20');
});

test('there is nothing beyond the window to report', function () {
    scheduleAsset('2026-10-06');

    expect(($this->agenda)()['nextAfter'])->toBeNull();
});

test('the default window crosses a month boundary without losing a day', function () {
    $this->travelTo(now()->setDate(2026, 10, 28)->startOfDay());

    $schedule = ($this->agenda)();

    expect($schedule['from'])->toBe('2026-10-28')
        ->and($schedule['to'])->toBe('2026-11-11')
        ->and($schedule['horizonDays'])->toBe(15)
        ->and($schedule['days'])->toHaveCount(15)
        ->and($schedule['days'][0]['date'])->toBe('2026-10-28')
        ->and($schedule['days'][3]['date'])->toBe('2026-10-31')
        ->and($schedule['days'][4]['date'])->toBe('2026-11-01')
        ->and($schedule['days'][14]['date'])->toBe('2026-11-11')
        // Only the as-of date is today, even though the window opens on it.
        ->and(collect($schedule['days'])->where('isToday', true)->pluck('date')->all())
        ->toBe(['2026-10-28']);
});

test('a shorter horizon reports fewer strip cells than the default', function (string $preset, int $cells, string $last) {
    expect(($this->agenda)(null, null, 'all', $preset)['days'])
        ->toHaveCount($cells)
        ->and(($this->agenda)(null, null, 'all', $preset)['to'])->toBe($last);
})->with([
    // Presets are inclusive offsets, so 7 is eight cells and the strip can never
    // be shorter than the number of days the range claims to cover.
    'seven days' => ['7d', 8, '2026-10-11'],
    'fourteen days' => ['14d', 15, '2026-10-18'],
    'thirty days' => ['30d', 31, '2026-11-03'],
]);

test('the default window is the fourteen day preset', function () {
    expect(($this->agenda)()['horizonPreset'])->toBe('14d')
        ->and(($this->agenda)()['from'])->toBe(($this->agenda)(null, null, 'all', '14d')['from'])
        ->and(($this->agenda)()['to'])->toBe(($this->agenda)(null, null, 'all', '14d')['to']);
});

test('exclude mode still counts the overdue backlog it is hiding', function () {
    scheduleAsset('2026-09-01');
    scheduleAsset('2026-10-06');

    $schedule = ($this->agenda)(overdue: 'exclude');

    // The backlog is unreachable from the list, so the offer to show it has to
    // know it is there. Counting the filtered list would report zero and the
    // count on the Overdue tile could never be recovered from this page.
    expect($schedule['overdueCount'])->toBe(1)
        ->and($schedule['overdueSince'])->toBe('2026-09-01')
        ->and($schedule['overdueByKind'])->toBe(['asset' => 1, 'certificate' => 0])
        ->and($schedule['windowItemCount'])->toBe(1)
        ->and($schedule['totalCount'])->toBe(1);
});

test('overdue-only mode still counts the window it is hiding', function () {
    scheduleAsset('2026-09-01');
    scheduleAsset('2026-10-06');

    $schedule = ($this->agenda)(overdue: 'only');

    // Symmetrically: "show everything due in this range" needs the window count,
    // and the Due soon card reads from it.
    expect($schedule['windowItemCount'])->toBe(1)
        ->and($schedule['totalCount'])->toBe(1)
        ->and($schedule['days'])->toBe([])
        ->and($schedule['windowHidden'])->toBeTrue()
        ->and($schedule['overdueCount'])->toBe(1);
});

test('the overdue count ignores the day, the window count does not', function () {
    scheduleAsset('2026-09-01');
    scheduleAsset('2026-10-06');
    scheduleAsset('2026-10-07');

    $wide = ($this->agenda)(overdue: 'exclude');
    $narrow = ($this->agenda)(overdue: 'exclude', day: '2026-10-06');

    // The two halves answer different questions, so they are scoped differently.
    //
    // windowItemCount describes the rows on screen and the denominator for
    // "showing X of Y", so narrowing to a day correctly reduces it to that day.
    //
    // overdueCount describes what the offer can reveal. A strip cell is always an
    // in-window day, so no overdue record can sit on the selected day: counting
    // the day would report a backlog of zero and the offer would never appear.
    // The offer clears the day as it switches, so a backlog total is what it needs.
    expect($narrow['windowItemCount'])->toBe(1)
        ->and($wide['windowItemCount'])->toBe(2)
        ->and($narrow['overdueCount'])->toBe(1)
        ->and($wide['overdueCount'])->toBe(1)
        ->and($narrow['totalCount'])->toBe(1);
});
