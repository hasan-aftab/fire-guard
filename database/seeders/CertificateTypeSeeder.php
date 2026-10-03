<?php

namespace Database\Seeders;

use App\Models\CertificateType;
use Illuminate\Database\Seeder;

class CertificateTypeSeeder extends Seeder
{
    public function run(): void
    {
        if (CertificateType::query()->exists()) {
            return;
        }

        foreach ([
            'Annual Fire Safety Statement',
            'Fire Alarm System Compliance',
            'Sprinkler System Certificate',
            'Electrical Safety Certificate',
            'Emergency Evacuation Plan',
        ] as $name) {
            CertificateType::firstOrCreate(['name' => $name]);
        }
    }
}
