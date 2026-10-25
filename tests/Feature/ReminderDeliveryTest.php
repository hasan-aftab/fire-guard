<?php

use App\Jobs\SendReminderEmail;
use App\Mail\AssetInspectionReminderMail;
use App\Mail\CertificateExpiryReminderMail;
use App\Models\Asset;
use App\Models\Certificate;
use App\Models\Client;
use App\Models\Inspection;
use App\Models\ReminderDelivery;
use App\Models\ReminderRecipient;
use App\Models\Site;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    User::factory()->create(['email' => 'admin@example.test', 'role' => User::ROLE_ADMIN]);
});

function reminderAsset(int $days, array $clientAttributes = [], array $siteAttributes = [], array $assetAttributes = []): Asset
{
    $client = Client::factory()->create($clientAttributes);
    $site = Site::factory()->for($client)->create($siteAttributes);

    return Asset::factory()->for($site)->create(['next_service_due' => today()->addDays($days)->toDateString()] + $assetAttributes);
}

function reminderCertificate(int $days, array $clientAttributes = [], array $siteAttributes = []): Certificate
{
    $client = Client::factory()->create($clientAttributes);
    $site = Site::factory()->for($client)->create($siteAttributes);

    return Certificate::factory()->for($site)->create(['expiry_date' => today()->addDays($days)->toDateString()]);
}

test('asset reminder windows send once and record a successful handoff', function (int $offset) {
    $this->travelTo(now()->setDate(2026, 10, 1)->startOfDay());
    $asset = reminderAsset($offset);
    Mail::fake();

    $this->artisan('fire-tracker:send-reminders')->assertExitCode(0);
    $this->artisan('fire-tracker:send-reminders')->assertExitCode(0);

    Mail::assertSent(AssetInspectionReminderMail::class, 1);
    $delivery = ReminderDelivery::query()->sole();
    expect($delivery->subject_id)->toBe($asset->id);
    expect($delivery->due_date->toDateString())->toBe($asset->next_service_due->toDateString());
    expect($delivery->reminder_offset)->toBe($offset);
    expect($delivery->status)->toBe('sent');
    expect($delivery->attempt_count)->toBe(1);
    expect($delivery->sent_at)->not->toBeNull();

    (new SendReminderEmail($delivery->id))->handle();
    Mail::assertSent(AssetInspectionReminderMail::class, 1);
})->with([14, 7, 1, 0]);

test('asset sends neither daily messages outside configured windows nor reminders for inactive records', function () {
    $this->travelTo(now()->setDate(2026, 10, 1)->startOfDay());
    reminderAsset(10);
    reminderAsset(7, ['is_active' => false]);
    reminderAsset(7, [], ['is_active' => false]);
    reminderAsset(7, [], [], ['status' => 'decommissioned']);
    reminderAsset(7, ['entered_in_error_at' => now()]);
    reminderAsset(7, [], ['entered_in_error_at' => now()]);
    reminderAsset(7, [], [], ['entered_in_error_at' => now()]);
    Mail::fake();

    $this->artisan('fire-tracker:send-reminders')->assertExitCode(0);

    Mail::assertNothingSent();
    $this->assertDatabaseCount('reminder_deliveries', 0);
});

test('client email is never required for admin reminders', function (string $email) {
    $this->travelTo(now()->setDate(2026, 10, 1)->startOfDay());
    reminderAsset(7, ['email' => $email]);
    Mail::fake();

    $this->artisan('fire-tracker:send-reminders')->assertExitCode(0);
    $this->artisan('fire-tracker:send-reminders')->assertExitCode(0);

    Mail::assertSent(AssetInspectionReminderMail::class, 1);
    expect(ReminderDelivery::query()->sole()->audience)->toBe('staff');
})->with(['', 'not-an-address']);

