<?php

use App\Models\Asset;
use App\Models\Certificate;
use App\Models\FireNotification;
use App\Models\Inspection;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('notification generation includes actionable records once and preserves read state', function () {
    $this->travelTo(now()->setDate(2026, 9, 29)->startOfDay());
    $user = User::factory()->create();
    $asset = Asset::factory()->create(['next_service_due' => '2026-09-28']);
    Asset::factory()->create(['next_service_due' => '2026-10-13']);
    Asset::factory()->create(['next_service_due' => '2026-10-14']);
    Asset::factory()->create(['next_service_due' => '2026-09-28', 'status' => 'decommissioned']);
    Certificate::factory()->create(['expiry_date' => '2026-09-28']);
    Certificate::factory()->create(['expiry_date' => '2026-10-29']);
    Certificate::factory()->create(['expiry_date' => '2026-10-30']);
    Inspection::factory()->for($asset)->create(['result' => 'fail']);
    Inspection::factory()->for($asset)->create(['result' => 'pass']);

    $this->actingAs($user)->from(route('dashboard'))->post(route('notifications.generate'))->assertRedirect(route('dashboard'));
    $notification = FireNotification::where('type', 'overdue_asset')->firstOrFail();
    $this->patch(route('notifications.update', $notification))->assertRedirect(route('assets.index'));
    $this->post(route('notifications.generate'))->assertRedirect();

    $this->assertDatabaseCount('fire_notifications', 5);
    expect($notification->refresh()->is_read)->toBeTrue();
    $this->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page->where('fireNotifications.unreadCount', 4)->has('fireNotifications.items', 5));
});

test('standalone notification polling returns current notifications as JSON', function () {
    $user = User::factory()->create();
    $asset = Asset::factory()->create(['next_service_due' => now()->subDay()->toDateString()]);

    $this->actingAs($user)->postJson(route('notifications.generate'))
        ->assertOk()
        ->assertJsonPath('unreadCount', 1)
        ->assertJsonCount(1, 'items')
        ->assertJsonPath('items.0.entity_id', $asset->id);
});

test('refreshing unchanged alerts preserves their read state and timestamps', function () {
    $this->travelTo(now()->setDate(2026, 9, 29)->startOfDay());
    $user = User::factory()->create();
    Asset::factory()->create(['next_service_due' => '2026-09-28']);
    $this->actingAs($user)->postJson(route('notifications.generate'))->assertJsonPath('unreadCount', 1);
    $notification = FireNotification::firstOrFail();
    $this->patchJson(route('notifications.update', $notification))->assertJsonPath('unreadCount', 0);
    $updatedAt = $notification->refresh()->updated_at->toDateTimeString();

    $this->travel(1)->hours();
    $this->postJson(route('notifications.generate'))
        ->assertJsonPath('unreadCount', 0)
        ->assertJsonPath('items.0.is_read', true);

    expect($notification->refresh()->updated_at->toDateTimeString())->toBe($updatedAt);
});

test('notification messages use calendar day counts and singular units', function () {
    $this->travelTo(now()->setDate(2026, 9, 29)->startOfDay());
    $user = User::factory()->create();
    $overdueAsset = Asset::factory()->create(['next_service_due' => '2026-09-28']);
    $dueAsset = Asset::factory()->create(['next_service_due' => '2026-09-30']);
    $expiredCertificate = Certificate::factory()->create(['expiry_date' => '2026-09-28']);
    $expiringCertificate = Certificate::factory()->create(['expiry_date' => '2026-09-30']);

    $this->actingAs($user)->postJson(route('notifications.generate'))->assertJsonPath('unreadCount', 4);

    expect(FireNotification::where('entity_type', 'asset')->where('entity_id', $overdueAsset->id)->value('message'))
        ->toContain('is 1 day overdue for service');
    expect(FireNotification::where('entity_type', 'asset')->where('entity_id', $dueAsset->id)->value('message'))
        ->toContain('is due in 1 day');
    expect(FireNotification::where('entity_type', 'certificate')->where('entity_id', $expiredCertificate->id)->value('message'))
        ->toContain('expired 1 day ago');
    expect(FireNotification::where('entity_type', 'certificate')->where('entity_id', $expiringCertificate->id)->value('message'))
        ->toContain('expires in 1 day');
});

