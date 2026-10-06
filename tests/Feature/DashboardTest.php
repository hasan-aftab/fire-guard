<?php

use App\Models\Asset;
use App\Models\AssetType;
use App\Models\Certificate;
use App\Models\Client;
use App\Models\Inspection;
use App\Models\Site;
use App\Models\Technician;
use App\Models\User;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertInertia(fn (Assert $page) => $page->component('dashboard')
        ->where('summary.stats.activeClients', 0)
        ->where('summary.stats.totalAssets', 0)
        ->where('schedule.totalCount', 0)
        ->has('schedule.groups.0.items', 0)
        // Even an empty dashboard gets one bucket per day of the window.
        ->has('schedule.days', 15)
        ->where('schedule.days.0.itemCount', 0));
});

test('unverified users are redirected before dashboard data is shown', function () {
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)->get(route('dashboard'))
        ->assertRedirect(route('verification.notice'));
});

test('the overdue and expiring KPIs use the model thresholds', function () {
    $this->travelTo(now()->setDate(2026, 10, 1)->startOfDay());
    $site = Site::factory()->create();
    $type = AssetType::factory()->create();
    $assetBoundary = Asset::factory()->for($site)->for($type)->create(['next_service_due' => '2026-10-15']);
    Asset::factory()->for($site)->for($type)->create(['next_service_due' => '2026-10-16']);
    Asset::factory()->for($site)->for($type)->create(['next_service_due' => '2026-09-30', 'status' => 'decommissioned']);
    $certificateBoundary = Certificate::factory()->for($site)->create(['issue_date' => '2026-01-01', 'expiry_date' => '2026-10-31']);
    Certificate::factory()->for($site)->create(['issue_date' => '2026-01-01', 'expiry_date' => '2026-11-01']);

    $this->actingAs(User::factory()->create())->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->component('dashboard')
            ->where('kpis.overdue.total', 0)
            ->where('kpis.certificates.expiringSoon', 1)
            // The boundary asset is due on the last day of the window; the
            // certificate at thirty days falls outside it.
            ->where('schedule.totalCount', 1)
            ->where('schedule.days.14.date', '2026-10-15')
            ->where('schedule.groups.2.items.0.entityId', $assetBoundary->id)
            ->where('schedule.groups.2.items.0.status', 'due_soon'));
    expect($certificateBoundary->exists)->toBeTrue();
});

test('the expiring certificate KPI counts certificates that already expired', function () {
    $this->travelTo(now()->setDate(2026, 9, 29)->startOfDay());
    $site = Site::factory()->create();
    $expired = Certificate::factory()->for($site)->create(['expiry_date' => '2026-09-28']);
    $dueSoon = Certificate::factory()->for($site)->create(['expiry_date' => '2026-10-10']);
    Certificate::factory()->for($site)->create(['expiry_date' => '2026-10-10', 'entered_in_error_at' => now()]);

    $this->actingAs(User::factory()->create())->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->component('dashboard')
            ->where('kpis.certificates.total', 2)
            // Both are also lifted into the overdue block on the agenda.
            ->where('schedule.overdueCount', 1)
            ->where('schedule.groups.0.items.0.entityId', $expired->id));
    expect($dueSoon->exists)->toBeTrue();
});

test('the schedule groups only actionable asset due dates inside the default horizon', function () {
    $this->travelTo(now()->setDate(2026, 9, 29)->startOfDay());
    $user = User::factory()->create();
    $site = Site::factory()->create();
    $type = AssetType::factory()->create();
    Asset::factory()->for($site)->for($type)->create(['next_service_due' => '2026-10-02']);
    Inspection::factory()->for($asset = Asset::factory()->for($site)->for($type)->create(['next_service_due' => '2026-10-03']))->create(['next_due_date' => '2026-10-03']);
    Asset::factory()->for($site)->for($type)->count(10)->create(['next_service_due' => '2026-10-01']);
    Asset::factory()->for($site)->for($type)->create(['next_service_due' => '2027-01-01']);

    $this->actingAs($user)->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->component('dashboard')
            ->where('schedule.from', '2026-09-29')
            ->where('schedule.to', '2026-10-13')
            ->where('schedule.totalCount', 12)
            ->where('schedule.overdueCount', 0)
            ->has('schedule.days', 15)
            ->where('schedule.days.0.date', '2026-09-29')
            ->where('schedule.days.0.itemCount', 0)
            ->where('schedule.days.2.date', '2026-10-01')
            ->where('schedule.days.2.itemCount', 10)
            ->where('schedule.days.3.date', '2026-10-02')
            ->where('schedule.days.3.itemCount', 1)
            ->where('schedule.days.4.date', '2026-10-03')
            ->where('schedule.days.4.itemCount', 1));
    expect($asset->exists)->toBeTrue();
});

