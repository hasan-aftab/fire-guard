<?php

use App\Models\Asset;
use App\Models\Certificate;
use App\Models\Client;
use App\Models\ReminderDelivery;
use App\Models\Site;
use App\Models\User;
use App\Models\WhatsAppAccount;
use App\Models\WhatsAppMessage;
use App\Models\WhatsAppTemplate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->travelTo(now()->setDate(2026, 10, 1)->startOfDay());
    $this->admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    config()->set('services.whatsapp.graph_version', 'v25.0');
    config()->set('services.whatsapp.app_secret', 'webhook-test-secret');
    config()->set('services.whatsapp.webhook_verify_token', 'strong-webhook-test-token');
});

function waSender(array $attributes = []): WhatsAppAccount
{
    return WhatsAppAccount::factory()->create(array_replace([
        'is_default' => true, 'default_slot' => 1, 'waba_id' => '2054627991879504',
        'phone_number_id' => '1311503608720043',
    ], $attributes));
}

function waTemplate(WhatsAppAccount $account, string $purpose = 'asset', array $attributes = []): WhatsAppTemplate
{
    return WhatsAppTemplate::factory()->create(array_replace([
        'waba_id' => $account->waba_id, 'purpose' => $purpose, 'name' => 'test_'.$purpose.'_template',
    ], $attributes));
}

function waAsset(int $days = 7, array $client = [], array $site = [], array $asset = []): Asset
{
    $clientModel = Client::factory()->create(array_replace([
        'whatsapp_phone' => '+12025550143', 'whatsapp_reminders_enabled' => true,
    ], $client));
    $siteModel = Site::factory()->for($clientModel)->create($site);

    return Asset::factory()->for($siteModel)->create(array_replace([
        'next_service_due' => today()->addDays($days)->toDateString(),
    ], $asset));
}

function waAccepted(string $wamid = 'wamid.test-123'): void
{
    Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => $wamid]]], 200)]);
}

function waWebhook(string $wamid, string $status, string $phoneId = '1311503608720043', ?int $code = null): string
{
    return json_encode(['object' => 'whatsapp_business_account', 'entry' => [[
        'id' => '2054627991879504', 'changes' => [[
            'field' => 'messages', 'value' => ['metadata' => ['phone_number_id' => $phoneId], 'statuses' => [[
                'id' => $wamid, 'status' => $status, 'timestamp' => '1790841600',
                'errors' => $code === null ? [] : [['code' => $code]],
            ]]],
        ]],
    ]]]) ?: '';
}

function postWaWebhook(string $body): TestResponse
{
    return test()->call('POST', route('whatsapp.webhook.receive'), [], [], [], [
        'HTTP_X_HUB_SIGNATURE_256' => 'sha256='.hash_hmac('sha256', $body, 'webhook-test-secret'),
        'CONTENT_TYPE' => 'application/json',
    ], $body);
}

test('admin manages multiple accounts with encrypted hidden tokens and a single default', function () {
    $this->actingAs($this->admin);
    $payload = ['name' => 'Main', 'display_phone_number' => '+1 202 555 0143', 'phone_number_id' => '1311503608720043',
        'waba_id' => '2054627991879504', 'access_token' => 'private-token-first', 'is_active' => true,
        'is_test' => false, 'is_default' => true];
    $this->post(route('whatsapp.accounts.store'), $payload)->assertRedirect();
    $first = WhatsAppAccount::query()->sole();
    expect($first->display_phone_number)->toBe('+12025550143');
    expect(DB::table('whatsapp_accounts')->value('access_token'))->not->toContain('private-token-first');
    $this->get(route('whatsapp.index'))->assertInertia(fn (Assert $page) => $page->component('whatsapp')
        ->where('accounts.0.display_phone_number', '+12025550143')->missing('accounts.0.access_token')->etc());
    $this->post(route('whatsapp.accounts.store'), array_replace($payload, ['name' => 'Secondary',
        'display_phone_number' => '+16505551234', 'phone_number_id' => '1311503608720044', 'access_token' => 'private-token-second']))->assertRedirect();
    expect(WhatsAppAccount::query()->count())->toBe(2);
    expect(WhatsAppAccount::query()->where('is_default', true)->sole()->name)->toBe('Secondary');
    $this->put(route('whatsapp.accounts.update', $first), array_replace($payload, ['access_token' => '', 'is_default' => false]))->assertRedirect();
    expect($first->fresh()->access_token)->toBe('private-token-first');
    $this->put(route('whatsapp.accounts.update', $first), array_replace($payload, ['access_token' => 'replacement', 'is_default' => false]))->assertRedirect();
    expect($first->fresh()->access_token)->toBe('replacement');
    $this->post(route('whatsapp.accounts.store'), $payload)->assertSessionHasErrors('phone_number_id');
});

