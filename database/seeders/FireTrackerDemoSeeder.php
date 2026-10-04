<?php

namespace Database\Seeders;

use App\Actions\SyncFireNotifications;
use App\Models\Asset;
use App\Models\AssetType;
use App\Models\Certificate;
use App\Models\CertificateDocument;
use App\Models\CertificateType;
use App\Models\Client;
use App\Models\FireNotification;
use App\Models\GeneratedReport;
use App\Models\Inspection;
use App\Models\InspectionPhoto;
use App\Models\ManagedUpload;
use App\Models\ReminderDelivery;
use App\Models\ReminderRecipient;
use App\Models\Site;
use App\Models\Technician;
use App\Models\User;
use App\ReportType;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class FireTrackerDemoSeeder extends Seeder
{
    public function run(SyncFireNotifications $notifications): void
    {
        $this->call([
            AssetTypeSeeder::class,
            CertificateTypeSeeder::class,
        ]);

        $demoAlreadyExists = Client::query()
            ->where('company_name', 'Harbourside Logistics Ltd')
            ->exists();

        if ((Client::query()->exists() || Asset::query()->exists()) && ! $demoAlreadyExists) {
            return;
        }

        DB::transaction(function () use ($notifications): void {
            $assetTypes = AssetType::query()->pluck('id', 'type_name')->all();
            $certificateTypes = CertificateType::query()->pluck('id', 'name')->all();
            $clients = $this->seedClients();
            $sites = $this->seedSites($clients);
            $technicians = $this->seedTechnicians();
            $assets = $this->seedAssets($sites, $assetTypes);
            $inspections = $this->seedInspections($assets, $technicians);
            $certificates = $this->seedCertificates($sites, $assets, $certificateTypes);
            $user = User::query()->first() ?? User::factory()->create();

            $imageSize = $this->seedFiles($inspections, $certificates);
            $this->seedManagedUpload($user, $inspections['needs-repair'], $imageSize);
            $this->seedReports($clients, $sites, $user);
            $this->seedReminderHistory($assets, $certificates);

            $notifications->handleAll();
            FireNotification::query()->where('user_id', $user->id)->orderBy('id')->limit(1)
                ->update(['is_read' => true]);
        });
    }

    /** @return array<string, Client> */
    private function seedClients(): array
    {
        $clients = [];

        foreach ([
            'harbourside' => [
                'company_name' => 'Harbourside Logistics Ltd',
                'contact_name' => 'Peter Kaupa',
                'phone' => '+675 7123 4501',
                'email' => 'peter.kaupa@example.test',
                'billing_address' => 'PO Box 1042, Port Moresby, NCD',
                'notes' => 'Primary logistics client. Gate access required after 5pm.',
                'is_active' => true,
                'entered_in_error_at' => null,
            ],
            'islander' => [
                'company_name' => 'Islander Hotel Group',
                'contact_name' => 'Grace Robinson',
                'phone' => '+675 7234 8890',
                'email' => null,
                'billing_address' => null,
                'notes' => null,
                'is_active' => true,
                'entered_in_error_at' => null,
            ],
            'central-bank' => [
                'company_name' => 'Moresby Central Bank Tower',
                'contact_name' => 'Simon Aitsi',
                'phone' => '+675 7345 1122',
                'email' => 'simon.aitsi@example.test',
                'billing_address' => 'Douglas Street, Port Moresby, NCD',
                'notes' => 'Retained historical client record.',
                'is_active' => false,
                'entered_in_error_at' => now()->subDays(2),
            ],
        ] as $key => $attributes) {
            $clients[$key] = Client::unguarded(fn (): Client => Client::query()->updateOrCreate(
                ['company_name' => $attributes['company_name']],
                $attributes,
            ));
        }

        return $clients;
    }

    /**
     * @param  array<string, Client>  $clients
     * @return array<string, Site>
     */
    private function seedSites(array $clients): array
    {
        $sites = [];

        foreach ([
            'warehouse' => [
                'client' => 'harbourside',
                'site_name' => 'Port Moresby Warehouse',
                'address' => 'Section 45, Badili',
                'suburb_region' => 'Badili, NCD',
                'access_notes' => 'Collect a visitor pass from the loading bay office.',
                'site_contact' => 'Peter Kaupa',
                'site_phone' => '+675 7123 4501',
                'is_active' => true,
                'entered_in_error_at' => null,
            ],
            'depot' => [
                'client' => 'harbourside',
                'site_name' => 'Lae Depot',
                'address' => 'Milfordhaven Road, Lae',
                'suburb_region' => 'Lae, Morobe',
                'access_notes' => null,
                'site_contact' => null,
                'site_phone' => null,
                'is_active' => true,
                'entered_in_error_at' => null,
            ],
            'hotel' => [
                'client' => 'islander',
                'site_name' => 'Waigani Tower Hotel',
                'address' => 'Waigani Drive, Port Moresby',
                'suburb_region' => 'Waigani, NCD',
                'access_notes' => 'Check in with hotel security.',
                'site_contact' => 'Grace Robinson',
                'site_phone' => '+675 7234 8890',
                'is_active' => true,
                'entered_in_error_at' => null,
            ],
            'bank' => [
                'client' => 'central-bank',
                'site_name' => 'Central Tower - Main Branch',
                'address' => 'Douglas Street, Port Moresby',
                'suburb_region' => 'Town, NCD',
                'access_notes' => 'Historical access instructions.',
                'site_contact' => 'Simon Aitsi',
                'site_phone' => '+675 7345 1122',
                'is_active' => false,
                'entered_in_error_at' => now()->subDay(),
            ],
        ] as $key => $attributes) {
            $client = $clients[$attributes['client']];
            unset($attributes['client']);
            $attributes['client_id'] = $client->id;

            $sites[$key] = Site::unguarded(fn (): Site => Site::query()->updateOrCreate(
                [
                    'client_id' => $client->id,
                    'site_name' => $attributes['site_name'],
                ],
                $attributes,
            ));
        }

        return $sites;
    }

    /** @return array<string, Technician> */
    private function seedTechnicians(): array
    {
        $technicians = [];

        foreach ([
            'wilson' => [
                'full_name' => 'Wilson Namaliu',
                'phone' => '+675 7011 2211',
                'email' => 'wilson.namaliu@example.test',
                'license_number' => 'PNG-FS-1024',
                'license_expiry' => today()->addYear()->toDateString(),
                'is_active' => true,
                'entered_in_error_at' => null,
            ],
            'ruth' => [
                'full_name' => 'Ruth Ovia',
                'phone' => '+675 7022 3311',
                'email' => 'ruth.ovia@example.test',
                'license_number' => 'PNG-FS-2048',
                'license_expiry' => today()->subDay()->toDateString(),
                'is_active' => true,
                'entered_in_error_at' => null,
            ],
            'michael' => [
                'full_name' => 'Michael Doa',
                'phone' => null,
                'email' => null,
                'license_number' => null,
                'license_expiry' => null,
                'is_active' => false,
                'entered_in_error_at' => now()->subDays(3),
            ],
        ] as $key => $attributes) {
            $technicians[$key] = Technician::unguarded(fn (): Technician => Technician::query()->updateOrCreate(
                ['full_name' => $attributes['full_name']],
                $attributes,
            ));
        }

        return $technicians;
    }

    /**
     * @param  array<string, Site>  $sites
     * @param  array<string, int>  $assetTypes
     * @return array<string, Asset>
     */
    private function seedAssets(array $sites, array $assetTypes): array
    {
        $today = today();
        $assets = [];

        foreach ([
            'overdue' => ['warehouse', 'Fire Extinguisher - Dry Chemical', 'EX-2201', 'Loading bay 2', -10, 'active', null],
            'due-soon' => ['warehouse', 'Fire Hose Reel', 'HR-0093', 'West wall corridor', 7, 'active', null],
            'unknown' => ['depot', 'Smoke Alarm', 'SA-4471', 'Storage room A', null, 'active', null],
            'ok' => ['hotel', 'Fire Alarm Panel', 'FAP-0055', 'Main control room', 20, 'active', null],
            'due-today' => ['hotel', 'Sprinkler Head', 'SP-1187', 'Level 3, room 312', 0, 'active', null],
            'decommissioned' => ['bank', 'Emergency Exit Lighting', 'EL-3392', 'Fire stairwell B', -4, 'decommissioned', null],
            'entered-in-error' => ['depot', 'Fire Hydrant', 'HY-0021', 'Ground floor forecourt', 30, 'active', now()->subDay()],
            'co2' => ['warehouse', 'Fire Extinguisher - CO2', 'CO2-0112', 'Server room', 1, 'active', null],
            'panel' => ['depot', 'Fire Alarm Panel', 'EX-3301', 'Main control room', 45, 'active', null],
            'blanket' => ['hotel', 'Fire Blanket', 'FB-0078', 'Cafeteria kitchen', 14, 'active', null],
            'extinguisher' => ['hotel', 'Fire Extinguisher - Dry Chemical', 'EX-2244', 'Kitchen', -2, 'active', null],
            'alarm' => ['warehouse', 'Smoke Alarm', 'SA-5502', 'Office corridor', null, 'active', null],
            'unnumbered' => ['depot', 'Smoke Alarm', null, 'Plant room north wall', null, 'active', null],
        ] as $key => [$siteKey, $typeName, $serial, $location, $dueOffset, $status, $enteredInErrorAt]) {
            $dueDate = $dueOffset === null ? null : $today->copy()->addDays($dueOffset)->toDateString();
            $attributes = [
                'site_id' => $sites[$siteKey]->id,
                'asset_type_id' => $assetTypes[$typeName],
                'serial_number' => $serial,
                'location_desc' => $location,
                'install_date' => $today->copy()->subYears(2)->toDateString(),
                'last_service_date' => $today->copy()->subMonths(6)->toDateString(),
                'next_service_due' => $dueDate,
                'status' => $status,
                'entered_in_error_at' => $enteredInErrorAt,
            ];

            $identity = $serial === null
                ? [
                    'site_id' => $attributes['site_id'],
                    'asset_type_id' => $attributes['asset_type_id'],
                    'location_desc' => $location,
                ]
                : ['serial_number' => $serial];

            $assets[$key] = Asset::unguarded(fn (): Asset => Asset::query()->updateOrCreate(
                $identity,
                $attributes,
            ));
        }

        AssetType::query()->where('type_name', 'Fire Blanket')->update(['is_active' => false]);

        return $assets;
    }

    /**
     * @param  array<string, Asset>  $assets
     * @param  array<string, Technician>  $technicians
     * @return array<string, Inspection>
     */
    private function seedInspections(array $assets, array $technicians): array
    {
        $today = today();
        $inspections = [];

        foreach ([
            'pass' => ['unknown', 'wilson', '2026-08-01', 'pass', 'Battery replaced; unit tested successfully.', 180, null],
            'needs-repair' => ['due-soon', 'ruth', '2026-09-22', 'needs_repair', 'Fitting cracked; replacement part ordered.', 150, null],
            'legacy-pass' => ['overdue', 'michael', '2026-06-20', 'pass', null, null, null],
            'fail' => ['extinguisher', 'wilson', '2026-09-01', 'fail', 'Pressure gauge is below the minimum operating range.', 14, null],
            'hotel-pass' => ['blanket', 'ruth', '2026-08-20', 'pass', 'All checks passed.', 320, null],
            'historical-repair' => ['decommissioned', 'michael', '2026-07-15', 'needs_repair', 'Mounting bracket loose.', null, null],
            'entered-in-error' => ['panel', 'michael', '2026-09-10', 'needs_repair', 'Duplicate record entered during migration.', 30, now()->subDays(4)],
            'unassigned' => ['co2', null, '2026-09-28', 'pass', null, null, null],
        ] as $key => [$assetKey, $technicianKey, $inspectionDate, $result, $notes, $nextDueOffset, $enteredInErrorAt]) {
            $asset = $assets[$assetKey];
            $attributes = [
                'asset_id' => $asset->id,
                'technician_id' => $technicianKey === null ? null : $technicians[$technicianKey]->id,
                'inspection_date' => $inspectionDate,
                'result' => $result,
                'notes' => $notes,
                'next_due_date' => $nextDueOffset === null
                    ? null
                    : $today->copy()->addDays($nextDueOffset)->toDateString(),
                'entered_in_error_at' => $enteredInErrorAt,
            ];

            $inspections[$key] = Inspection::unguarded(fn (): Inspection => Inspection::query()->updateOrCreate(
                [
                    'asset_id' => $asset->id,
                    'inspection_date' => $attributes['inspection_date'],
                ],
                $attributes,
            ));
        }

        return $inspections;
    }

    /**
     * @param  array<string, Site>  $sites
     * @param  array<string, Asset>  $assets
     * @param  array<string, int>  $certificateTypes
     * @return array<string, Certificate>
     */
    private function seedCertificates(array $sites, array $assets, array $certificateTypes): array
    {
        $certificates = [];

        foreach ([
            'expired' => ['warehouse', null, 'Annual Fire Safety Statement', '2025-09-25', '2026-09-25', 'Fire Safety Authority', null, null],
            'expiring' => ['hotel', null, 'Fire Alarm System Compliance', '2025-10-30', '2026-10-30', 'PNG Fire Service', null, null],
            'historical' => ['bank', null, 'Sprinkler System Certificate', '2025-09-10', '2026-09-10', 'Previous provider', null, null],
            'valid' => ['depot', null, 'Annual Fire Safety Statement', '2026-02-14', '2027-02-14', null, 'Annual Fire Safety Statement (legacy)', null],
            'linked' => ['warehouse', 'due-soon', 'Electrical Safety Certificate', '2025-08-20', '2026-08-20', 'Pacific Electrical', null, null],
            'entered-in-error' => ['bank', null, 'Emergency Evacuation Plan', '2026-01-15', '2026-09-30', 'Fire Safety Authority', null, now()->subDays(2)],
        ] as $key => [$siteKey, $assetKey, $typeName, $issueDate, $expiryDate, $issuedBy, $legacyType, $enteredInErrorAt]) {
            $attributes = [
                'site_id' => $sites[$siteKey]->id,
                'asset_id' => $assetKey === null ? null : $assets[$assetKey]->id,
                'certificate_type_id' => $certificateTypes[$typeName],
                'legacy_certificate_type' => $legacyType,
                'issue_date' => $issueDate,
                'expiry_date' => $expiryDate,
                'issued_by' => $issuedBy,
                'entered_in_error_at' => $enteredInErrorAt,
            ];

            $certificates[$key] = Certificate::unguarded(fn (): Certificate => Certificate::query()->updateOrCreate(
                [
                    'site_id' => $attributes['site_id'],
                    'certificate_type_id' => $attributes['certificate_type_id'],
                    'issue_date' => $attributes['issue_date'],
                ],
                $attributes,
            ));
        }

        CertificateType::query()->where('name', 'Emergency Evacuation Plan')->update(['is_active' => false]);

        return $certificates;
    }

    /**
     * @param  array<string, Inspection>  $inspections
     * @param  array<string, Certificate>  $certificates
     */
    private function seedFiles(array $inspections, array $certificates): int
    {
        $diskName = (string) config('fire-tracker.disk');
        $disk = Storage::disk($diskName);
        $inspection = $inspections['needs-repair'];
        $photoSize = 0;

        foreach ([
            'repair-evidence.jpg' => 'Damaged hose fitting',
            'extinguisher-condition.jpg' => 'Extinguisher inspection',
            'replacement-part.jpg' => 'Replacement fitting required',
        ] as $filename => $label) {
            $photoPath = "fire-tracker/demo/inspections/{$filename}";
            $image = $this->demoImage($label);
            $photoSize = max($photoSize, $this->putDemoFile($disk, $photoPath, $image));

            InspectionPhoto::unguarded(fn (): InspectionPhoto => InspectionPhoto::query()->updateOrCreate(
                ['storage_path' => $photoPath],
                [
                    'inspection_id' => $inspection->id,
                    'storage_disk' => $diskName,
                    'original_name' => $filename,
                    'mime_type' => 'image/jpeg',
                    'file_size' => strlen($image),
                    'pending_deletion_at' => null,
                ],
            ));
        }

        $removedImage = $this->demoImage('Archived inspection photo');

        $oldPhotoPath = 'fire-tracker/demo/inspections/removed-evidence.jpg';
        $oldPhotoSize = $this->putDemoFile($disk, $oldPhotoPath, $removedImage);
        InspectionPhoto::unguarded(fn (): InspectionPhoto => InspectionPhoto::query()->updateOrCreate(
            ['storage_path' => $oldPhotoPath],
            [
                'inspection_id' => $inspections['pass']->id,
                'storage_disk' => $diskName,
                'original_name' => 'removed-evidence.jpg',
                'mime_type' => 'image/jpeg',
                'file_size' => $oldPhotoSize,
                'pending_deletion_at' => now()->subDay(),
            ],
        ));

        $imageDocument = $this->demoImage('Fire alarm compliance evidence');
        $imageDocumentPath = 'fire-tracker/demo/certificates/fire-alarm-compliance.jpg';
        $imageDocumentSize = $this->putDemoFile($disk, $imageDocumentPath, $imageDocument);
        CertificateDocument::unguarded(fn (): CertificateDocument => CertificateDocument::query()->updateOrCreate(
            ['storage_path' => $imageDocumentPath],
            [
                'certificate_id' => $certificates['expiring']->id,
                'storage_disk' => $diskName,
                'original_name' => 'fire-alarm-compliance.jpg',
                'mime_type' => 'image/jpeg',
                'file_size' => $imageDocumentSize,
                'pending_deletion_at' => null,
            ],
        ));

        foreach ([
            'expired' => 'Expired annual fire safety statement',
            'historical' => 'Sprinkler system certificate',
            'valid' => 'Current annual fire safety statement',
            'linked' => 'Electrical safety certificate',
            'entered-in-error' => 'Emergency evacuation plan',
        ] as $certificateKey => $title) {
            $filename = str_replace(' ', '-', strtolower($title)).'.pdf';
            $documentPath = "fire-tracker/demo/certificates/{$filename}";
            $pdf = $this->demoPdf($title);
            $documentSize = $this->putDemoFile($disk, $documentPath, $pdf);

            CertificateDocument::unguarded(fn (): CertificateDocument => CertificateDocument::query()->updateOrCreate(
                ['storage_path' => $documentPath],
                [
                    'certificate_id' => $certificates[$certificateKey]->id,
                    'storage_disk' => $diskName,
                    'original_name' => $filename,
                    'mime_type' => 'application/pdf',
                    'file_size' => $documentSize,
                    'pending_deletion_at' => null,
                ],
            ));
        }

        $pdf = $this->demoPdf('Superseded annual fire safety statement');
        $oldDocumentPath = 'fire-tracker/demo/certificates/superseded-certificate.pdf';
        $oldDocumentSize = $this->putDemoFile($disk, $oldDocumentPath, $pdf);
        CertificateDocument::unguarded(fn (): CertificateDocument => CertificateDocument::query()->updateOrCreate(
            ['storage_path' => $oldDocumentPath],
            [
                'certificate_id' => $certificates['valid']->id,
                'storage_disk' => $diskName,
                'original_name' => 'superseded-certificate.pdf',
                'mime_type' => 'application/pdf',
                'file_size' => $oldDocumentSize,
                'pending_deletion_at' => now()->subDay(),
            ],
        ));

        return $photoSize;
    }

    private function demoImage(string $label): string
    {
        if (! function_exists('imagecreatetruecolor')) {
            throw new RuntimeException('GD is required to create the demo inspection images.');
        }

        $image = imagecreatetruecolor(1800, 1200);
        if ($image === false) {
            throw new RuntimeException('The demo inspection image could not be created.');
        }

        $background = $this->allocateImageColor($image, 112, 10, 16);
        $body = $this->allocateImageColor($image, 220, 28, 35);
        $shadow = $this->allocateImageColor($image, 72, 5, 10);
        $labelColor = $this->allocateImageColor($image, 255, 244, 225);
        $textureColors = [];

        for ($index = 0; $index < 64; $index++) {
            $textureColors[] = $this->allocateImageColor(
                $image,
                100 + ($index % 8) * 5,
                min(255, max(0, 4 + intdiv($index, 8) * 2)),
                10 + ($index % 4) * 4,
            );
        }

        for ($y = 0; $y < 1200; $y += 8) {
            for ($x = 0; $x < 1800; $x += 8) {
                $textureIndex = ($x * 17 + $y * 31 + (($x * $y) % 97)) % 64;
                imagefilledrectangle(
                    $image,
                    $x,
                    $y,
                    $x + 7,
                    $y + 7,
                    $textureColors[$textureIndex],
                );
            }
        }

        imagefilledrectangle($image, 810, 230, 1120, 1000, $shadow);
        imagefilledrectangle($image, 835, 250, 1095, 980, $body);
        imagefilledellipse($image, 965, 270, 260, 90, $body);
        imagefilledrectangle($image, 905, 180, 1025, 250, $shadow);
        imagefilledrectangle($image, 985, 130, 1150, 185, $labelColor);
        imagefilledrectangle($image, 855, 390, 1075, 660, $labelColor);
        imagestring($image, 5, 875, 430, 'FIRE', $shadow);
        imagestring($image, 4, 875, 475, 'SAFETY', $shadow);
        imagestring($image, 3, 110, 1040, strtoupper($label), $labelColor);

        ob_start();
        try {
            if (! imagejpeg($image, null, 88)) {
                throw new RuntimeException('The demo inspection image could not be encoded.');
            }

            $contents = ob_get_contents();
            if (! is_string($contents)) {
                throw new RuntimeException('The demo inspection image bytes could not be read.');
            }

            return $contents;
        } finally {
            ob_end_clean();
            imagedestroy($image);
        }
    }

    /**
     * @param  int<0, 255>  $red
     * @param  int<0, 255>  $green
     * @param  int<0, 255>  $blue
     */
    private function allocateImageColor(\GdImage $image, int $red, int $green, int $blue): int
    {
        $color = imagecolorallocate($image, $red, $green, $blue);
        if ($color === false) {
            throw new RuntimeException('A color could not be allocated for the demo image.');
        }

        return $color;
    }

    private function seedManagedUpload(User $user, Inspection $inspection, int $fileSize): void
    {
        $diskName = (string) config('fire-tracker.disk');
        $uploadId = 'd8f6caa2-2012-4e9c-a4b3-fce2b26a0f60';

        ManagedUpload::unguarded(fn (): ManagedUpload => ManagedUpload::query()->updateOrCreate(
            ['id' => $uploadId],
            [
                'user_id' => $user->id,
                'kind' => 'photo',
                'record_id' => $inspection->id,
                'storage_disk' => $diskName,
                'original_name' => 'unfinished-inspection-photo.png',
                'file_size' => $fileSize,
                'total_chunks' => 1,
                'next_chunk' => 0,
                'status' => 'pending',
                'expires_at' => now()->addHours(2),
            ],
        ));
    }

    /**
     * @param  array<string, Client>  $clients
     * @param  array<string, Site>  $sites
     */
    private function seedReports(array $clients, array $sites, User $user): void
    {
        $diskName = (string) (config('fire-tracker.report.disk') ?: config('fire-tracker.disk'));
        $disk = Storage::disk($diskName);
        $today = today();
        $originalInspectionReport = null;
        $reportIndex = 0;

        foreach ([
            'inspection-original' => [
                'type' => ReportType::Inspection,
                'client' => 'harbourside',
                'site' => 'warehouse',
                'include_entered_in_error' => false,
                'regenerated_from_id' => null,
            ],
            'inspection-regenerated' => [
                'type' => ReportType::Inspection,
                'client' => 'harbourside',
                'site' => 'warehouse',
                'include_entered_in_error' => false,
                'regenerated_from_id' => 'inspection-original',
            ],
            'service' => [
                'type' => ReportType::Service,
                'client' => 'harbourside',
                'site' => 'depot',
                'include_entered_in_error' => false,
                'regenerated_from_id' => null,
            ],
            'maintenance' => [
                'type' => ReportType::Maintenance,
                'client' => 'islander',
                'site' => 'hotel',
                'include_entered_in_error' => false,
                'regenerated_from_id' => null,
            ],
            'audit' => [
                'type' => ReportType::Audit,
                'client' => 'central-bank',
                'site' => null,
                'include_entered_in_error' => true,
                'regenerated_from_id' => null,
            ],
        ] as $key => $definition) {
            $client = $clients[$definition['client']];
            $site = $definition['site'] === null ? null : $sites[$definition['site']];
            $type = $definition['type'];
            $path = "fire-tracker/demo/reports/{$key}.pdf";
            $bytes = $this->demoPdf($type->title());
            $size = $this->putDemoFile($disk, $path, $bytes);
            $sourceKey = $definition['regenerated_from_id'];
            $generatedAt = now()->subDays($reportIndex++);

            $report = GeneratedReport::unguarded(fn (): GeneratedReport => GeneratedReport::query()->updateOrCreate(
                ['storage_path' => $path],
                [
                    'report_type' => $type,
                    'client_id' => $client->id,
                    'site_id' => $site?->id,
                    'date_from' => $today->copy()->subMonth()->toDateString(),
                    'date_to' => $today->toDateString(),
                    'include_entered_in_error' => $definition['include_entered_in_error'],
                    'storage_disk' => $diskName,
                    'original_filename' => "{$key}-report.pdf",
                    'file_size' => $size,
                    'sha256' => hash('sha256', $bytes),
                    'generated_by' => $user->id,
                    'generated_at' => $generatedAt,
                    'metadata' => [
                        'client_name' => $client->company_name,
                        'site_name' => $site === null ? '' : $site->site_name,
                        'report_title' => $type->title(),
                        'generated_by_name' => $user->name,
                    ],
                    'regenerated_from_id' => $sourceKey === null
                        ? null
                        : $originalInspectionReport?->id,
                ],
            ));

            if ($key === 'inspection-original') {
                $originalInspectionReport = $report;
            }
        }
    }

    /**
     * @param  array<string, Asset>  $assets
     * @param  array<string, Certificate>  $certificates
     */
    private function seedReminderHistory(array $assets, array $certificates): void
    {
        foreach (['fire-team@example.test', 'safety-lead@example.test'] as $email) {
            ReminderRecipient::query()->updateOrCreate(['email' => $email]);
        }

        $this->seedReminderDelivery(
            $assets['due-soon'],
            'fire-team@example.test',
            'pending',
            0,
            null,
            null,
        );
        $this->seedReminderDelivery(
            $assets['overdue'],
            'safety-lead@example.test',
            'failed',
            3,
            now()->subHours(2),
            'ConnectionException',
        );
        $this->seedReminderDelivery(
            $certificates['expiring'],
            'fire-team@example.test',
            'sent',
            1,
            null,
            null,
            now(),
        );
        $this->seedReminderDelivery(
            $certificates['expired'],
            'safety-lead@example.test',
            'uncertain',
            1,
            null,
            'Worker stopped before delivery status was confirmed.',
        );
    }

    private function seedReminderDelivery(
        Asset|Certificate $subject,
        string $recipient,
        string $status,
        int $attemptCount,
        ?CarbonImmutable $failedAt,
        ?string $lastError,
        ?CarbonImmutable $sentAt = null,
    ): void {
        $site = $subject->site;
        $client = $site->client;
        $isAsset = $subject instanceof Asset;
        $type = $isAsset ? 'asset' : 'certificate';
        $dueDate = $isAsset ? $subject->next_service_due : $subject->expiry_date;
        $reminderOffset = $status === 'pending' ? 7 : ($status === 'sent' ? 30 : -1);
        $subjectName = $isAsset
            ? trim($subject->assetType->type_name.' '.($subject->serial_number ?? ''))
            : $subject->certificateType->name;
        $identity = [
            'channel' => 'email',
            'reminder_type' => $type,
            'subject_id' => $subject->id,
            'due_date' => $dueDate->toDateString(),
            'reminder_offset' => $reminderOffset,
            'recipient' => $recipient,
        ];

        ReminderDelivery::query()->updateOrCreate($identity, [
            'client_id' => $client->id,
            'site_id' => $site->id,
            'asset_id' => $isAsset ? $subject->id : null,
            'certificate_id' => $isAsset ? null : $subject->id,
            'audience' => 'configured',
            'recipient_name' => $recipient === 'fire-team@example.test' ? 'Fire Team' : null,
            'client_name' => $client->company_name,
            'client_contact_name' => $client->contact_name,
            'client_phone' => $client->phone,
            'client_email' => $client->email,
            'site_name' => $site->site_name,
            'site_address' => $site->address,
            'subject_name' => $subjectName,
            'status' => $status,
            'attempt_count' => $attemptCount,
            'sent_at' => $sentAt,
            'failed_at' => $failedAt,
            'last_error' => $lastError,
        ]);
    }

    private function putDemoFile(Filesystem $disk, string $path, string $contents): int
    {
        if (! $disk->put($path, $contents, ['visibility' => 'private'])) {
            throw new RuntimeException("The demo file could not be stored at {$path}.");
        }

        return strlen($contents);
    }

    private function demoPdf(string $title, int $pageCount = 3): string
    {
        $fontObjectId = 3;
        $objects = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids ['.implode(' ', array_map(
                fn (int $page): string => (string) (4 + (($page - 1) * 2)).' 0 R',
                range(1, $pageCount),
            )).'] /Count '.$pageCount.' >>',
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
        ];

        for ($page = 1; $page <= $pageCount; $page++) {
            $pageObjectId = 4 + (($page - 1) * 2);
            $contentObjectId = $pageObjectId + 1;
            $escapedTitle = str_replace(
                ['\\', '(', ')'],
                ['\\\\', '\\(', '\\)'],
                "{$title} - Page {$page}",
            );
            $stream = "BT /F1 18 Tf 50 740 Td ({$escapedTitle}) Tj ET";
            $objects[] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 {$fontObjectId} 0 R >> >> /Contents {$contentObjectId} 0 R >>";
            $objects[] = '<< /Length '.strlen($stream)." >>\nstream\n{$stream}\nendstream";
        }

        $pdf = "%PDF-1.4\n";
        $offsets = [0];

        foreach ($objects as $index => $object) {
            $offsets[] = strlen($pdf);
            $pdf .= ($index + 1)." 0 obj\n{$object}\nendobj\n";
        }

        $objectCount = count($objects);
        $crossReferenceOffset = strlen($pdf);
        $pdf .= 'xref'."\n0 ".($objectCount + 1)."\n";
        $pdf .= "0000000000 65535 f \n";

        foreach (array_slice($offsets, 1) as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }

        return $pdf
            ."trailer\n<< /Size ".($objectCount + 1)." /Root 1 0 R >>\n"
            ."startxref\n{$crossReferenceOffset}\n%%EOF\n";
    }
}