test('a newly logged inspection due date appears on the dashboard schedule', function () {
    $this->travelTo(now()->setDate(2026, 9, 29)->startOfDay());
    $user = User::factory()->create();
    $asset = Asset::factory()->create(['next_service_due' => null]);
    $technician = Technician::factory()->create();

    $this->actingAs($user)->post(route('inspections.store'), [
        'asset_id' => $asset->id,
        'technician_id' => $technician->id,
        'inspection_date' => '2026-09-29',
        'result' => 'pass',
        'next_due_date' => '2026-10-15',
    ])->assertSessionHasNoErrors();

    $this->get(route('dashboard', ['schedule_to' => '2026-10-20']))
        ->assertInertia(fn (Assert $page) => $page->component('dashboard')
            ->where('schedule.to', '2026-10-20')
            ->where('schedule.totalCount', 1)
            ->where('schedule.days.16.date', '2026-10-15')
            ->where('schedule.days.16.itemCount', 1)
            ->where('schedule.groups.2.items.0.dueDate', '2026-10-15')
            ->where('schedule.groups.2.items.0.kind', 'asset'));
});

test('the schedule filters carry through to the dashboard prop', function () {
    $this->travelTo(now()->setDate(2026, 9, 29)->startOfDay());
    $user = User::factory()->create();
    $site = Site::factory()->create();
    $type = AssetType::factory()->create();
    Asset::factory()->for($site)->for($type)->create(['next_service_due' => '2026-09-28']);
    Asset::factory()->for($site)->for($type)->create(['next_service_due' => '2026-10-10']);
    Asset::factory()->for($site)->for($type)->create(['next_service_due' => '2026-10-30']);
    Asset::factory()->for($site)->for($type)->create(['next_service_due' => '2026-10-10', 'status' => 'decommissioned']);

    $this->actingAs($user)->get(route('dashboard', ['schedule_from' => '2026-10-01']))
        ->assertInertia(fn (Assert $page) => $page->component('dashboard')
            ->where('kpis.overdue.total', 1)
            ->where('filters.schedule_from', '2026-10-01'));
});

test('sidebar counts and dashboard stats share one eligibility rule', function () {
    $this->travelTo(now()->setDate(2026, 10, 1)->startOfDay());
    $user = User::factory()->create();
    $type = AssetType::factory()->create();

    $client = Client::factory()->create();
    $site = Site::factory()->for($client)->create();
    $live = Asset::factory()->for($site)->for($type)->create(['next_service_due' => '2026-10-05']);
    $technician = Technician::factory()->create();
    Inspection::factory()->for($live)->for($technician)->create(['result' => 'pass']);

    $erroredClient = Client::factory()->create(['entered_in_error_at' => now()]);
    $erroredSite = Site::factory()->for($erroredClient)->create(['entered_in_error_at' => now()]);
    Inspection::factory()->for(Asset::factory()->for($erroredSite)->for($type)->create(['next_service_due' => '2026-10-05']))
        ->for($technician)->create(['result' => 'fail']);

    Asset::factory()->for($site)->for($type)->create(['status' => 'decommissioned']);
    $inactiveSite = Site::factory()->for($client)->create(['is_active' => false]);
    Asset::factory()->for($inactiveSite)->for($type)->create(['next_service_due' => '2026-10-05']);
    $selfErrored = Asset::factory()->for($site)->for($type)->create(['entered_in_error_at' => now()]);
    Inspection::factory()->for($selfErrored)->for($technician)->create(['result' => 'fail']);

    // Only the one working technician is counted; the other two are retired or
    // were struck from the record.
    Technician::factory()->create(['is_active' => false]);
    Technician::factory()->create(['entered_in_error_at' => now()]);

    $this->actingAs($user)->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
        ->where('counts.clients', 1)
        ->where('counts.sites', 1)
        ->where('counts.assets', 1)
        ->where('counts.inspections', 1)
        ->where('counts.technicians', 1)
        ->where('summary.stats.activeClients', 1)
        ->where('summary.stats.totalAssets', 1)
        ->where('kpis.passRate.total', 1)
        ->where('kpis.overdue.total', 0)
        // The standing counts still link to the list views they summarise.
        ->where('summary.links.activeClients', route('clients.index'))
        ->where('summary.links.totalAssets', route('assets.index'))
        ->where('schedule.totalCount', 1)
        ->where('schedule.days.4.date', '2026-10-05')
        ->where('schedule.groups.1.items.0.entityId', $live->id));
});