test('phone numbers, Meta IDs, and explicit client consent have separate validation', function () {
    $this->actingAs($this->admin);
    $payload = ['name' => 'Test', 'display_phone_number' => '+1 (555) 632-3967', 'phone_number_id' => '1311503608720043',
        'waba_id' => '2054627991879504', 'access_token' => 'test-token', 'is_active' => true, 'is_test' => true, 'is_default' => false];
    $this->post(route('whatsapp.accounts.store'), $payload)->assertRedirect();
    expect(WhatsAppAccount::query()->sole()->display_phone_number)->toBe('+15556323967');
    $this->post(route('whatsapp.accounts.store'), array_replace($payload, ['phone_number_id' => '+12025550143']))->assertSessionHasErrors('phone_number_id');
    $this->post(route('whatsapp.accounts.store'), array_replace($payload, ['waba_id' => '+12025550143']))->assertSessionHasErrors('waba_id');
    $this->post(route('whatsapp.accounts.store'), array_replace($payload, ['display_phone_number' => 'bad']))->assertSessionHasErrors('display_phone_number');
    $this->post(route('clients.store'), ['company_name' => 'Customer', 'whatsapp_phone' => '+1 202 555 0143', 'whatsapp_reminders_enabled' => true])->assertRedirect();
    expect(Client::query()->sole()->whatsapp_phone)->toBe('+12025550143');
    $this->post(route('clients.store'), ['company_name' => 'Bad', 'whatsapp_phone' => '071234567', 'whatsapp_reminders_enabled' => true])->assertSessionHasErrors('whatsapp_phone');
    $this->post(route('clients.store'), ['company_name' => 'Bad', 'whatsapp_phone' => '', 'whatsapp_reminders_enabled' => true])->assertSessionHasErrors('whatsapp_phone');
});

test('connection verification matches WABA number and sanitizes Meta errors', function () {
    $account = waSender();
    $this->actingAs($this->admin);
    Http::fakeSequence()
        ->push(['data' => [['id' => $account->phone_number_id, 'display_phone_number' => '+1 650-555-1234']]], 200)
        ->push(['error' => ['message' => 'private-token-leak', 'code' => 190]], 400);
    $this->post(route('whatsapp.accounts.verify', $account))->assertRedirect();
    expect($account->fresh()->last_verified_at)->not->toBeNull();
    Http::assertSent(fn ($request) => str_contains($request->url(), $account->waba_id.'/phone_numbers')
        && $request->hasHeader('Authorization', 'Bearer test-only-access-token'));
    $this->post(route('whatsapp.accounts.verify', $account))->assertRedirect();
    expect($account->fresh()->last_verified_at)->toBeNull();
    expect($account->fresh()->last_error)->toContain('190')->toContain('expiry set to Never')->not->toContain('private-token-leak');
});

test('email and WhatsApp share discovery but submit independently and only once', function () {
    $account = waSender();
    waTemplate($account);
    $asset = waAsset();
    Mail::fake();
    waAccepted();
    $this->artisan('fire-tracker:send-reminders')->assertExitCode(0);
    $this->artisan('fire-tracker:send-reminders')->assertExitCode(0);
    expect(ReminderDelivery::query()->where('channel', 'email')->sole()->status)->toBe('sent');
    $delivery = ReminderDelivery::query()->where('channel', 'whatsapp')->sole();
    expect($delivery->subject_id)->toBe($asset->id);
    expect($delivery->status)->toBe('submitted');
    expect(WhatsAppMessage::query()->sole()->meta_message_id)->toBe('wamid.test-123');
    expect(WhatsAppMessage::query()->sole()->delivered_at)->toBeNull();
    Http::assertSentCount(1);
    Http::assertSent(fn ($request) => $request->method() === 'POST' && str_ends_with($request->url(), '/v25.0/'.$account->phone_number_id.'/messages')
        && $request['to'] === '12025550143' && $request['type'] === 'template'
        && $request['template']['components'][0]['parameters'][0]['text'] === $asset->site->client->company_name);
});

