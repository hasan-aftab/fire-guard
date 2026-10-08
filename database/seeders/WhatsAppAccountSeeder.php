<?php

namespace Database\Seeders;

use App\Models\WhatsAppAccount;
use App\Models\WhatsAppTemplate;
use Illuminate\Database\Seeder;

/**
 * Recreates the Meta sandbox sending number and its two reminder template
 * mappings so `migrate:fresh --seed` restores a working test-send setup.
 *
 * Verification is deliberately never claimed. `last_verified_at` is what makes a
 * sender eligible to send, and only the verify route has actually spoken to
 * Meta, so a token written here leaves the account unverified for the admin to
 * confirm against live credentials.
 *
 * The access token reuses the existing WHATSAPP_APP_SECRET value in `.env` and is
 * never written into this file or into `.env.example`. Without it the seeder
 * refreshes the account's non-secret fields when the account already exists and
 * skips cleanly on a database that has never had one.
 */
class WhatsAppAccountSeeder extends Seeder
{
    private const PHONE_NUMBER_ID = '1311503608720043';

    public function run(): void
    {
        $existing = WhatsAppAccount::query()->where('phone_number_id', self::PHONE_NUMBER_ID)->first();
        $token = trim((string) config('services.whatsapp.app_secret', ''));

        if ($existing === null && $token === '') {
            $this->command->warn('WhatsApp sender skipped: set WHATSAPP_APP_SECRET in .env first.');

            return;
        }

        $data = [
            'name' => 'Web',
            'display_phone_number' => '+15556323967',
            'phone_number_id' => self::PHONE_NUMBER_ID,
            'waba_id' => '2054627991879504',
            'is_active' => true,
            'is_test' => true,
            'is_default' => false,
            'default_slot' => null,
        ];
        if ($token !== '') {
            $data['access_token'] = $token;
            $data['last_verified_at'] = null;
            $data['last_error'] = null;
        }

        if ($existing === null) {
            $account = WhatsAppAccount::query()->create($data);
        } else {
            $existing->update($data);
            $account = $existing;
        }

        foreach (['asset', 'certificate'] as $purpose) {
            WhatsAppTemplate::query()->updateOrCreate(
                ['waba_id' => $account->waba_id, 'purpose' => $purpose],
                ['name' => 'hello_world', 'language' => 'en_US', 'meta_status' => 'APPROVED',
                    'category' => 'UTILITY', 'parameter_keys' => [], 'is_enabled' => true,
                    'last_synced_at' => now(), 'last_error' => null],
            );
        }
    }
}