test('the compliance gauge counts only the last twelve months', function () {
    $this->travelTo(now()->setDate(2026, 10, 1)->startOfDay());
    $inside = Inspection::factory()->create(['inspection_date' => '2026-09-01', 'result' => 'pass']);
    $onBoundary = Inspection::factory()->create(['inspection_date' => '2025-10-01', 'result' => 'fail']);
    Inspection::factory()->create(['inspection_date' => '2025-09-30', 'result' => 'fail']);
    Inspection::factory()->create(['inspection_date' => '2025-06-01', 'result' => 'fail', 'entered_in_error_at' => now()]);

    $this->actingAs(User::factory()->create())->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->component('dashboard')
            ->where('summary.complianceMonths', 12)
            ->where('summary.complianceSince', '2025-10-01')
            ->where('kpis.passRate.total', 2)
            ->where('kpis.passRate.pass', 1)
            ->where('kpis.passRate.fail', 1));
    expect($inside->exists && $onBoundary->exists)->toBeTrue();
});

test('dashboard rejects invalid schedule ranges and record types', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->from(route('dashboard'))->get(route('dashboard', [
        'schedule_from' => '2026-10-20',
        'schedule_to' => '2026-10-01',
        'schedule_type' => 'inspections',
    ]))->assertRedirect(route('dashboard'))
        ->assertSessionHasErrors(['schedule_to', 'schedule_type']);
});

test('the dashboard ships four KPIs with the scope each one counts', function () {
    $this->actingAs(User::factory()->create())->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->component('dashboard')
            ->has('kpis.overdue')
            ->has('kpis.overdue.byKind')
            ->where('kpis.dueSoon.horizonPreset', '7d')
            ->where('kpis.dueSoon.horizonDays', 8)
            ->where('kpis.certificates.horizonPreset', '30d')
            ->where('kpis.passRate.percent', 0)
            ->where('kpis.passRate.band', 'danger'));
});

test('the overdue KPI counts both kinds, not assets alone', function () {
    $this->travelTo(now()->setDate(2026, 10, 4)->startOfDay());
    $site = Site::factory()->create();
    $type = AssetType::factory()->create();
    Asset::factory()->for($site)->for($type)->create(['next_service_due' => '2026-10-01']);
    Asset::factory()->for($site)->for($type)->create(['next_service_due' => '2026-10-02']);
    Certificate::factory()->for($site)->create(['expiry_date' => '2026-10-03']);
    // Already inside the seven-day window, so it is not overdue.
    Certificate::factory()->for($site)->create(['expiry_date' => '2026-10-05']);

    $this->actingAs(User::factory()->create())->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->component('dashboard')
            ->where('kpis.overdue.total', 3)
            ->where('kpis.overdue.byKind.asset', 2)
            ->where('kpis.overdue.byKind.certificate', 1));
});

