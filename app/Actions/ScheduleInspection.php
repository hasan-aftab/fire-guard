<?php

namespace App\Actions;

use App\Models\Asset;
use App\Models\Inspection;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class ScheduleInspection
{
    public function nextDueDate(string $inspectionDate, ?int $intervalMonths): ?string
    {
        if ($intervalMonths === null || $intervalMonths < 1) {
            return null;
        }

        return CarbonImmutable::parse($inspectionDate)->addMonthsNoOverflow($intervalMonths)->toDateString();
    }

    /** @param array<string, mixed> $attributes */
    public function save(array $attributes, ?Inspection $inspection = null): Inspection
    {
        return DB::transaction(function () use ($attributes, $inspection): Inspection {
            if ($inspection !== null) {
                $inspection = Inspection::query()->whereKey($inspection->id)->lockForUpdate()->firstOrFail();
            } else {
                $inspection = new Inspection;
            }

            $previousAssetId = $inspection->exists ? $inspection->asset_id : null;
            $assetIds = array_unique(array_filter([$previousAssetId, (int) $attributes['asset_id']]));
            sort($assetIds);
            $assets = Asset::query()->whereKey($assetIds)->lockForUpdate()->get()->keyBy('id');
            $asset = $assets->get((int) $attributes['asset_id']);
            if (! $asset instanceof Asset) {
                abort(404);
            }

            $interval = $asset->assetType->default_interval_months;
            $previousDefault = $inspection->exists && $previousAssetId === $asset->id
                ? $this->nextDueDate($inspection->inspection_date->toDateString(), $interval)
                : null;
            $submittedDueDate = $attributes['next_due_date'] ?? null;
            $unchangedDueDate = $inspection->exists
                && $submittedDueDate === $inspection->next_due_date?->toDateString();
            $shouldCalculate = ! $submittedDueDate || ($unchangedDueDate
                && ($previousAssetId !== $asset->id || $submittedDueDate === $previousDefault));
            $attributes['next_due_date'] = ! $shouldCalculate
                ? $submittedDueDate
                : $this->nextDueDate($attributes['inspection_date'], $interval);

            $inspection->fill($attributes)->save();
            foreach ($assetIds as $assetId) {
                $affectedAsset = $assets->get($assetId);
                if ($affectedAsset instanceof Asset) {
                    $this->synchronize($affectedAsset);
                }
            }

            return $inspection;
        }, 3);
    }

    public function setEnteredInError(Inspection $inspection, bool $enteredInError): void
    {
        DB::transaction(function () use ($inspection, $enteredInError): void {
            $inspection = Inspection::query()->whereKey($inspection->id)->lockForUpdate()->firstOrFail();
            $asset = Asset::query()->whereKey($inspection->asset_id)->lockForUpdate()->firstOrFail();
            $current = $asset->inspections()->whereNull('entered_in_error_at')
                ->orderByDesc('inspection_date')->orderByDesc('id')->first();
            $scheduleCameFromInspection = $asset->last_service_date?->toDateString() === $current?->inspection_date?->toDateString()
                && $asset->next_service_due?->toDateString() === $current?->next_due_date?->toDateString();
            $inspection->entered_in_error_at = $enteredInError ? now() : null;
            $inspection->save();
            if ($scheduleCameFromInspection) {
                $this->synchronize($asset);
            }
        }, 3);
    }

    private function synchronize(Asset $asset): void
    {
        if ($asset->status !== 'active') {
            return;
        }

        $latest = $asset->inspections()->whereNull('entered_in_error_at')
            ->orderByDesc('inspection_date')->orderByDesc('id')->first();
        $asset->fill([
            'last_service_date' => $latest?->inspection_date->toDateString(),
            'next_service_due' => $latest?->next_due_date?->toDateString(),
        ])->save();
    }
}
