<?php

namespace Database\Factories;

use App\Models\Asset;
use App\Models\Inspection;
use App\Models\Technician;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Inspection> */
class InspectionFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['asset_id' => Asset::factory(), 'technician_id' => Technician::factory(), 'inspection_date' => now()->toDateString(), 'result' => 'pass', 'notes' => 'Equipment inspected'];
    }
}
