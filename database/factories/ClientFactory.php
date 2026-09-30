<?php

namespace Database\Factories;

use App\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Client> */
class ClientFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['company_name' => fake()->company(), 'contact_name' => fake()->name(), 'phone' => fake()->phoneNumber(), 'email' => fake()->safeEmail(), 'is_active' => true];
    }
}