test('only verified admins receive fallback reminders without client email or phone', function () {
    $this->travelTo(now()->setDate(2026, 10, 1)->startOfDay());
    $asset = reminderAsset(7, ['email' => null, 'phone' => null]);
    $second = User::factory()->create(['email' => 'second@example.test', 'role' => User::ROLE_ADMIN]);
    User::factory()->create(['email' => 'regular@example.test']);
    User::factory()->unverified()->create(['email' => 'unverified@example.test', 'role' => User::ROLE_ADMIN]);
    Mail::fake();

    $this->artisan('fire-tracker:send-reminders')->assertExitCode(0);
    $this->artisan('fire-tracker:send-reminders')->assertExitCode(0);

    Mail::assertSent(AssetInspectionReminderMail::class, 2);
    Mail::assertSent(AssetInspectionReminderMail::class, fn (AssetInspectionReminderMail $mail) => $mail->hasTo('admin@example.test') && $mail->delivery->audience === 'staff');
    Mail::assertSent(AssetInspectionReminderMail::class, fn (AssetInspectionReminderMail $mail) => $mail->hasTo($second->email) && $mail->delivery->audience === 'staff');
    $this->assertDatabaseCount('reminder_deliveries', 2);
    expect(ReminderDelivery::query()->pluck('subject_id')->unique()->all())->toBe([$asset->id]);
    expect(ReminderDelivery::query()->pluck('audience')->unique()->all())->toBe(['staff']);
});

test('a configured email list replaces admins and client addresses', function () {
    $this->travelTo(now()->setDate(2026, 10, 1)->startOfDay());
    reminderAsset(7, ['email' => 'client@example.test', 'phone' => null]);
    User::factory()->create(['email' => 'regular@example.test']);
    ReminderRecipient::query()->create(['email' => 'chosen@example.test']);
    ReminderRecipient::query()->create(['email' => 'second@example.test']);
    Mail::fake();

    $this->artisan('fire-tracker:send-reminders')->assertExitCode(0);
    Mail::assertSent(AssetInspectionReminderMail::class, 2);
    expect(ReminderDelivery::query()->orderBy('id')->pluck('audience')->all())->toBe(['configured', 'configured']);
    Mail::assertNotSent(AssetInspectionReminderMail::class, fn (AssetInspectionReminderMail $mail) => $mail->hasTo('admin@example.test') || $mail->hasTo('client@example.test'));

    reminderCertificate(7, ['email' => 'client@example.test', 'phone' => null]);
    $this->artisan('fire-tracker:send-reminders')->assertExitCode(0);
    Mail::assertSent(CertificateExpiryReminderMail::class, 2);
    expect(ReminderDelivery::query()->where('reminder_type', 'certificate')->pluck('audience')->unique()->all())->toBe(['configured']);
});

test('client contact addresses are excluded from configured reminders', function () {
    $this->travelTo(now()->setDate(2026, 10, 1)->startOfDay());
    reminderAsset(7, ['email' => 'client@example.test']);
    ReminderRecipient::query()->create(['email' => 'CLIENT@example.test']);
    Mail::fake();

    $this->artisan('fire-tracker:send-reminders')->assertExitCode(0);

    Mail::assertNothingSent();
    $this->assertDatabaseCount('reminder_deliveries', 0);
    $this->actingAs(User::query()->where('email', 'admin@example.test')->sole())
        ->get(route('reminders.index'))
        ->assertInertia(fn (Assert $page) => $page->component('reminders')
            ->where('recipients.0.excluded', true)
            ->etc());
});

test('an admin address shared with a client contact cannot receive fallback reminders', function () {
    $this->travelTo(now()->setDate(2026, 10, 1)->startOfDay());
    reminderAsset(7, ['email' => 'ADMIN@example.test']);
    Mail::fake();

    $this->artisan('fire-tracker:send-reminders')->assertExitCode(0);

    Mail::assertNothingSent();
    $this->assertDatabaseCount('reminder_deliveries', 0);
    $this->actingAs(User::query()->where('email', 'admin@example.test')->sole())
        ->get(route('reminders.index'))
        ->assertInertia(fn (Assert $page) => $page->component('reminders')
            ->has('fallbackAdmins', 0)
            ->etc());
});

