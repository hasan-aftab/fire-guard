<?php

use App\Models\Asset;
use App\Models\AssetType;
use App\Models\Certificate;
use App\Models\Inspection;
use App\Models\Site;
use App\Models\Technician;
use App\Models\User;
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
        ->where('attention.total', 0)
        ->where('certificates.total', 0)
        ->where('calendar.events.total', 0)
        ->has('summary.upcomingDates', 0));
});

test('unverified users are redirected before dashboard data is shown', function () {
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)->get(route('dashboard'))
        ->assertRedirect(route('verification.notice'));
});

test('dashboard paginates alert tables and the calendar independently', function () {
    $this->travelTo(now()->setDate(2026, 9, 29)->startOfDay());
    $user = User::factory()->create();
    $site = Site::factory()->create();
    $type = AssetType::factory()->create();
    $assets = Asset::factory()->for($site)->for($type)->count(12)->create(['next_service_due' => '2026-10-01']);
    $certificates = Certificate::factory()->for($site)->count(12)->create(['expiry_date' => '2026-10-01']);

    $this->actingAs($user)->get(route('dashboard', ['attention_page' => 2, 'cert_page' => 2, 'calendar_month' => '2026-10']))
        ->assertInertia(fn (Assert $page) => $page->component('dashboard')
            ->where('attention.total', 12)->where('attention.current_page', 2)
            ->has('attention.data', 2)->where('attention.data.0.assetId', $assets[10]->id)
            ->where('certificates.total', 12)->where('certificates.current_page', 2)
            ->has('certificates.data', 2)->where('certificates.data.0.certificateId', $certificates[10]->id)
            ->where('summary.upcomingDates.0', '2026-10-01')
            ->where('calendar.events.total', 12)
            ->has('calendar.events.data', 10));
});

test('dashboard attention uses fourteen days for assets and thirty days for certificates', function () {
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
            ->where('summary.stats.overdueInspections', 0)
            ->where('summary.stats.expiringCerts', 1)
            ->where('attention.total', 1)
            ->where('attention.data.0.assetId', $assetBoundary->id)
            ->where('attention.data.0.serviceStatus', 'due_soon')
            ->where('certificates.total', 1)
            ->where('certificates.data.0.certificateId', $certificateBoundary->id)
            ->where('certificates.data.0.status', 'expiring_soon'));
});

test('dashboard calendar uses only current asset due dates and pages through each month', function () {
    $this->travelTo(now()->setDate(2026, 9, 29)->startOfDay());
    $user = User::factory()->create();
    $site = Site::factory()->create();
    $type = AssetType::factory()->create();
    $asset = Asset::factory()->for($site)->for($type)->create(['next_service_due' => '2026-10-02']);
    Inspection::factory()->for($asset)->create(['next_due_date' => '2026-10-03']);
    Asset::factory()->for($site)->for($type)->count(10)->create(['next_service_due' => '2026-10-01']);
    Asset::factory()->for($site)->for($type)->create(['next_service_due' => '2027-01-01']);

    $this->actingAs($user)->get(route('dashboard', ['calendar_month' => '2026-10', 'calendar_page' => 2]))
        ->assertInertia(fn (Assert $page) => $page->component('dashboard')
            ->where('calendar.month', '2026-10')
            ->where('calendar.events.total', 11)
            ->where('calendar.events.current_page', 2)
            ->has('calendar.events.data', 1)
            ->where('calendar.events.data.0.source', 'asset')
            ->where('calendar.events.data.0.dueDate', '2026-10-02')
            ->where('summary.upcomingDates.0', '2026-10-01')
            ->where('summary.upcomingDates.1', '2026-10-02')
            ->has('summary.upcomingDates', 2));
});

test('a newly logged inspection due date appears on the dashboard calendar', function () {
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

    $this->get(route('dashboard', ['calendar_month' => '2026-10']))
        ->assertInertia(fn (Assert $page) => $page->component('dashboard')
            ->where('summary.upcomingDates.0', '2026-10-15')
            ->where('calendar.events.total', 1)
            ->where('calendar.events.data.0.source', 'asset')
            ->where('calendar.events.data.0.dueDate', '2026-10-15'));
});

test('dashboard shows the last available alert page when requested pages are out of range', function () {
    $this->travelTo(now()->setDate(2026, 9, 29)->startOfDay());
    $user = User::factory()->create();
    $site = Site::factory()->create();
    $type = AssetType::factory()->create();
    Asset::factory()->for($site)->for($type)->count(11)->create(['next_service_due' => '2026-10-01']);
    Certificate::factory()->for($site)->count(11)->create(['expiry_date' => '2026-10-01']);

    $this->actingAs($user)->get(route('dashboard', ['attention_page' => 99, 'cert_page' => 99]))
        ->assertInertia(fn (Assert $page) => $page->component('dashboard')
            ->where('attention.current_page', 2)->has('attention.data', 1)
            ->where('certificates.current_page', 2)->has('certificates.data', 1));
});

test('dashboard applies each date boundary without requiring a complete range', function () {
    $this->travelTo(now()->setDate(2026, 9, 29)->startOfDay());
    $user = User::factory()->create();
    $site = Site::factory()->create();
    $type = AssetType::factory()->create();
    Asset::factory()->for($site)->for($type)->create(['next_service_due' => '2026-09-28']);
    $matchingAsset = Asset::factory()->for($site)->for($type)->create(['next_service_due' => '2026-10-10']);
    Asset::factory()->for($site)->for($type)->create(['next_service_due' => '2026-10-30']);
    Asset::factory()->for($site)->for($type)->create(['next_service_due' => '2026-10-10', 'status' => 'decommissioned']);
    $matchingCertificate = Certificate::factory()->for($site)->create(['expiry_date' => '2026-09-28']);
    Certificate::factory()->for($site)->create(['expiry_date' => '2026-10-10']);

    $this->actingAs($user)->get(route('dashboard', ['attention_from' => '2026-10-01', 'cert_to' => '2026-09-30']))
        ->assertInertia(fn (Assert $page) => $page->component('dashboard')
            ->where('attention.total', 1)->where('attention.data.0.assetId', $matchingAsset->id)
            ->where('certificates.total', 1)->where('certificates.data.0.certificateId', $matchingCertificate->id)
            ->where('summary.stats.overdueInspections', 1)
            ->where('summary.stats.expiringCerts', 2)
            ->where('filters.attention_from', '2026-10-01')
            ->where('filters.cert_to', '2026-09-30'));
});

test('dashboard rejects invalid date ranges and page numbers', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->from(route('dashboard'))->get(route('dashboard', [
        'attention_from' => '2026-10-20',
        'attention_to' => '2026-10-01',
        'cert_from' => 'not-a-date',
        'cert_page' => 0,
        'calendar_month' => '2026-99',
        'calendar_page' => 0,
    ]))->assertRedirect(route('dashboard'))
        ->assertSessionHasErrors(['attention_to', 'cert_from', 'cert_page', 'calendar_month', 'calendar_page']);
});
