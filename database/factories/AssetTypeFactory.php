<?php

namespace Database\Factories;

use App\Models\AssetType;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AssetType> */
class AssetTypeFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['type_name' => fake()->unique()->bothify('Equipment ###???'), 'default_interval_months' => 12];
    }
}
