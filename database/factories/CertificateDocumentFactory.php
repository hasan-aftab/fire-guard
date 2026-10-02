<?php

namespace Database\Factories;

use App\Models\Certificate;
use App\Models\CertificateDocument;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CertificateDocument>
 */
class CertificateDocumentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'certificate_id' => Certificate::factory(),
            'storage_disk' => 'local',
            'storage_path' => 'fire-tracker/certificates/'.fake()->uuid().'.pdf',
            'original_name' => 'certificate.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 1024,
        ];
    }
}
