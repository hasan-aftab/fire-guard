<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\GeneratedReport;
use App\Models\User;
use App\ReportType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GeneratedReport>
 */
class GeneratedReportFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'report_type' => ReportType::Inspection,
            'client_id' => Client::factory(),
            'site_id' => null,
            'date_from' => '2026-09-01',
            'date_to' => '2026-09-30',
            'include_entered_in_error' => false,
            'storage_disk' => 'local',
            'storage_path' => 'fire-tracker/reports/'.fake()->uuid().'.pdf',
            'original_filename' => 'inspection-report.pdf',
            'file_size' => 4,
            'sha256' => hash('sha256', '%PDF'),
            'generated_by' => User::factory(),
            'generated_at' => now(),
            'metadata' => [
                'client_name' => 'Historical Client',
                'site_name' => '',
                'report_title' => 'Inspection Report',
                'generated_by_name' => 'Historical User',
            ],
        ];
    }
}
