<?php

namespace Database\Factories;

use App\Models\Asset;
use App\Models\AssetType;
use App\Models\Site;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Asset> */
class AssetFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['site_id' => Site::factory(), 'asset_type_id' => AssetType::factory(), 'serial_number' => fake()->bothify('EX-####'), 'location_desc' => 'Main entrance', 'next_service_due' => now()->addMonth()->toDateString(), 'status' => 'active'];
    }
}
