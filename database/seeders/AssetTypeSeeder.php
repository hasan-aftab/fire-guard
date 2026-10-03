<?php

namespace Database\Seeders;

use App\Models\AssetType;
use Illuminate\Database\Seeder;

class AssetTypeSeeder extends Seeder
{
    public function run(): void
    {
        if (AssetType::query()->exists()) {
            return;
        }

        foreach ([
            ['type_name' => 'Fire Extinguisher - Dry Chemical', 'default_interval_months' => 6],
            ['type_name' => 'Fire Extinguisher - CO2', 'default_interval_months' => 6],
            ['type_name' => 'Smoke Alarm', 'default_interval_months' => 12],
            ['type_name' => 'Fire Alarm Panel', 'default_interval_months' => 12],
            ['type_name' => 'Sprinkler Head', 'default_interval_months' => 12],
            ['type_name' => 'Fire Hose Reel', 'default_interval_months' => 6],
            ['type_name' => 'Fire Hydrant', 'default_interval_months' => 12],
            ['type_name' => 'Emergency Exit Lighting', 'default_interval_months' => 6],
            ['type_name' => 'Fire Blanket', 'default_interval_months' => 12],
        ] as $type) {
            AssetType::firstOrCreate(['type_name' => $type['type_name']], $type);
        }
    }
}
