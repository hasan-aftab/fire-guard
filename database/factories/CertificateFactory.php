<?php

namespace Database\Factories;

use App\Models\Certificate;
use App\Models\Site;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Certificate> */
class CertificateFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['site_id' => Site::factory(), 'certificate_type' => 'Annual Fire Safety Statement', 'issue_date' => now()->subYear()->toDateString(), 'expiry_date' => now()->addMonth()->toDateString()];
    }
}
