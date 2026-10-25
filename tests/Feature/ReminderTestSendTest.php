<?php

use App\Mail\AssetInspectionReminderMail;
use App\Mail\BrevoTransport;
use App\Mail\CertificateExpiryReminderMail;
use App\Models\Client;
use App\Models\ReminderDelivery;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

beforeEach(function (): void {
    $this->travelTo(now()->setDate(2026, 10, 1)->startOfDay());
    $this->user = User::factory()->create();
    $this->unverified = User::factory()->unverified()->create();
});

test('authenticated verified user can send asset test email to internal address', function (): void {
    $this->actingAs($this->user);
    Mail::fake();
    $this->post(route('reminders.test-email'), ['type' => 'asset', 'email' => 'team@example.com'])
        ->assertRedirect();
    Mail::assertSent(AssetInspectionReminderMail::class, function ($mail): bool {
        expect($mail->preview)->toBeTrue();
        expect($mail->hasTo('team@example.com'))->toBeTrue();

        return true;
    });
    expect(ReminderDelivery::count())->toBe(0);
});

test('authenticated verified user can send certificate test email to internal address', function (): void {
    $this->actingAs($this->user);
    Mail::fake();
    $this->post(route('reminders.test-email'), ['type' => 'certificate', 'email' => 'team@example.com'])
        ->assertRedirect();
    Mail::assertSent(CertificateExpiryReminderMail::class, function ($mail): bool {
        expect($mail->preview)->toBeTrue();
        expect($mail->hasTo('team@example.com'))->toBeTrue();

        return true;
    });
    expect(ReminderDelivery::count())->toBe(0);
});

test('invalid type and email are rejected', function (): void {
    $this->actingAs($this->user);
    Mail::fake();
    $this->post(route('reminders.test-email'), ['type' => 'bad', 'email' => 'not-an-email'])
        ->assertSessionHasErrors(['type', 'email']);
    Mail::assertNothingSent();
    $this->post(route('reminders.test-email'), ['type' => 'asset', 'email' => 'test@example.com'.str_repeat('x', 300)])
        ->assertSessionHasErrors('email');
    Mail::assertNothingSent();
});

test('client contact email cannot be used for test send', function (): void {
    $this->actingAs($this->user);
    Mail::fake();
    Client::factory()->create(['email' => 'contact@example.test']);
    $this->post(route('reminders.test-email'), ['type' => 'asset', 'email' => 'contact@example.test'])
        ->assertSessionHasErrors('email');
    Mail::assertNothingSent();
});

test('test email writes nothing to reminder deliveries table', function (): void {
    $this->actingAs($this->user);
    Mail::fake();
    $this->post(route('reminders.test-email'), ['type' => 'certificate', 'email' => 'team@example.com'])
        ->assertRedirect();
    expect(ReminderDelivery::count())->toBe(0);
    $this->post(route('reminders.test-email'), ['type' => 'asset', 'email' => 'ops@example.com'])
        ->assertRedirect();
    expect(ReminderDelivery::count())->toBe(0);
});

test('guest is redirected to login and unverified user to verification notice', function (): void {
    Mail::fake();
    $this->post(route('reminders.test-email'), ['type' => 'asset', 'email' => 'team@example.com'])
        ->assertRedirect(route('login'));
    $this->actingAs($this->unverified);
    $this->post(route('reminders.test-email'), ['type' => 'asset', 'email' => 'team@example.com'])
        ->assertRedirect(route('verification.notice'));
    Mail::assertNothingSent();
});

test('test email succeeds and redirects back', function (): void {
    $this->actingAs($this->user);
    Mail::fake();
    $this->post(route('reminders.test-email'), ['type' => 'asset', 'email' => 'team@example.com'])
        ->assertRedirect();
    Mail::assertSent(AssetInspectionReminderMail::class);
});

test('brevo mailer sends the test reminder through the transactional API', function (): void {
    Http::fake(['api.brevo.com/*' => Http::response(['messageId' => 99], 201)]);
    config()->set('mail.default', 'brevo');
    config()->set('mail.mailers.brevo.key', 'test-api-key');
    config()->set('mail.from.address', 'firetracker@local.test');
    config()->set('mail.from.name', 'Fire Tracker');
    $this->actingAs($this->user);

    $this->post(route('reminders.test-email'), ['type' => 'asset', 'email' => 'team@example.com'])
        ->assertRedirect()
        ->assertInertiaFlash('toast', [
            'type' => 'success',
            'message' => 'Test reminder sent to team@example.com. It stays out of delivery history.',
        ]);

    Http::assertSent(function (Request $request): bool {
        return $request->url() === BrevoTransport::ENDPOINT
            && $request->hasHeader('api-key', 'test-api-key')
            && $request['sender']['email'] === 'firetracker@local.test'
            && $request['to'][0]['email'] === 'team@example.com'
            && str_starts_with((string) $request['subject'], 'TEST COPY')
            && str_contains((string) $request['htmlContent'], 'TEST COPY');
    });
    expect(ReminderDelivery::count())->toBe(0);
});