test('inactive records and disabled clients do not create WhatsApp reminders', function () {
    $account = waSender();
    waTemplate($account);
    waAsset(7, ['whatsapp_reminders_enabled' => false]);
    waAsset(7, ['is_active' => false]);
    waAsset(7, [], ['is_active' => false]);
    waAsset(7, [], [], ['status' => 'decommissioned']);
    Mail::fake();
    $this->artisan('fire-tracker:send-reminders')->assertExitCode(0);
    expect(ReminderDelivery::query()->where('channel', 'whatsapp')->count())->toBe(0);
    Http::assertNothingSent();
});

test('missing sender and pending template skip safely then approved configuration requeues', function () {
    waAsset();
    Mail::fake();
    $this->artisan('fire-tracker:send-reminders')->assertExitCode(0);
    $delivery = ReminderDelivery::query()->where('channel', 'whatsapp')->sole();
    expect($delivery->status)->toBe('skipped');
    $account = waSender();
    $template = waTemplate($account, 'asset', ['meta_status' => 'PENDING']);
    $this->artisan('fire-tracker:send-reminders')->assertExitCode(0);
    Http::assertNothingSent();
    $template->update(['meta_status' => 'APPROVED']);
    waAccepted();
    $this->artisan('fire-tracker:send-reminders')->assertExitCode(0);
    expect($delivery->fresh()->status)->toBe('submitted');
    Http::assertSentCount(1);
});

test('webhook verification and signature reject invalid requests', function () {
    $this->get('/webhooks/whatsapp?hub.mode=subscribe&hub.verify_token=strong-webhook-test-token&hub.challenge=ok123')->assertOk()->assertSeeText('ok123');
    $this->get('/webhooks/whatsapp?hub.mode=subscribe&hub.verify_token=wrong&hub.challenge=ok123')->assertForbidden();
    $this->postJson(route('whatsapp.webhook.receive'), ['object' => 'whatsapp_business_account'])->assertForbidden();
});

test('signed out-of-order and duplicate status webhooks preserve read and sender identity', function () {
    $account = waSender();
    $other = WhatsAppAccount::factory()->create(['waba_id' => $account->waba_id, 'phone_number_id' => '1311503608720099']);
    $message = WhatsAppMessage::factory()->for($account, 'account')->create(['meta_message_id' => 'wamid.test-123', 'status' => 'submitted']);
    foreach (['read', 'delivered', 'sent', 'read'] as $status) {
        postWaWebhook(waWebhook('wamid.test-123', $status))->assertOk();
    }
    expect($message->fresh()->status)->toBe('read');
    expect($message->fresh()->read_at)->not->toBeNull();
    expect($message->fresh()->delivered_at)->not->toBeNull();
    postWaWebhook(waWebhook('wamid.test-123', 'failed', $other->phone_number_id, 131014))->assertOk();
    expect($message->fresh()->status)->toBe('read');
});

test('failed and unknown webhook events are idempotent and retain safe codes', function () {
    $account = waSender();
    $message = WhatsAppMessage::factory()->for($account, 'account')->create(['meta_message_id' => 'wamid.failed', 'status' => 'submitted']);
    foreach (['wamid.failed', 'wamid.failed', 'wamid.unknown'] as $wamid) {
        postWaWebhook(waWebhook($wamid, 'failed', $account->phone_number_id, 131014))->assertOk();
    }
    expect($message->fresh()->status)->toBe('failed');
    expect($message->fresh()->failure_code)->toBe('131014');
    expect(WhatsAppMessage::query()->count())->toBe(1);
    postWaWebhook(waWebhook('wamid.failed', 'delivered', $account->phone_number_id))->assertOk();
    expect($message->fresh()->status)->toBe('delivered');
    expect($message->fresh()->failure_code)->toBeNull();
    postWaWebhook(waWebhook('wamid.failed', 'failed', $account->phone_number_id, 131014))->assertOk();
    expect($message->fresh()->status)->toBe('delivered');
});

test('dashboard aggregates actual reminder messages and excludes test sends', function () {
    $account = waSender();
    $delivery = ReminderDelivery::factory()->create(['channel' => 'whatsapp', 'status' => 'delivered']);
    WhatsAppMessage::factory()->for($account, 'account')->create(['reminder_delivery_id' => $delivery->id, 'is_test' => false,
        'status' => 'delivered', 'submitted_at' => now(), 'delivered_at' => now()]);
    WhatsAppMessage::factory()->for($account, 'account')->create(['is_test' => true, 'status' => 'failed']);
    $this->actingAs($this->admin)->get(route('whatsapp.index', ['status' => 'delivered', 'account_id' => $account->id]))
        ->assertInertia(fn (Assert $page) => $page->component('whatsapp')->has('messages.data', 1)
            ->where('summary.delivered', 1)->where('summary.failed', 0)->where('counts.whatsapp', 2)
            ->missing('filters.page')->etc());
});