test('a queued recipient is skipped when their address becomes a client contact', function () {
    $this->travelTo(now()->setDate(2026, 10, 1)->startOfDay());
    $asset = reminderAsset(7, ['email' => null]);
    ReminderRecipient::query()->create(['email' => 'chosen@example.test']);
    config()->set('queue.default', 'database');
    Mail::fake();
    $this->artisan('fire-tracker:send-reminders')->assertExitCode(0);
    $delivery = ReminderDelivery::query()->sole();
    $asset->site->client->update(['email' => 'CHOSEN@example.test']);

    (new SendReminderEmail($delivery->id))->handle();

    Mail::assertNothingSent();
    expect($delivery->fresh()->status)->toBe('skipped');
});

test('queued staff delivery is skipped when user verification is revoked', function () {
    $this->travelTo(now()->setDate(2026, 10, 1)->startOfDay());
    reminderAsset(7, ['email' => null]);
    $user = User::query()->where('email', 'admin@example.test')->sole();
    config()->set('queue.default', 'database');
    Mail::fake();
    $this->artisan('fire-tracker:send-reminders')->assertExitCode(0);
    $user->forceFill(['email_verified_at' => null])->save();

    (new SendReminderEmail(ReminderDelivery::query()->sole()->id))->handle();

    Mail::assertNothingSent();
    expect(ReminderDelivery::query()->sole()->status)->toBe('skipped');
});

test('queued deliveries follow recipient changes before transport', function () {
    $this->travelTo(now()->setDate(2026, 10, 1)->startOfDay());
    reminderAsset(7, ['email' => 'client@example.test']);
    config()->set('queue.default', 'database');
    Mail::fake();
    $this->artisan('fire-tracker:send-reminders')->assertExitCode(0);
    $adminDelivery = ReminderDelivery::query()->sole();

    $chosen = ReminderRecipient::query()->create(['email' => 'chosen@example.test']);
    (new SendReminderEmail($adminDelivery->id))->handle();
    expect($adminDelivery->fresh()->status)->toBe('skipped');

    $this->artisan('fire-tracker:send-reminders')->assertExitCode(0);
    $chosenDelivery = ReminderDelivery::query()->where('audience', 'configured')->sole();
    $chosen->delete();
    (new SendReminderEmail($chosenDelivery->id))->handle();

    Mail::assertNothingSent();
    expect($chosenDelivery->fresh()->status)->toBe('skipped');

    $this->artisan('fire-tracker:send-reminders')->assertExitCode(0);
    (new SendReminderEmail($adminDelivery->id))->handle();
    Mail::assertSent(AssetInspectionReminderMail::class, fn (AssetInspectionReminderMail $mail) => $mail->hasTo('admin@example.test'));
    expect($adminDelivery->fresh()->status)->toBe('sent');
});

test('removing the last configured address restores verified admin fallback', function () {
    $this->travelTo(now()->setDate(2026, 10, 1)->startOfDay());
    reminderAsset(7, ['email' => 'client@example.test']);
    $chosen = ReminderRecipient::query()->create(['email' => 'chosen@example.test']);
    Mail::fake();
    $this->artisan('fire-tracker:send-reminders')->assertExitCode(0);
    $chosen->delete();

    $this->artisan('fire-tracker:send-reminders')->assertExitCode(0);

    Mail::assertSent(AssetInspectionReminderMail::class, 2);
    expect(ReminderDelivery::query()->orderBy('id')->pluck('audience')->all())->toBe(['configured', 'staff']);
});

test('a changed asset due date starts a new reminder cycle', function () {
    $this->travelTo(now()->setDate(2026, 10, 1)->startOfDay());
    $asset = reminderAsset(14);
    Mail::fake();
    $this->artisan('fire-tracker:send-reminders')->assertExitCode(0);

    $asset->update(['next_service_due' => '2026-12-15']);
    $this->travelTo(now()->setDate(2026, 12, 1)->startOfDay());
    $this->artisan('fire-tracker:send-reminders')->assertExitCode(0);

    Mail::assertSent(AssetInspectionReminderMail::class, 2);
    $this->assertDatabaseCount('reminder_deliveries', 2);
});