test('the due soon KPI counts the next seven days inclusively and excludes overdue work', function () {
    $this->travelTo(now()->setDate(2026, 10, 4)->startOfDay());
    $site = Site::factory()->create();
    $type = AssetType::factory()->create();

    // Boundaries of the window the card names: today and today plus seven.
    Asset::factory()->for($site)->for($type)->create(['next_service_due' => '2026-10-04']);
    Asset::factory()->for($site)->for($type)->create(['next_service_due' => '2026-10-11']);
    Certificate::factory()->for($site)->create(['expiry_date' => '2026-10-05']);
    // Just outside on both sides.
    Asset::factory()->for($site)->for($type)->create(['next_service_due' => '2026-10-03']);
    Asset::factory()->for($site)->for($type)->create(['next_service_due' => '2026-10-12']);

    $this->actingAs(User::factory()->create())->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->component('dashboard')
            ->where('kpis.dueSoon.total', 3));
});

test('the certificate KPI splits expired work from what is still to expire', function () {
    $this->travelTo(now()->setDate(2026, 10, 4)->startOfDay());
    $site = Site::factory()->create();

    Certificate::factory()->for($site)->create(['expiry_date' => '2026-10-03']);
    Certificate::factory()->for($site)->create(['expiry_date' => '2026-10-03']);
    Certificate::factory()->for($site)->create(['expiry_date' => '2026-10-04']);
    Certificate::factory()->for($site)->create(['expiry_date' => '2026-11-03']);
    // Beyond the thirty-day window, so it is not a near-term certificate.
    Certificate::factory()->for($site)->create(['expiry_date' => '2026-11-04']);

    $this->actingAs(User::factory()->create())->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->component('dashboard')
            ->where('kpis.certificates.expired', 2)
            ->where('kpis.certificates.expiringSoon', 2)
            ->where('kpis.certificates.total', 4));
});

test('each KPI card counts exactly the rows its Schedule scope shows', function () {
    $this->travelTo(now()->setDate(2026, 10, 4)->startOfDay());
    $site = Site::factory()->create();
    $type = AssetType::factory()->create();

    Asset::factory()->for($site)->for($type)->create(['next_service_due' => '2026-10-01']);
    Certificate::factory()->for($site)->create(['expiry_date' => '2026-10-02']);
    Asset::factory()->for($site)->for($type)->create(['next_service_due' => '2026-10-05']);
    Asset::factory()->for($site)->for($type)->create(['next_service_due' => '2026-10-11']);
    Certificate::factory()->for($site)->create(['expiry_date' => '2026-10-20']);
    Certificate::factory()->for($site)->create(['expiry_date' => '2026-11-04']);

    $user = User::factory()->create();

    // Overdue ignores the window, so the default and the seven-day preset must
    // report the same late work.
    $this->actingAs($user)->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->component('dashboard')
            ->where('kpis.overdue.total', 2)
            ->where('schedule.overdueCount', 2)
            ->where('schedule.truncated', false));

    // The due-soon card must equal the window rows of the schedule it selects.
    $this->actingAs($user)->get(route('dashboard', ['schedule_horizon' => '7d']))
        ->assertInertia(fn (Assert $page) => $page->component('dashboard')
            ->where('kpis.dueSoon.total', 2)
            ->where('schedule.windowItemCount', 2)
            ->where('schedule.truncated', false));

    // The certificate card must equal the certificate rows of its own scope.
    $this->actingAs($user)->get(route('dashboard', [
        'schedule_horizon' => '30d',
        'schedule_type' => 'certificate',
    ]))->assertInertia(fn (Assert $page) => $page->component('dashboard')
        ->where('kpis.certificates.expired', 1)
        ->where('kpis.certificates.expiringSoon', 1)
        ->where('kpis.certificates.total', 2)
        ->where('schedule.overdueCount', 1)
        ->where('schedule.windowItemCount', 1)
        ->where('schedule.totalCount', 2)
        ->where('schedule.truncated', false));
});