test('message history filters are server side and dashboard periods exclude older outcomes', function () {
    $account = waSender();
    $other = WhatsAppAccount::factory()->create(['waba_id' => $account->waba_id, 'phone_number_id' => '1311503608720099']);
    $delivery = ReminderDelivery::factory()->create(['channel' => 'whatsapp', 'status' => 'failed']);
    $target = WhatsAppMessage::factory()->for($account, 'account')->create([
        'reminder_delivery_id' => $delivery->id, 'is_test' => false, 'status' => 'failed',
    ]);
    $otherDelivery = ReminderDelivery::factory()->create(['channel' => 'whatsapp', 'status' => 'delivered']);
    WhatsAppMessage::factory()->for($other, 'account')->create([
        'reminder_delivery_id' => $otherDelivery->id, 'is_test' => false, 'status' => 'delivered',
        'submitted_at' => now()->subDays(8), 'created_at' => now()->subDays(8),
    ]);

    $this->actingAs($this->admin)->get(route('whatsapp.index', [
        'period' => 'today', 'status' => 'failed', 'type' => 'asset', 'account_id' => $account->id,
        'search' => $delivery->client_name, 'from' => '2026-10-01', 'to' => '2026-10-01',
    ]))->assertInertia(fn (Assert $page) => $page->component('whatsapp')
        ->has('messages.data', 1)->where('messages.data.0.id', $target->id)
        ->where('summary.failed', 1)->where('summary.delivered', 0)
        ->where('filters.from', '2026-10-01')->missing('filters.page')->etc());

    $this->get(route('whatsapp.index', ['period' => '30', 'status' => 'delivered', 'account_id' => $account->id]))
        ->assertInertia(fn (Assert $page) => $page->component('whatsapp')->has('messages.data', 0)
            ->where('summary.delivered', 1)->etc());
    $this->get(route('whatsapp.index', ['search' => '%']))
        ->assertInertia(fn (Assert $page) => $page->component('whatsapp')->has('messages.data', 0)->etc());
});

test('test sends stay separate and regular users cannot manage WhatsApp credentials', function () {
    $account = waSender();
    $template = waTemplate($account);
    $this->get(route('whatsapp.index'))->assertRedirect(route('login'));
    $this->actingAs(User::factory()->create())->get(route('whatsapp.index'))->assertForbidden();
    $this->post(route('whatsapp.test'), ['type' => 'asset', 'recipient' => '+12025550143'])->assertForbidden();
    auth()->logout();
    $this->actingAs($this->admin);
    waAccepted();
    $this->post(route('whatsapp.test'), ['type' => 'asset', 'recipient' => '+12025550143'])->assertRedirect();
    expect(WhatsAppMessage::query()->sole()->is_test)->toBeTrue();
    expect(WhatsAppMessage::query()->sole()->status)->toBe('submitted');
    expect(ReminderDelivery::query()->count())->toBe(0);
});

test('admin typed whatsapp test resolves default sender and purpose template', function (): void {
    $account = waSender();
    waTemplate($account, 'asset');
    waTemplate($account, 'certificate');
    $this->actingAs($this->admin);
    Http::fakeSequence()
        ->push(['messages' => [['id' => 'wamid.asset']]], 200)
        ->push(['messages' => [['id' => 'wamid.certificate']]], 200);
    $this->post(route('whatsapp.test'), ['type' => 'asset', 'recipient' => '+12025550143'])->assertRedirect();
    $message = WhatsAppMessage::query()->where('meta_message_id', 'wamid.asset')->sole();
    expect($message->is_test)->toBeTrue();
    expect($message->status)->toBe('submitted');
    expect(ReminderDelivery::query()->count())->toBe(0);
    $this->post(route('whatsapp.test'), ['type' => 'certificate', 'recipient' => '+12025550143'])->assertRedirect();
    expect(WhatsAppMessage::query()->count())->toBe(2);
    expect(WhatsAppMessage::query()->where('meta_message_id', 'wamid.certificate')->exists())->toBeTrue();
});

