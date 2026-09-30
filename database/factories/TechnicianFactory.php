<?php

namespace Database\Factories;

use App\Models\Technician;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Technician> */
class TechnicianFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['full_name' => fake()->name(), 'phone' => fake()->phoneNumber(), 'email' => fake()->safeEmail(), 'is_active' => true];
    }
}
