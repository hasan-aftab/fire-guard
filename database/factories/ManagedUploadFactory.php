<?php

namespace Database\Factories;

use App\Models\Inspection;
use App\Models\ManagedUpload;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ManagedUpload>
 */
class ManagedUploadFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'kind' => 'photo',
            'record_id' => Inspection::factory(),
            'storage_disk' => 'local',
            'original_name' => 'photo.jpg',
            'file_size' => 1024,
            'total_chunks' => 1,
            'next_chunk' => 0,
            'status' => 'pending',
            'expires_at' => now()->addHours(2),
        ];
    }
}
