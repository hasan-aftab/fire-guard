<?php

namespace Database\Seeders;

use App\Models\AssetType;
use Illuminate\Database\Seeder;

class AssetTypeSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            ['type_name' => 'Fire Extinguisher - Dry Chemical', 'category' => 'Extinguisher', 'default_interval_months' => 6],
            ['type_name' => 'Fire Extinguisher - CO2', 'category' => 'Extinguisher', 'default_interval_months' => 6],
            ['type_name' => 'Smoke Alarm', 'category' => 'Alarm', 'default_interval_months' => 12],
            ['type_name' => 'Fire Alarm Panel', 'category' => 'Alarm', 'default_interval_months' => 12],
            ['type_name' => 'Sprinkler Head', 'category' => 'Sprinkler', 'default_interval_months' => 12],
            ['type_name' => 'Fire Hose Reel', 'category' => 'Hose Reel', 'default_interval_months' => 6],
            ['type_name' => 'Fire Hydrant', 'category' => 'Hydrant', 'default_interval_months' => 12],
            ['type_name' => 'Emergency Exit Lighting', 'category' => 'Lighting', 'default_interval_months' => 6],
            ['type_name' => 'Fire Blanket', 'category' => 'Blanket', 'default_interval_months' => 12],
        ] as $type) {
            AssetType::firstOrCreate(['type_name' => $type['type_name']], $type);
        }
    }
}
