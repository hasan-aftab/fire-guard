<?php

namespace App\Reports;

use App\Models\Asset;
use App\Models\Certificate;
use App\Models\Client;
use App\Models\Inspection;
use App\Models\InspectionPhoto;
use App\Models\Site;
use App\ReportType;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

class ReportDataBuilder
{
    public function __construct(private ReportPhoto $photos, private ReportBranding $branding) {}

    public function build(ReportFilters $filters): ReportData
    {
        $client = Client::query()->findOrFail($filters->clientId);
        if (! $filters->includeEnteredInError && $client->entered_in_error_at !== null) {
            throw ValidationException::withMessages(['client_id' => 'Select Include entered-in-error records to report on this client.']);
        }

        $sites = Site::query()->where('client_id', $client->id)
            ->when($filters->siteId, fn ($query) => $query->whereKey($filters->siteId))
            ->when(! $filters->includeEnteredInError, fn ($query) => $query->whereNull('entered_in_error_at'))
            ->orderBy('id')->limit($this->limit('max_sites') + 1)->get();
        $this->assertWithinLimit('site_id', $sites->count(), 'max_sites');
        if ($filters->siteId !== null && $sites->isEmpty()) {
            throw ValidationException::withMessages(['site_id' => 'The selected site is unavailable for this report.']);
        }

        $siteIds = $sites->modelKeys();
        $assets = Asset::query()->whereIn('site_id', $siteIds)
            ->when(! $filters->includeEnteredInError, fn ($query) => $query->whereNull('entered_in_error_at'))
            ->with('assetType')->orderBy('id')->limit($this->limit('max_assets') + 1)->get();
        $this->assertWithinLimit('client_id', $assets->count(), 'max_assets');

        $certificates = Certificate::query()->whereIn('site_id', $siteIds)
            ->when(! $filters->includeEnteredInError, fn ($query) => $query->whereNull('entered_in_error_at'))
            ->orderBy('id')->limit($this->limit('max_certificates') + 1)->get();
        $this->assertWithinLimit('client_id', $certificates->count(), 'max_certificates');

        $inspections = Inspection::query()->whereIn('asset_id', $assets->modelKeys())
            ->whereBetween('inspection_date', [$filters->dateFrom, $filters->dateTo])
            ->when(! $filters->includeEnteredInError, fn ($query) => $query->whereNull('entered_in_error_at'))
            ->with('technician')->orderBy('inspection_date')->orderBy('id')
            ->limit($this->limit('max_inspections') + 1)->get();
        $this->assertWithinLimit('date_from', $inspections->count(), 'max_inspections');

        $photoCount = InspectionPhoto::query()->whereIn('inspection_id', $inspections->modelKeys())
            ->whereNull('pending_deletion_at')->count();
        $this->assertWithinLimit('date_from', $photoCount, 'max_photos');
        $inspections->load('photos');

        $operationalAssets = $assets->filter(function (Asset $asset) use ($client, $sites): bool {
            $site = $sites->firstWhere('id', $asset->site_id);

            return $client->is_active && $client->entered_in_error_at === null
                && $site?->is_active && $site->entered_in_error_at === null
                && $asset->status === 'active' && $asset->entered_in_error_at === null;
        });
        $operationalCertificates = $certificates->filter(function (Certificate $certificate) use ($client, $sites): bool {
            $site = $sites->firstWhere('id', $certificate->site_id);

            return $client->is_active && $client->entered_in_error_at === null
                && $site?->is_active && $site->entered_in_error_at === null
                && $certificate->entered_in_error_at === null;
        });

        $siteRows = [];
        foreach ($sites as $site) {
            $assetRows = [];
            foreach ($assets->where('site_id', $site->id) as $asset) {
                $activity = $inspections->where('asset_id', $asset->id);
                if ($filters->type !== ReportType::Audit && $activity->isEmpty()) {
                    continue;
                }

                $assetRows[] = [
                    'type' => $asset->assetType->type_name,
                    'serial' => $asset->serial_number,
                    'location' => $asset->location_desc,
                    'installed' => $asset->install_date?->toDateString(),
                    'last_service' => $asset->last_service_date?->toDateString(),
                    'next_service' => $asset->next_service_due?->toDateString(),
                    'status' => $asset->status,
                    'service_status' => $operationalAssets->contains('id', $asset->id) ? $asset->service_status : 'inactive record',
                    'entered_in_error' => $asset->entered_in_error_at !== null,
                    'inspections' => $activity->map(fn (Inspection $inspection): array => [
                        'date' => $inspection->inspection_date->toDateString(),
                        'technician' => $inspection->technician?->full_name,
                        'result' => $inspection->result,
                        'notes' => $inspection->notes,
                        'next_due' => $inspection->next_due_date?->toDateString(),
                        'entered_in_error' => $inspection->entered_in_error_at !== null,
                        'photos' => $inspection->photos->map(fn (InspectionPhoto $photo): array => [
                            'name' => $photo->original_name,
                            'src' => $this->photos->dataUri($photo),
                        ])->all(),
                    ])->all(),
                ];
            }

            if ($filters->type !== ReportType::Audit && $assetRows === []) {
                continue;
            }

            $siteRows[] = [
                'name' => $site->site_name,
                'address' => $site->address,
                'region' => $site->suburb_region,
                'contact' => $site->site_contact,
                'phone' => $site->site_phone,
                'is_active' => $site->is_active,
                'entered_in_error' => $site->entered_in_error_at !== null,
                'assets' => $assetRows,
                'certificates' => $this->certificateRows($certificates->where('site_id', $site->id)),
            ];
        }

        return new ReportData(
            $filters->type,
            [
                'name' => $client->company_name,
                'contact' => $client->contact_name,
                'phone' => $client->phone,
                'email' => $client->email,
                'address' => $client->billing_address,
                'is_active' => $client->is_active,
                'entered_in_error' => $client->entered_in_error_at !== null,
            ],
            $siteRows,
            $filters->dateFrom,
            $filters->dateTo,
            now()->toDateTimeString(),
            $filters->includeEnteredInError,
            [
                'sites' => $sites->count(),
                'assets' => $assets->count(),
                'overdue_assets' => $operationalAssets->filter(fn (Asset $asset): bool => $asset->service_status === 'overdue')->count(),
                'due_soon_assets' => $operationalAssets->filter(fn (Asset $asset): bool => $asset->service_status === 'due_soon')->count(),
                'certificates' => $certificates->count(),
                'expired_certificates' => $operationalCertificates->filter(fn (Certificate $certificate): bool => $certificate->status === 'expired')->count(),
                'inspections' => $inspections->count(),
            ],
            $this->branding->logoDataUri(),
        );
    }

    /** @param Collection<int, Certificate> $certificates
     * @return list<array<string, mixed>>
     */
    private function certificateRows(Collection $certificates): array
    {
        return array_values($certificates->map(fn (Certificate $certificate): array => [
            'type' => $certificate->certificate_type,
            'issue_date' => $certificate->issue_date->toDateString(),
            'expiry_date' => $certificate->expiry_date->toDateString(),
            'issued_by' => $certificate->issued_by,
            'status' => $certificate->status,
            'entered_in_error' => $certificate->entered_in_error_at !== null,
        ])->all());
    }

    private function limit(string $key): int
    {
        return (int) config('fire-tracker.report.'.$key);
    }

    private function assertWithinLimit(string $field, int $count, string $limitKey): void
    {
        if ($count > $this->limit($limitKey)) {
            throw ValidationException::withMessages([
                $field => 'This report has too many records. Select a smaller site or date range.',
            ]);
        }
    }
}
