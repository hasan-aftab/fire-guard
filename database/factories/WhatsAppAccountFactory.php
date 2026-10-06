<?php

namespace Database\Factories;

use App\Models\WhatsAppAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WhatsAppAccount>
 */
class WhatsAppAccountFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->company().' WhatsApp',
            'display_phone_number' => '+16505551234',
            'phone_number_id' => fake()->unique()->numerify('###############'),
            'waba_id' => fake()->numerify('###############'),
            'access_token' => 'test-only-access-token',
            'is_active' => true,
            'is_test' => false,
            'is_default' => false,
            'last_verified_at' => now(),
        ];
    }
}