test('the pass rate bands on named thresholds', function (int $pass, int $fail, int $expected, string $band) {
    $this->travelTo(now()->setDate(2026, 10, 4)->startOfDay());

    for ($index = 0; $index < $pass; $index++) {
        Inspection::factory()->create(['inspection_date' => '2026-10-01', 'result' => 'pass']);
    }

    for ($index = 0; $index < $fail; $index++) {
        Inspection::factory()->create(['inspection_date' => '2026-10-01', 'result' => 'fail']);
    }
    $this->actingAs(User::factory()->create())->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->component('dashboard')
            ->where('kpis.passRate.percent', $expected)
            ->where('kpis.passRate.band', $band)
            ->where('kpis.passRate.total', $pass + $fail));
})->with([
    'everything passes' => [5, 0, 100, 'success'],
    'exactly at the success threshold' => [4, 1, 80, 'success'],
    'just under the success threshold' => [79, 21, 79, 'warning'],
    'exactly at the warning threshold' => [3, 2, 60, 'warning'],
    'just under the warning threshold' => [59, 41, 59, 'danger'],
    'nothing passes' => [0, 3, 0, 'danger'],
]);

test('the pass rate ignores inspections older than the compliance window', function () {
    $this->travelTo(now()->setDate(2026, 10, 4)->startOfDay());
    Inspection::factory()->create(['inspection_date' => '2026-10-01', 'result' => 'pass']);
    Inspection::factory()->create(['inspection_date' => '2025-09-01', 'result' => 'fail']);

    $this->actingAs(User::factory()->create())->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->component('dashboard')
            ->where('kpis.passRate.total', 1)
            ->where('kpis.passRate.pass', 1)
            ->where('kpis.passRate.percent', 100)
            ->where('kpis.passRate.band', 'success'));
});

test('the dashboard rejects a horizon that is not a shipped preset', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->from(route('dashboard'))
        ->get(route('dashboard', ['schedule_horizon' => '90d']))
        ->assertRedirect(route('dashboard'))
        ->assertSessionHasErrors('schedule_horizon');
});

test('a preset horizon reaches the schedule through the query string', function () {
    $this->travelTo(now()->setDate(2026, 10, 4)->startOfDay());

    $this->actingAs(User::factory()->create())
        ->get(route('dashboard', ['schedule_horizon' => '30d']))
        ->assertInertia(fn (Assert $page) => $page->component('dashboard')
            ->where('schedule.from', '2026-10-04')
            ->where('schedule.to', '2026-11-03')
            ->where('schedule.horizonPreset', '30d')
            ->has('schedule.days', 31));
});

test('the schedule state is driven entirely by the query string', function () {
    $this->travelTo(now()->setDate(2026, 10, 4)->startOfDay());
    $site = Site::factory()->create();
    Asset::factory()->for($site)->create(['next_service_due' => '2026-09-20']);
    Asset::factory()->for($site)->create(['next_service_due' => '2026-10-06']);
    Certificate::factory()->for($site)->create(['expiry_date' => '2026-10-07']);
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('dashboard', [
        'schedule_type' => 'certificate',
        'schedule_horizon' => '7d',
        'schedule_overdue' => 'exclude',
        'schedule_day' => '2026-10-07',
    ]))->assertInertia(fn (Assert $page) => $page->component('dashboard')
        ->where('schedule.type', 'certificate')
        ->where('schedule.horizonPreset', '7d')
        ->where('schedule.overdueMode', 'exclude')
        ->where('schedule.day', '2026-10-07')
        ->where('schedule.horizonDays', 8)
        ->where('schedule.totalCount', 1)
        ->where('schedule.groups.1.items.0.kind', 'certificate')
        // The filters are echoed back so the frontend can rebuild the same GETs
        // without keeping a second copy of the state.
        ->where('filters.schedule_type', 'certificate')
        ->where('filters.schedule_horizon', '7d')
        ->where('filters.schedule_overdue', 'exclude')
        ->where('filters.schedule_day', '2026-10-07'));
});

test('the schedule state rejects values it cannot act on', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('dashboard', ['schedule_overdue' => 'sideways']))
        ->assertSessionHasErrors('schedule_overdue');

    $this->actingAs($user)->get(route('dashboard', ['schedule_day' => '07-10-2026']))
        ->assertSessionHasErrors('schedule_day');
});

