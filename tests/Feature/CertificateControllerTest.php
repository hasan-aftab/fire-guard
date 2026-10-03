<?php

use App\Models\Certificate;
use App\Models\CertificateType;
use App\Models\Client;
use App\Models\Site;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('certificate status and filters follow the inclusive thirty day expiry boundary', function () {
    $this->travelTo(now()->setDate(2026, 10, 1)->startOfDay());
    $site = Site::factory()->create();
    $expired = Certificate::factory()->for($site)->create(['issue_date' => '2026-01-01', 'expiry_date' => '2026-09-30']);
    $today = Certificate::factory()->for($site)->create(['issue_date' => '2026-01-01', 'expiry_date' => '2026-10-01']);
    $tomorrow = Certificate::factory()->for($site)->create(['issue_date' => '2026-01-01', 'expiry_date' => '2026-10-02']);
    $boundary = Certificate::factory()->for($site)->create(['issue_date' => '2026-01-01', 'expiry_date' => '2026-10-31']);
    $valid = Certificate::factory()->for($site)->create(['issue_date' => '2026-01-01', 'expiry_date' => '2026-11-01']);

    $this->actingAs(User::factory()->create())->get(route('certificates.index'))
        ->assertInertia(fn (Assert $page) => $page->component('compliance')
            ->where('records.data.0.id', $expired->id)
            ->where('records.data.0.status', 'expired')
            ->where('records.data.1.id', $today->id)
            ->where('records.data.1.status', 'expiring_soon')
            ->where('records.data.2.id', $tomorrow->id)
            ->where('records.data.2.status', 'expiring_soon')
            ->where('records.data.3.id', $boundary->id)
            ->where('records.data.3.status', 'expiring_soon')
            ->where('records.data.4.id', $valid->id)
            ->where('records.data.4.status', 'valid'));
    $this->get(route('certificates.index', ['status' => 'due_soon']))
        ->assertInertia(fn (Assert $page) => $page->has('records.data', 3)
            ->where('records.data.2.id', $boundary->id));
    $this->get(route('certificates.index', ['status' => 'overdue']))
        ->assertInertia(fn (Assert $page) => $page->has('records.data', 1)
            ->where('records.data.0.id', $expired->id));
    $this->get(route('certificates.index', ['status' => 'ok']))
        ->assertInertia(fn (Assert $page) => $page->has('records.data', 1)
            ->where('records.data.0.id', $valid->id));
});

test('certificate status changes with the calendar date without changing its stored column', function () {
    $this->travelTo(now()->setDate(2026, 10, 1)->startOfDay());
    $certificate = Certificate::factory()->create(['issue_date' => '2026-01-01', 'expiry_date' => '2026-11-01']);
    expect($certificate->status)->toBe('valid');

    $this->travel(1)->days();
    expect($certificate->refresh()->status)->toBe('expiring_soon');

    $this->travel(31)->days();
    expect($certificate->refresh()->status)->toBe('expired');
    $this->assertDatabaseHas('certificates', ['id' => $certificate->id, 'status' => 'valid']);
});

test('certificate search matches client names without treating wildcards as patterns', function () {
    $client = Client::factory()->create(['company_name' => 'Harbour % Safety']);
    $site = Site::factory()->for($client)->create(['site_name' => 'Main Depot']);
    $target = Certificate::factory()->for($site)->create();
    Certificate::factory()->create();

    $this->actingAs(User::factory()->create())->get(route('certificates.index', ['search' => '%']))
        ->assertInertia(fn (Assert $page) => $page->has('records.data', 1)
            ->where('records.data.0.id', $target->id));
});

test('certificate writes derive status from expiry date', function () {
    $this->travelTo(now()->setDate(2026, 9, 30)->startOfDay());
    $site = Site::factory()->create();
    $payload = [
        'site_id' => $site->id,
        'certificate_type_id' => CertificateType::factory()->create()->id,
        'issue_date' => '2026-09-01',
        'expiry_date' => '2026-09-29',
        'status' => 'valid',
    ];

    $this->actingAs(User::factory()->create())->post(route('certificates.store'), $payload)
        ->assertSessionHasNoErrors()->assertRedirect();
    $certificate = Certificate::query()->firstOrFail();
    expect($certificate->status)->toBe('expired');

    $this->put(route('certificates.update', $certificate), [
        ...$payload,
        'expiry_date' => '2026-10-31',
        'status' => 'expired',
    ])->assertSessionHasNoErrors();
    expect($certificate->refresh()->status)->toBe('valid');
    $this->assertDatabaseHas('certificates', ['id' => $certificate->id, 'status' => 'valid']);
});

test('expired certificates remain listed and cannot be deleted through the application', function () {
    $this->travelTo(now()->setDate(2026, 9, 30)->startOfDay());
    $site = Site::factory()->create();
    Certificate::factory()->count(11)->for($site)->create(['issue_date' => '2026-01-01', 'expiry_date' => '2026-09-01']);
    $lastCertificate = Certificate::query()->latest('id')->firstOrFail();

    $this->actingAs(User::factory()->create())->from(route('certificates.index', ['page' => 2]))
        ->delete('/certificates/'.$lastCertificate->id)->assertStatus(405);
    $this->get(route('certificates.index', ['page' => 2]))
        ->assertInertia(fn (Assert $page) => $page->has('records.data', 1)
            ->where('records.data.0.status', 'expired')
            ->where('records.total', 11)->missing('filters.page'));
    $this->assertModelExists($lastCertificate);
});
