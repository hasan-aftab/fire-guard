<?php

use App\Models\WhatsAppAccount;
use App\Models\WhatsAppTemplate;
use Database\Seeders\WhatsAppAccountSeeder;

test('whatsapp sender seeder stores the verified active account and both approved templates', function (): void {
    config()->set('services.whatsapp.app_secret', 'seeded-sender-token');
    $this->seed(WhatsAppAccountSeeder::class);
    $this->seed(WhatsAppAccountSeeder::class);

    $account = WhatsAppAccount::query()->sole();
    expect($account->name)->toBe('Web');
    expect($account->display_phone_number)->toBe('+15556323967');
    expect($account->phone_number_id)->toBe('1311503608720043');
    expect($account->waba_id)->toBe('2054627991879504');
    expect($account->access_token)->toBe('seeded-sender-token');
    expect($account->is_active)->toBeTrue();
    expect($account->is_test)->toBeTrue();
    expect($account->is_default)->toBeFalse();
    expect($account->default_slot)->toBeNull();
    expect($account->last_verified_at)->toBeNull();
    expect($account->last_error)->toBeNull();

    expect(WhatsAppTemplate::query()->count())->toBe(2);
    foreach (['asset', 'certificate'] as $purpose) {
        $template = WhatsAppTemplate::query()->where('waba_id', '2054627991879504')
            ->where('purpose', $purpose)->sole();
        expect($template->name)->toBe('hello_world');
        expect($template->language)->toBe('en_US');
        expect($template->meta_status)->toBe('APPROVED');
        expect($template->category)->toBe('UTILITY');
        expect($template->parameter_keys)->toBe([]);
        expect($template->is_enabled)->toBeTrue();
        expect($template->last_synced_at)->not->toBeNull();
        expect($template->last_error)->toBeNull();
    }
});

test('whatsapp sender seeder skips cleanly when no token and no account exist', function (): void {
    config()->set('services.whatsapp.app_secret', null);
    $this->seed(WhatsAppAccountSeeder::class);

    expect(WhatsAppAccount::query()->count())->toBe(0);
    expect(WhatsAppTemplate::query()->count())->toBe(0);
});

test('reseeded sender token clears a stale verification', function (): void {
    config()->set('services.whatsapp.app_secret', 'first-token');
    $this->seed(WhatsAppAccountSeeder::class);
    WhatsAppAccount::query()->sole()->update([
        'last_verified_at' => now(),
        'last_error' => 'Meta connection failed (code 190).',
    ]);

    config()->set('services.whatsapp.app_secret', 'rotated-token');
    $this->seed(WhatsAppAccountSeeder::class);

    $account = WhatsAppAccount::query()->sole();
    expect($account->access_token)->toBe('rotated-token');
    expect($account->last_verified_at)->toBeNull();
    expect($account->last_error)->toBeNull();
});

test('reseed without a token keeps an established verification', function (): void {
    config()->set('services.whatsapp.app_secret', 'only-token');
    $this->seed(WhatsAppAccountSeeder::class);
    $verifiedAt = now()->subMinutes(5)->startOfSecond();
    WhatsAppAccount::query()->sole()->update(['last_verified_at' => $verifiedAt]);

    config()->set('services.whatsapp.app_secret', null);
    $this->seed(WhatsAppAccountSeeder::class);

    $account = WhatsAppAccount::query()->sole();
    expect($account->access_token)->toBe('only-token');
    expect($account->last_verified_at->equalTo($verifiedAt))->toBeTrue();
});