test('historical inspection dates do not drive reminders or change retained records', function () {
    $this->travelTo(now()->setDate(2026, 10, 1)->startOfDay());
    $asset = reminderAsset(7);
    $inspection = Inspection::factory()->for($asset)->create(['next_due_date' => '2026-10-15']);
    $certificate = Certificate::factory()->for($asset->site)->for($asset)->create(['expiry_date' => '2026-10-21']);
    Mail::fake();

    $this->artisan('fire-tracker:send-reminders')->assertExitCode(0);

    Mail::assertSent(AssetInspectionReminderMail::class, 1);
    Mail::assertNotSent(CertificateExpiryReminderMail::class);
    expect($asset->fresh()->next_service_due->toDateString())->toBe('2026-10-08');
    expect($inspection->fresh()->next_due_date->toDateString())->toBe('2026-10-15');
    expect($certificate->fresh()->expiry_date->toDateString())->toBe('2026-10-21');
});

test('certificate reminder windows send from expiry dates', function (int $offset) {
    $this->travelTo(now()->setDate(2026, 10, 1)->startOfDay());
    $certificate = reminderCertificate($offset);
    Mail::fake();

    $this->artisan('fire-tracker:send-reminders')->assertExitCode(0);

    Mail::assertSent(CertificateExpiryReminderMail::class, 1);
    $delivery = ReminderDelivery::query()->sole();
    expect($delivery->due_date->toDateString())->toBe($certificate->expiry_date->toDateString());
    expect($delivery->status)->toBe('sent');
})->with([30, 14, 7, 1, 0]);

test('certificate wrong window and a decommissioned linked asset do not send', function () {
    $this->travelTo(now()->setDate(2026, 10, 1)->startOfDay());
    reminderCertificate(20);
    $asset = reminderAsset(10, [], [], ['status' => 'decommissioned']);
    Certificate::factory()->for($asset->site)->for($asset)->create(['expiry_date' => '2026-10-08']);
    Mail::fake();

    $this->artisan('fire-tracker:send-reminders')->assertExitCode(0);

    Mail::assertNothingSent();
});

test('overdue reminders are sent once for each due date', function () {
    $this->travelTo(now()->setDate(2026, 10, 1)->startOfDay());
    reminderAsset(-3);
    reminderCertificate(-2);
    Mail::fake();

    $this->artisan('fire-tracker:send-reminders')->assertExitCode(0);
    $this->travelTo(now()->setDate(2026, 10, 2)->startOfDay());
    $this->artisan('fire-tracker:send-reminders')->assertExitCode(0);

    Mail::assertSent(AssetInspectionReminderMail::class, 1);
    Mail::assertSent(CertificateExpiryReminderMail::class, 1);
    $this->assertDatabaseCount('reminder_deliveries', 2);
});

test('mail content contains factual client site subject and due date information', function () {
    $this->travelTo(now()->setDate(2026, 10, 1)->startOfDay());
    reminderAsset(7, ['company_name' => 'Acme Fire Customer'], ['site_name' => 'Main Wharf']);
    Mail::fake();
    $this->artisan('fire-tracker:send-reminders')->assertExitCode(0);

    $mail = new AssetInspectionReminderMail(ReminderDelivery::query()->sole());
    $mail->assertSeeInHtml('Acme Fire Customer');
    $mail->assertSeeInHtml('Main Wharf');
    $mail->assertSeeInText('8 October 2026');
});

test('transport failure is recorded without marking delivery sent or exposing exception text', function () {
    $this->travelTo(now()->setDate(2026, 10, 1)->startOfDay());
    reminderAsset(7);
    Mail::shouldReceive('to')->once()->andThrow(new RuntimeException('smtp password secret'));

    $this->artisan('fire-tracker:send-reminders')->assertExitCode(0);

    $delivery = ReminderDelivery::query()->sole();
    expect($delivery->status)->toBe('failed');
    expect($delivery->sent_at)->toBeNull();
    expect($delivery->attempt_count)->toBe(1);
    expect($delivery->last_error)->not->toContain('secret');
});

