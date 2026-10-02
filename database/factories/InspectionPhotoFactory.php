<?php

namespace Database\Factories;

use App\Models\Inspection;
use App\Models\InspectionPhoto;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InspectionPhoto>
 */
class InspectionPhotoFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'inspection_id' => Inspection::factory(),
            'storage_disk' => 'local',
            'storage_path' => 'fire-tracker/inspections/'.fake()->uuid().'.jpg',
            'original_name' => 'photo.jpg',
            'mime_type' => 'image/jpeg',
            'file_size' => 1024,
        ];
    }
}
