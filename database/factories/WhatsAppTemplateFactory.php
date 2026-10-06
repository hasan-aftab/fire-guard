<?php

namespace Database\Factories;

use App\Models\WhatsAppTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WhatsAppTemplate>
 */
class WhatsAppTemplateFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'waba_id' => fake()->numerify('###############'),
            'purpose' => 'asset',
            'name' => 'test_asset_template',
            'language' => 'en_US',
            'meta_status' => 'APPROVED',
            'category' => 'UTILITY',
            'parameter_keys' => ['client_name', 'site_name', 'subject_name', 'due_date', 'timing'],
            'is_enabled' => true,
            'last_synced_at' => now(),
        ];
    }
}