test('a failed job can retry while a successful job cannot send again', function () {
    $this->travelTo(now()->setDate(2026, 10, 1)->startOfDay());
    reminderAsset(7);
    $fake = Mail::fake();
    Mail::shouldReceive('to')->once()->andThrow(new RuntimeException('Temporary SMTP failure'));
    $this->artisan('fire-tracker:send-reminders')->assertExitCode(0);
    $delivery = ReminderDelivery::query()->sole();
    expect($delivery->status)->toBe('failed');

    Mail::swap($fake);
    (new SendReminderEmail($delivery->id))->handle();
    (new SendReminderEmail($delivery->id))->handle();

    Mail::assertSent(AssetInspectionReminderMail::class, 1);
    expect($delivery->fresh()->status)->toBe('sent');
    expect($delivery->fresh()->attempt_count)->toBe(2);
});

test('job skips a record made inactive after discovery', function () {
    $this->travelTo(now()->setDate(2026, 10, 1)->startOfDay());
    $asset = reminderAsset(7);
    config()->set('queue.default', 'database');
    Mail::fake();
    $this->artisan('fire-tracker:send-reminders')->assertExitCode(0);
    $this->assertDatabaseCount('jobs', 1);
    $asset->site->client->update(['is_active' => false]);
    config()->set('queue.default', 'sync');

    (new SendReminderEmail(ReminderDelivery::query()->sole()->id))->handle();

    Mail::assertNothingSent();
    expect(ReminderDelivery::query()->sole()->status)->toBe('skipped');
});

test('database queue worker performs a pending reminder handoff', function () {
    $this->travelTo(now()->setDate(2026, 10, 1)->startOfDay());
    reminderAsset(7);
    config()->set('queue.default', 'database');
    Mail::fake();

    $this->artisan('fire-tracker:send-reminders')->assertExitCode(0);
    $this->assertDatabaseCount('jobs', 1);
    Mail::assertNothingSent();
    $this->artisan('queue:work', ['--once' => true, '--sleep' => 0])->assertExitCode(0);

    Mail::assertSent(AssetInspectionReminderMail::class, 1);
    expect(ReminderDelivery::query()->sole()->status)->toBe('sent');
});

test('stale processing is marked uncertain without an automatic resend', function () {
    $this->travelTo(now()->setDate(2026, 10, 1)->startOfDay());
    reminderAsset(7);
    config()->set('queue.default', 'database');
    Mail::fake();
    $this->artisan('fire-tracker:send-reminders')->assertExitCode(0);
    $delivery = ReminderDelivery::query()->sole();
    DB::table('reminder_deliveries')->where('id', $delivery->id)->update([
        'status' => 'processing', 'updated_at' => now()->subHours(3),
    ]);

    $this->artisan('fire-tracker:send-reminders')->assertExitCode(0);
    (new SendReminderEmail($delivery->id))->handle();

    Mail::assertNothingSent();
    expect($delivery->fresh()->status)->toBe('uncertain');
    expect($delivery->fresh()->last_error)->toContain('confirm the provider outcome');
});

test('database rejects a duplicate logical reminder identity', function () {
    $this->travelTo(now()->setDate(2026, 10, 1)->startOfDay());
    reminderAsset(7);
    Mail::fake();
    $this->artisan('fire-tracker:send-reminders')->assertExitCode(0);
    $attributes = ReminderDelivery::query()->sole()->getAttributes();
    unset($attributes['id']);

    expect(fn () => DB::table('reminder_deliveries')->insert($attributes))->toThrow(QueryException::class);
    $this->assertDatabaseCount('reminder_deliveries', 1);
});

test('reminder offsets can be changed through configuration', function () {
    $this->travelTo(now()->setDate(2026, 10, 1)->startOfDay());
    config()->set('fire-tracker.reminders.asset_offsets', [5]);
    reminderAsset(5);
    reminderAsset(7);
    Mail::fake();

    $this->artisan('fire-tracker:send-reminders')->assertExitCode(0);

    Mail::assertSent(AssetInspectionReminderMail::class, 1);
    expect(ReminderDelivery::query()->sole()->reminder_offset)->toBe(5);
});