test('test sends fall back to a verified test account when no production default exists', function (): void {
    $account = WhatsAppAccount::factory()->create(['is_test' => true]);
    waTemplate($account, 'asset');
    $this->actingAs($this->admin);
    Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.asset']]], 200)]);
    $this->post(route('whatsapp.test'), ['type' => 'asset', 'recipient' => '+12025550143'])->assertRedirect();
    $message = WhatsAppMessage::query()->sole();
    expect($message->whatsapp_account_id)->toBe($account->id);
    expect($message->is_test)->toBeTrue();
    expect($message->status)->toBe('submitted');
    expect(ReminderDelivery::query()->count())->toBe(0);
});

test('test sends prefer the verified production default over a test account', function (): void {
    $testAccount = WhatsAppAccount::factory()->create(['is_test' => true]);
    $default = waSender();
    waTemplate($testAccount, 'asset');
    waTemplate($default, 'asset');
    $this->actingAs($this->admin);
    Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.asset']]], 200)]);
    $this->post(route('whatsapp.test'), ['type' => 'asset', 'recipient' => '+12025550143'])->assertRedirect();
    expect(WhatsAppMessage::query()->sole()->whatsapp_account_id)->toBe($default->id);
});

test('test sends name the missing sender or template in the validation error', function (): void {
    $this->actingAs($this->admin);
    Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.certificate']]], 200)]);
    $this->post(route('whatsapp.test'), ['type' => 'asset', 'recipient' => '+12025550143'])
        ->assertSessionHasErrors(['type' => 'No verified active sender is available. Verify the default sender, or an active test account, on this page.']);
    $account = waSender();
    $this->post(route('whatsapp.test'), ['type' => 'certificate', 'recipient' => '+12025550143'])
        ->assertSessionHasErrors(['type' => 'No approved, enabled template is mapped for this reminder type. Sync templates with Meta.']);
    expect(WhatsAppMessage::query()->count())->toBe(0);
    waTemplate($account, 'certificate');
    $this->post(route('whatsapp.test'), ['type' => 'certificate', 'recipient' => '+12025550143'])->assertRedirect();
    expect(WhatsAppMessage::query()->count())->toBe(1);
});
test('certificate reminders use the configured WABA template and a new due date makes a new cycle', function () {
    $account = waSender();
    waTemplate($account, 'asset');
    waTemplate($account, 'certificate');
    $asset = waAsset(14);
    $certificate = Certificate::factory()->for($asset->site)->for($asset)->create([
        'expiry_date' => today()->addDays(7)->toDateString(),
    ]);
    Mail::fake();
    Http::fakeSequence()->push(['messages' => [['id' => 'wamid.asset']]])
        ->push(['messages' => [['id' => 'wamid.certificate']]])
        ->push(['messages' => [['id' => 'wamid.next']]]);
    $this->artisan('fire-tracker:send-reminders')->assertExitCode(0);
    expect(ReminderDelivery::query()->where('channel', 'whatsapp')->count())->toBe(2);
    $certMessage = WhatsAppMessage::query()->where('template_name', 'test_certificate_template')->sole();
    expect($certMessage->reminderDelivery->subject_id)->toBe($certificate->id);
    Http::assertSent(fn ($request) => $request['template']['name'] === 'test_certificate_template'
        && $request['template']['components'][0]['parameters'][3]['text'] === $certificate->expiry_date->format('j F Y'));
    $asset->update(['next_service_due' => '2026-11-01']);
    $certificate->update(['expiry_date' => '2027-01-01']);
    $this->travelTo(now()->setDate(2026, 10, 18)->startOfDay());
    $this->artisan('fire-tracker:send-reminders')->assertExitCode(0);
    expect(ReminderDelivery::query()->where('channel', 'whatsapp')->where('asset_id', $asset->id)->count())->toBe(2);
});

test('template body parameter order is optional and still validated per entry', function () {
    $account = waSender();
    $this->actingAs($this->admin);
    $this->post(route('whatsapp.templates.save'), [
        'account_id' => $account->id, 'purpose' => 'asset', 'name' => 'plain_notice', 'language' => 'en_US',
        'is_enabled' => true,
    ])->assertRedirect();
    expect(WhatsAppTemplate::query()->sole()->parameter_keys)->toBe([]);
    $this->post(route('whatsapp.templates.save'), [
        'account_id' => $account->id, 'purpose' => 'asset', 'name' => 'plain_notice', 'language' => 'en_US',
        'parameter_keys' => ['not_a_key'], 'is_enabled' => true,
    ])->assertSessionHasErrors('parameter_keys.0');
    $this->post(route('whatsapp.templates.save'), [
        'account_id' => $account->id, 'purpose' => 'asset', 'name' => 'plain_notice', 'language' => 'en_US',
        'parameter_keys' => ['client_name', 'timing'], 'is_enabled' => true,
    ])->assertRedirect();
    expect(WhatsAppTemplate::query()->sole()->parameter_keys)->toBe(['client_name', 'timing']);
});

