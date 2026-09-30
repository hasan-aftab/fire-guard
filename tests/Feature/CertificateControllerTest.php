<?php

use App\Models\Certificate;
use App\Models\Client;
use App\Models\Site;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('certificate status follows the expiry date at the fourteen day boundary', function () {
    $this->travelTo(now()->setDate(2026, 9, 30)->startOfDay());
    $site = Site::factory()->create();
    $overdue = Certificate::factory()->for($site)->create(['expiry_date' => '2026-09-29']);
    $dueToday = Certificate::factory()->for($site)->create(['expiry_date' => '2026-09-30']);
    $dueInFourteenDays = Certificate::factory()->for($site)->create(['expiry_date' => '2026-10-14']);
    $ok = Certificate::factory()->for($site)->create(['expiry_date' => '2026-10-15']);

    $this->actingAs(User::factory()->create())->get(route('certificates.index'))
        ->assertInertia(fn (Assert $page) => $page->component('compliance')
            ->where('records.data.0.id', $overdue->id)
            ->where('records.data.0.status', 'expired')
            ->where('records.data.1.id', $dueToday->id)
            ->where('records.data.1.status', 'expiring_soon')
            ->where('records.data.2.id', $dueInFourteenDays->id)
            ->where('records.data.2.status', 'expiring_soon')
            ->where('records.data.3.id', $ok->id)
            ->where('records.data.3.status', 'valid'));
    $this->get(route('certificates.index', ['status' => 'due_soon']))
        ->assertInertia(fn (Assert $page) => $page->has('records.data', 2));
});

test('certificate search matches client names without treating wildcards as patterns', function () {
    $client = Client::factory()->create(['company_name' => 'Harbour % Safety']);
    $site = Site::factory()->for($client)->create(['site_name' => 'Main Depot']);
    $target = Certificate::factory()->for($site)->create();
    Certificate::factory()->create(['certificate_type' => 'Annual Fire Safety Statement']);

    $this->actingAs(User::factory()->create())->get(route('certificates.index', ['search' => '%']))
        ->assertInertia(fn (Assert $page) => $page->has('records.data', 1)
            ->where('records.data.0.id', $target->id));
});

test('certificate writes ignore submitted status and reject unsafe document URLs', function () {
    $this->travelTo(now()->setDate(2026, 9, 30)->startOfDay());
    $site = Site::factory()->create();
    $payload = [
        'site_id' => $site->id,
        'certificate_type' => 'Fire Safety',
        'issue_date' => '2026-09-01',
        'expiry_date' => '2026-09-29',
        'status' => 'valid',
        'document_url' => 'https://example.com/certificate.pdf',
    ];

    $this->actingAs(User::factory()->create())->post(route('certificates.store'), $payload)
        ->assertSessionHasNoErrors()->assertRedirect();
    $certificate = Certificate::query()->firstOrFail();
    expect($certificate->status)->toBe('expired');
    $this->assertDatabaseHas('certificates', ['id' => $certificate->id, 'document_url' => $payload['document_url']]);

    $this->put(route('certificates.update', $certificate), [
        ...$payload,
        'expiry_date' => '2026-10-15',
        'status' => 'expired',
        'document_url' => 'javascript:alert(1)',
    ])->assertSessionHasErrors(['document_url']);
    expect($certificate->refresh()->expiry_date->toDateString())->toBe('2026-09-29');

    $this->put(route('certificates.update', $certificate), [
        ...$payload,
        'expiry_date' => '2026-10-15',
        'status' => 'expired',
    ])->assertSessionHasNoErrors();
    expect($certificate->refresh()->status)->toBe('valid');
});

test('certificate pagination returns to the last available page after a deletion', function () {
    $site = Site::factory()->create();
    Certificate::factory()->count(11)->for($site)->create();
    $lastCertificate = Certificate::query()->latest('id')->firstOrFail();

    $this->actingAs(User::factory()->create())->from(route('certificates.index', ['page' => 2]))
        ->delete(route('certificates.destroy', $lastCertificate))->assertRedirect();
    $this->get(route('certificates.index', ['page' => 2]))
        ->assertRedirect(route('certificates.index', ['page' => 1]));
    $this->get(route('certificates.index', ['page' => 1]))
        ->assertInertia(fn (Assert $page) => $page->has('records.data', 10)
            ->where('records.total', 10)->missing('filters.page'));
});