test('certificate mail includes the related equipment when present', function () {
    $this->travelTo(now()->setDate(2026, 10, 1)->startOfDay());
    $asset = reminderAsset(20);
    Certificate::factory()->for($asset->site)->for($asset)->create(['expiry_date' => '2026-10-08']);
    Mail::fake();

    $this->artisan('fire-tracker:send-reminders')->assertExitCode(0);

    $mail = new CertificateExpiryReminderMail(ReminderDelivery::query()->sole());
    $mail->assertSeeInHtml($asset->assetType->type_name);
    $mail->assertSeeInText('8 October 2026');
});

test('history requires verification and filters paginated delivery records', function () {
    $this->travelTo(now()->setDate(2026, 10, 1)->startOfDay());
    reminderAsset(7);
    Mail::fake();
    $this->artisan('fire-tracker:send-reminders')->assertExitCode(0);
    $delivery = ReminderDelivery::query()->sole();

    $this->get(route('reminders.index'))->assertRedirect(route('login'));
    $this->actingAs(User::factory()->unverified()->create())->get(route('reminders.index'))
        ->assertRedirect(route('verification.notice'));
    auth()->logout();
    $this->actingAs(User::factory()->create())
        ->get(route('reminders.index', ['status' => 'sent', 'type' => 'asset', 'client_id' => $delivery->client_id]))
        ->assertInertia(fn (Assert $page) => $page->component('reminders')
            ->has('records.data', 1)
            ->where('records.data.0.id', $delivery->id)
            ->missing('filters.page')
            ->etc());
});

test('history date filters and stale pages stay server bounded', function () {
    $this->travelTo(now()->setDate(2026, 10, 1)->startOfDay());
    reminderAsset(7);
    reminderAsset(14);
    Mail::fake();
    $this->artisan('fire-tracker:send-reminders')->assertExitCode(0);
    $this->actingAs(User::factory()->create());

    $this->get(route('reminders.index', ['due_from' => '2026-10-08', 'due_to' => '2026-10-08']))
        ->assertInertia(fn (Assert $page) => $page->component('reminders')->has('records.data', 1)->etc());
    $this->get(route('reminders.index', ['due_from' => '2026-10-08', 'due_to' => '2026-10-08', 'page' => 9]))
        ->assertRedirect(route('reminders.index', ['due_from' => '2026-10-08', 'due_to' => '2026-10-08', 'page' => 1]));
    $this->get(route('reminders.index', ['due_from' => '2026-10-09', 'due_to' => '2026-10-08']))
        ->assertSessionHasErrors('due_to');
});

test('sample reminder command sends each internal design without a delivery record', function (string $type, string $mailClass) {
    $this->travelTo(now()->setDate(2026, 10, 1)->startOfDay());
    Mail::fake();

    $this->artisan('fire-tracker:test-reminder-email', ['type' => $type, 'email' => 'INTERNAL@example.test'])
        ->assertExitCode(0);

    Mail::assertSent($mailClass, function ($mail): bool {
        $mail->assertSeeInHtml('TEST COPY');
        $mail->assertSeeInHtml('Hello team');
        $mail->assertSeeInHtml('Waigani Office');

        return $mail->hasTo('internal@example.test') && $mail->preview
            && str_contains($mail->envelope()->subject, 'TEST COPY');
    });
    $this->assertDatabaseCount('reminder_deliveries', 0);
})->with([
    ['asset', AssetInspectionReminderMail::class],
    ['certificate', CertificateExpiryReminderMail::class],
]);

test('sample reminder command rejects invalid types and client contact addresses', function () {
    Client::factory()->create(['email' => 'client@example.test']);
    Mail::fake();

    $this->artisan('fire-tracker:test-reminder-email', ['type' => 'other', 'email' => 'internal@example.test'])
        ->assertExitCode(1);
    $this->artisan('fire-tracker:test-reminder-email', ['type' => 'asset', 'email' => 'not-an-email'])
        ->assertExitCode(1);
    $this->artisan('fire-tracker:test-reminder-email', ['type' => 'asset', 'email' => 'CLIENT@example.test'])
        ->assertExitCode(1);

    Mail::assertNothingSent();
    $this->assertDatabaseCount('reminder_deliveries', 0);
});