test('the page is given the timezone its dates were resolved in', function () {
    // An instant where the organisation's calendar date and UTC's differ. If the
    // page were derived from a browser clock it would show the UTC date and
    // disagree with every count on the page.
    $this->travelTo(CarbonImmutable::parse('2026-10-03 20:00', 'UTC'));

    $this->actingAs(User::factory()->create())->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->component('dashboard')
            ->where('timezone', 'Pacific/Port_Moresby')
            ->where('today', '2026-10-04'));
});

test('each attention tile counts exactly the rows its own preset shows', function (array $query, string $kpi, int $expected) {
    $this->travelTo(now()->setDate(2026, 10, 4)->startOfDay());
    $site = Site::factory()->create();
    Asset::factory()->for($site)->create(['next_service_due' => '2026-09-20']);
    Certificate::factory()->for($site)->create(['expiry_date' => '2026-09-25']);
    Asset::factory()->for($site)->create(['next_service_due' => '2026-10-07']);
    Asset::factory()->for($site)->create(['next_service_due' => '2026-10-14']);
    Certificate::factory()->for($site)->create(['expiry_date' => '2026-10-24']);
    Certificate::factory()->for($site)->create(['expiry_date' => '2026-11-13']);
    Asset::factory()->for($site)->create(['next_service_due' => '2026-12-03']);

    $this->actingAs(User::factory()->create())->get(route('dashboard', $query))
        ->assertInertia(fn (Assert $page) => $page->component('dashboard')
            // The tile's headline number and the list it opens are asserted
            // against the same literal, so a drift in either one fails here
            // rather than showing as a tile that disagrees with its own list.
            ->where($kpi, $expected)
            ->where('schedule.totalCount', $expected));
})->with([
    'overdue ignores the window entirely' => [
        ['schedule_overdue' => 'only'],
        'kpis.overdue.total',
        2,
    ],
    'due in seven days counts the forward window without the overdue rows' => [
        ['schedule_horizon' => '7d', 'schedule_overdue' => 'exclude'],
        'kpis.dueSoon.total',
        1,
    ],
    'certificates count expiry plus already expired' => [
        ['schedule_type' => 'certificate', 'schedule_horizon' => '30d'],
        'kpis.certificates.total',
        2,
    ],
]);

test('the rows rendered add up to the count the page reports', function () {
    $this->travelTo(now()->setDate(2026, 10, 4)->startOfDay());
    $site = Site::factory()->create();
    Asset::factory()->for($site)->create(['next_service_due' => '2026-09-20']);
    Certificate::factory()->for($site)->create(['expiry_date' => '2026-09-25']);
    Asset::factory()->for($site)->create(['next_service_due' => '2026-10-07']);
    // Due twenty days out, so this one sits past the default window and is the
    // row the schedule has to admit it is leaving out.
    Certificate::factory()->for($site)->create(['expiry_date' => '2026-10-24']);

    $this->actingAs(User::factory()->create())->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->component('dashboard')
            ->where('schedule.totalCount', 3)
            ->where('schedule.returnedCount', 3)
            ->where('schedule.truncated', false)
            ->where('schedule.overdueCount', 2)
            ->where('schedule.windowItemCount', 1)
            ->where('schedule.counts.all', 3)
            ->where('schedule.counts.asset', 2)
            ->where('schedule.counts.certificate', 1)
            ->where('schedule.groups.0.itemCount', 2)
            ->where('schedule.groups.1.itemCount', 1)
            ->where('schedule.groups.2.itemCount', 0)
            // Every rendered row belongs to exactly one group, so the strip's
            // per-day counts and the group's rows describe the same work.
            ->where('schedule.overdueByKind', ['asset' => 1, 'certificate' => 1])
            ->where('schedule.overdueSince', '2026-09-20')
            ->where('schedule.nextAfter.date', '2026-10-24')
            ->where('schedule.nextAfter.daysUntil', 20));
});