test('template sync is scoped to WABA and blocks parameter mismatch', function () {
    $account = waSender();
    $other = WhatsAppAccount::factory()->create(['waba_id' => '2054627991879505', 'phone_number_id' => '1311503608720044']);
    $this->actingAs($this->admin);
    $this->post(route('whatsapp.templates.save'), [
        'account_id' => $account->id, 'purpose' => 'asset', 'name' => 'approved_notice', 'language' => 'en_US',
        'parameter_keys' => ['client_name', 'site_name'], 'is_enabled' => true,
    ])->assertRedirect();
    waTemplate($other, 'asset', ['name' => 'approved_notice', 'last_synced_at' => null]);
    Http::fake(['graph.facebook.com/*' => Http::response(['data' => [[
        'name' => 'approved_notice', 'language' => 'en_US', 'status' => 'APPROVED', 'category' => 'UTILITY',
        'components' => [['type' => 'BODY', 'text' => 'Hello {{1}} at {{2}}']],
    ]]], 200)]);
    $this->post(route('whatsapp.templates.sync', $account))->assertRedirect();
    $mapping = WhatsAppTemplate::query()->where('waba_id', $account->waba_id)->sole();
    expect($mapping->meta_status)->toBe('APPROVED');
    expect($mapping->last_error)->toBeNull();
    expect(WhatsAppTemplate::query()->where('waba_id', $other->waba_id)->sole()->last_synced_at)->toBeNull();
    $mapping->update(['parameter_keys' => ['client_name']]);
    $this->post(route('whatsapp.templates.sync', $account))->assertRedirect();
    expect($mapping->fresh()->last_error)->toContain('numbered body parameters');
});

test('definite Meta rejection allows one controlled retry while uncertain outcomes do not', function () {
    $account = waSender();
    waTemplate($account);
    waAsset();
    Mail::fake();
    Http::fakeSequence()->push(['error' => ['code' => 132000, 'message' => 'token secret']], 400)
        ->push(['messages' => [['id' => 'wamid.retry']]], 200);
    $this->artisan('fire-tracker:send-reminders')->assertExitCode(0);
    $message = WhatsAppMessage::query()->sole();
    expect($message->status)->toBe('failed');
    expect($message->last_error)->not->toContain('token secret');
    $this->actingAs($this->admin)->post(route('whatsapp.messages.retry', $message))->assertRedirect();
    expect($message->fresh()->status)->toBe('submitted');
    expect($message->fresh()->attempt_count)->toBe(2);
    $this->post(route('whatsapp.messages.retry', $message))->assertStatus(409);
    Http::assertSentCount(2);
});

test('webhook delivery and read update the linked reminder without claiming API submission was delivery', function () {
    $account = waSender();
    waTemplate($account);
    waAsset();
    Mail::fake();
    waAccepted();
    $this->artisan('fire-tracker:send-reminders')->assertExitCode(0);
    $delivery = ReminderDelivery::query()->where('channel', 'whatsapp')->sole();
    expect($delivery->status)->toBe('submitted');
    postWaWebhook(waWebhook('wamid.test-123', 'sent'))->assertOk();
    expect($delivery->fresh()->status)->toBe('sent');
    postWaWebhook(waWebhook('wamid.test-123', 'delivered'))->assertOk();
    expect($delivery->fresh()->status)->toBe('delivered');
    postWaWebhook(waWebhook('wamid.test-123', 'read'))->assertOk();
    expect($delivery->fresh()->status)->toBe('read');
    expect(WhatsAppMessage::query()->sole()->read_at)->not->toBeNull();
});

test('deactivating a default sender keeps history and promotes another verified production account', function () {
    $default = waSender();
    $other = WhatsAppAccount::factory()->create(['waba_id' => $default->waba_id, 'phone_number_id' => '1311503608720044']);
    WhatsAppMessage::factory()->for($default, 'account')->create(['status' => 'delivered']);
    $this->actingAs($this->admin)->patch(route('whatsapp.accounts.active', $default))->assertRedirect();
    expect($default->fresh()->is_active)->toBeFalse();
    expect($default->fresh()->is_default)->toBeFalse();
    expect($other->fresh()->is_default)->toBeTrue();
    expect(WhatsAppMessage::query()->sole()->account->id)->toBe($default->id);
});