test('web preview and test email routes are removed', function () {
    $this->actingAs(User::factory()->create());

    $this->get('/reminders/templates/asset/preview')->assertNotFound();
    $this->post('/reminders/templates/asset/test-email')->assertNotFound();
    $this->get('/reminders/1/preview')->assertNotFound();
    $this->post('/reminders/1/test-email')->assertNotFound();
});

test('history audience filter summary and sidebar count show reminder activity', function () {
    $this->travelTo(now()->setDate(2026, 10, 1)->startOfDay());
    reminderAsset(7, ['email' => 'client@example.test']);
    ReminderRecipient::query()->create(['email' => 'chosen@example.test']);
    $viewer = User::factory()->create();
    Mail::fake();
    $this->artisan('fire-tracker:send-reminders')->assertExitCode(0);

    $this->actingAs($viewer)->get(route('reminders.index', ['audience' => 'configured']))
        ->assertInertia(fn (Assert $page) => $page->component('reminders')
            ->has('records.data', 1)
            ->where('records.data.0.audience', 'configured')
            ->where('summary.sent', 1)
            ->where('counts.reminders', 1)
            ->has('recipients', 1)
            ->etc());
});

test('a verified user can add and remove a normalized recipient from the reminders page', function () {
    $viewer = User::factory()->create();
    $this->actingAs($viewer);

    $this->post(route('reminders.recipients.store'), ['email' => '  CHOSEN@Example.Test  '])
        ->assertRedirect();
    $recipient = ReminderRecipient::query()->sole();
    expect($recipient->email)->toBe('chosen@example.test');
    $this->get(route('reminders.index'))
        ->assertInertia(fn (Assert $page) => $page->component('reminders')
            ->where('recipients.0.email', 'chosen@example.test')
            ->has('fallbackAdmins', 1)
            ->etc());

    $this->delete(route('reminders.recipients.destroy', $recipient))->assertRedirect();
    $this->assertDatabaseCount('reminder_recipients', 0);
});

test('recipient editor rejects invalid and duplicate addresses and requires verification', function () {
    $recipient = ReminderRecipient::query()->create(['email' => 'existing@example.test']);
    $this->post(route('reminders.recipients.store'), ['email' => 'chosen@example.test'])
        ->assertRedirect(route('login'));
    $this->delete(route('reminders.recipients.destroy', $recipient))
        ->assertRedirect(route('login'));
    $this->actingAs(User::factory()->unverified()->create())
        ->post(route('reminders.recipients.store'), ['email' => 'chosen@example.test'])
        ->assertRedirect(route('verification.notice'));
    $this->delete(route('reminders.recipients.destroy', $recipient))
        ->assertRedirect(route('verification.notice'));
    auth()->logout();
    $this->actingAs(User::factory()->create());
    $this->post(route('reminders.recipients.store'), ['email' => 'bad address'])
        ->assertSessionHasErrors('email');
    Client::factory()->create(['email' => 'client@example.test']);
    $this->post(route('reminders.recipients.store'), ['email' => 'CLIENT@example.test'])
        ->assertSessionHasErrors('email');
    $this->post(route('reminders.recipients.store'), ['email' => 'chosen@example.test'])
        ->assertRedirect();
    $this->post(route('reminders.recipients.store'), ['email' => 'CHOSEN@example.test'])
        ->assertSessionHasErrors('email');
    $this->assertDatabaseCount('reminder_recipients', 2);
});

test('seeded admin has the admin role while newly registered users default to regular role', function () {
    $regular = User::factory()->create();
    $this->seed(DatabaseSeeder::class);

    expect($regular->role)->toBe(User::ROLE_USER);
    expect(User::query()->where('email', 'test@test.com')
        ->where('role', User::ROLE_ADMIN)->exists())->toBeTrue();
});