test('read alerts older than thirty days renew while newer read alerts stay read', function () {
    $this->travelTo(now()->setDate(2026, 9, 30)->startOfDay());
    $user = User::factory()->create();
    $oldAsset = Asset::factory()->create(['next_service_due' => '2026-09-29']);
    $recentAsset = Asset::factory()->create(['next_service_due' => '2026-09-29']);
    $oldAlert = FireNotification::factory()->for($user)->create([
        'entity_id' => $oldAsset->id,
        'is_read' => true,
        'created_at' => '2026-08-30 00:00:00',
    ]);
    $recentAlert = FireNotification::factory()->for($user)->create([
        'entity_id' => $recentAsset->id,
        'is_read' => true,
        'created_at' => '2026-08-31 00:00:00',
    ]);

    $this->actingAs($user)->postJson(route('notifications.generate'))
        ->assertJsonPath('unreadCount', 1)
        ->assertJsonCount(2, 'items');

    $this->assertDatabaseMissing('fire_notifications', ['id' => $oldAlert->id]);
    $this->assertDatabaseHas('fire_notifications', ['id' => $recentAlert->id, 'is_read' => true]);
    $this->assertDatabaseHas('fire_notifications', ['entity_id' => $oldAsset->id, 'is_read' => false]);
});

test('JSON mark all returns the refreshed list without changing another users alerts', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $own = FireNotification::factory()->for($user)->create();
    $foreign = FireNotification::factory()->for($other)->create();

    $this->actingAs($user)->patchJson(route('notifications.read-all'))
        ->assertOk()
        ->assertJsonPath('unreadCount', 0)
        ->assertJsonPath('items.0.is_read', true);

    expect($own->refresh()->is_read)->toBeTrue();
    expect($foreign->refresh()->is_read)->toBeFalse();
});

test('JSON item selection reads only the owners alert', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $own = FireNotification::factory()->for($user)->create();
    $foreign = FireNotification::factory()->for($other)->create();

    $this->actingAs($user)->patchJson(route('notifications.update', $foreign))->assertNotFound();
    $this->patchJson(route('notifications.update', $own))
        ->assertOk()
        ->assertJsonPath('unreadCount', 0)
        ->assertJsonPath('items.0.id', $own->id)
        ->assertJsonPath('items.0.is_read', true);

    expect($foreign->refresh()->is_read)->toBeFalse();
});

test('marking all notifications read affects only the current user', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $own = FireNotification::factory()->for($user)->create();
    $foreign = FireNotification::factory()->for($other)->create();

    $this->actingAs($user)->patch(route('notifications.read-all'))->assertRedirect();
    expect($own->refresh()->is_read)->toBeTrue();
    expect($foreign->refresh()->is_read)->toBeFalse();
});

test('users cannot read or receive another users notifications', function () {
    $user = User::factory()->create();
    $foreign = FireNotification::factory()->create();

    $this->actingAs($user)->patch(route('notifications.update', $foreign))->assertNotFound();
    expect($foreign->refresh()->is_read)->toBeFalse();
    $this->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page->has('fireNotifications.items', 0)->where('fireNotifications.unreadCount', 0));
});

test('rescheduling an asset and passing an inspection removes their stale alerts', function () {
    $this->travelTo(now()->setDate(2026, 9, 29)->startOfDay());
    $user = User::factory()->create();
    $asset = Asset::factory()->create(['next_service_due' => '2026-09-28']);
    $inspection = Inspection::factory()->for($asset)->create(['result' => 'fail']);
    $this->actingAs($user)->post(route('notifications.generate'));

    $this->put(route('assets.update', $asset), [
        'site_id' => $asset->site_id, 'asset_type_id' => $asset->asset_type_id, 'next_service_due' => '2027-01-01',
    ])->assertSessionHasNoErrors();
    $this->put(route('inspections.update', $inspection), [
        'asset_id' => $asset->id, 'technician_id' => $inspection->technician_id,
        'inspection_date' => $inspection->inspection_date->toDateString(), 'result' => 'pass',
    ])->assertSessionHasNoErrors();

    $this->assertDatabaseCount('fire_notifications', 0);
});

test('decommissioned assets remove alerts for the asset and its inspections', function () {
    $user = User::factory()->create();
    $asset = Asset::factory()->create(['next_service_due' => now()->subDay()->toDateString()]);
    Inspection::factory()->for($asset)->create(['result' => 'fail']);
    $this->actingAs($user)->post(route('notifications.generate'));

    $this->delete(route('assets.destroy', $asset))->assertRedirect();

    $this->assertDatabaseCount('fire_notifications', 0);
});

test('guests cannot generate or mark notifications read', function () {
    $this->post(route('notifications.generate'))->assertRedirect(route('login'));
    $this->patch(route('notifications.read-all'))->assertRedirect(route('login'));
});
