<?php

namespace Database\Seeders;

use App\Models\Asset;
use App\Models\AssetType;
use App\Models\Certificate;
use App\Models\Client;
use App\Models\Inspection;
use App\Models\Site;
use App\Models\Technician;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class FireTrackerDemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(AssetTypeSeeder::class);
        if (Client::exists() || Asset::exists()) {
            return;
        }
        DB::transaction(function (): void {
            $typeIds = AssetType::pluck('id', 'type_name');
            $sourceTypes = [1 => 'Fire Extinguisher - Dry Chemical', 2 => 'Fire Extinguisher - CO2', 3 => 'Smoke Alarm', 4 => 'Fire Alarm Panel', 5 => 'Sprinkler Head', 6 => 'Fire Hose Reel', 7 => 'Fire Hydrant', 8 => 'Emergency Exit Lighting', 9 => 'Fire Blanket'];
            $clients = [['id' => 1, 'company_name' => 'Harbourside Logistics Ltd', 'contact_name' => 'Peter Kaupa', 'phone' => '+675 7123 4501'],
                ['id' => 2, 'company_name' => 'Islander Hotel Group', 'contact_name' => 'Grace Robinson', 'phone' => '+675 7234 8890'],
                ['id' => 3, 'company_name' => 'Moresby Central Bank Tower', 'contact_name' => 'Simon Aitsi', 'phone' => '+675 7345 1122']];
            foreach ($clients as $attributes) {
                Client::unguarded(fn () => Client::create($attributes));
            }
            $sites = [['id' => 101, 'client_id' => 1, 'site_name' => 'Port Moresby Warehouse', 'address' => 'Section 45, Badili'],
                ['id' => 102, 'client_id' => 1, 'site_name' => 'Lae Depot', 'address' => 'Milfordhaven Rd, Lae'],
                ['id' => 103, 'client_id' => 2, 'site_name' => 'Waigani Tower Hotel', 'address' => 'Waigani Drive, Port Moresby'],
                ['id' => 104, 'client_id' => 3, 'site_name' => 'Central Tower - Main Branch', 'address' => 'Douglas St, Port Moresby']];
            foreach ($sites as $attributes) {
                Site::unguarded(fn () => Site::create($attributes));
            }
            $technicians = [['id' => 1, 'full_name' => 'Wilson Namaliu'],
                ['id' => 2, 'full_name' => 'Ruth Ovia'],
                ['id' => 3, 'full_name' => 'Michael Doa']];
            $technicianIds = [];
            foreach ($technicians as $attributes) {
                $sourceId = $attributes['id'];
                unset($attributes['id']);
                $technicianIds[$sourceId] = Technician::firstOrCreate(['full_name' => $attributes['full_name']], $attributes)->id;
            }
            $assets = [['id' => 1, 'site_id' => 101, 'asset_type_id' => 1, 'serial_number' => 'EX-2201', 'location_desc' => 'Loading bay 2', 'next_service_due' => '2026-09-20'],
                ['id' => 2, 'site_id' => 101, 'asset_type_id' => 6, 'serial_number' => 'HR-0093', 'location_desc' => 'West wall corridor', 'next_service_due' => '2026-10-15'],
                ['id' => 3, 'site_id' => 102, 'asset_type_id' => 3, 'serial_number' => 'SA-4471', 'location_desc' => 'Storage room A', 'next_service_due' => '2026-08-30'],
                ['id' => 4, 'site_id' => 103, 'asset_type_id' => 5, 'serial_number' => 'SP-1187', 'location_desc' => 'Level 3, room 312', 'next_service_due' => '2026-11-02'],
                ['id' => 5, 'site_id' => 103, 'asset_type_id' => 1, 'serial_number' => 'EX-2244', 'location_desc' => 'Kitchen', 'next_service_due' => '2026-09-14'],
                ['id' => 6, 'site_id' => 104, 'asset_type_id' => 8, 'serial_number' => 'EL-3392', 'location_desc' => 'Fire stairwell B', 'next_service_due' => '2026-09-05'],
                ['id' => 7, 'site_id' => 104, 'asset_type_id' => 7, 'serial_number' => 'HY-0021', 'location_desc' => 'Ground floor forecourt', 'next_service_due' => '2027-01-10'],
                ['id' => 8, 'site_id' => 101, 'asset_type_id' => 2, 'serial_number' => 'CO2-0112', 'location_desc' => 'Server room', 'next_service_due' => '2026-09-28'],
                ['id' => 9, 'site_id' => 102, 'asset_type_id' => 4, 'serial_number' => 'FAP-0055', 'location_desc' => 'Main control room', 'next_service_due' => '2026-10-10'],
                ['id' => 10, 'site_id' => 103, 'asset_type_id' => 9, 'serial_number' => 'FB-0078', 'location_desc' => 'Cafeteria kitchen', 'next_service_due' => '2026-09-17'],
                ['id' => 11, 'site_id' => 104, 'asset_type_id' => 1, 'serial_number' => 'EX-3301', 'location_desc' => 'Level 1 reception', 'next_service_due' => '2026-09-12'],
                ['id' => 12, 'site_id' => 101, 'asset_type_id' => 3, 'serial_number' => 'SA-5502', 'location_desc' => 'Office corridor', 'next_service_due' => '2026-09-22']];
            foreach ($assets as $attributes) {
                $attributes['asset_type_id'] = $typeIds[$sourceTypes[$attributes['asset_type_id']]];
                Asset::unguarded(fn () => Asset::create($attributes));
            }
            $inspections = [['id' => 1, 'asset_id' => 3, 'technician_id' => 1, 'inspection_date' => '2026-08-01', 'result' => 'pass', 'notes' => 'Battery replaced.'],
                ['id' => 2, 'asset_id' => 6, 'technician_id' => 2, 'inspection_date' => '2026-07-15', 'result' => 'needs_repair', 'notes' => 'Fitting cracked, ordered part.'],
                ['id' => 3, 'asset_id' => 1, 'technician_id' => 3, 'inspection_date' => '2026-06-20', 'result' => 'pass', 'notes' => null],
                ['id' => 4, 'asset_id' => 5, 'technician_id' => 1, 'inspection_date' => '2026-09-01', 'result' => 'fail', 'notes' => 'Pressure gauge below minimum.'],
                ['id' => 5, 'asset_id' => 10, 'technician_id' => 2, 'inspection_date' => '2026-08-20', 'result' => 'pass', 'notes' => 'All good.'],
                ['id' => 6, 'asset_id' => 11, 'technician_id' => 3, 'inspection_date' => '2026-09-10', 'result' => 'needs_repair', 'notes' => 'Mounting bracket loose.']];
            foreach ($inspections as $attributes) {
                $attributes['technician_id'] = $technicianIds[$attributes['technician_id']];
                Inspection::unguarded(fn () => Inspection::create($attributes));
            }
            $certificates = [['id' => 1, 'site_id' => 101, 'certificate_type' => 'Annual Fire Safety Statement', 'issue_date' => '2025-09-25', 'expiry_date' => '2026-09-25'],
                ['id' => 2, 'site_id' => 103, 'certificate_type' => 'Fire Alarm System Compliance', 'issue_date' => '2025-10-30', 'expiry_date' => '2026-10-30'],
                ['id' => 3, 'site_id' => 104, 'certificate_type' => 'Sprinkler System Certificate', 'issue_date' => '2025-09-10', 'expiry_date' => '2026-09-10'],
                ['id' => 4, 'site_id' => 102, 'certificate_type' => 'Annual Fire Safety Statement', 'issue_date' => '2026-02-14', 'expiry_date' => '2027-02-14'],
                ['id' => 5, 'site_id' => 101, 'certificate_type' => 'Electrical Safety Certificate', 'issue_date' => '2025-08-20', 'expiry_date' => '2026-08-20'],
                ['id' => 6, 'site_id' => 104, 'certificate_type' => 'Emergency Evacuation Plan', 'issue_date' => '2026-01-15', 'expiry_date' => '2026-09-30']];
            foreach ($certificates as $attributes) {
                Certificate::unguarded(fn () => Certificate::create($attributes));
            }
        });
    }
}
