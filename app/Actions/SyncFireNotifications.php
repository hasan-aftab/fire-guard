<?php

namespace App\Actions;

use App\Models\Asset;
use App\Models\Certificate;
use App\Models\FireNotification;
use App\Models\Inspection;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class SyncFireNotifications
{
    public function handleAll(): void
    {
        User::query()->select('id')->chunkById(100, function ($users): void {
            foreach ($users as $user) {
                $this->handle($user);
            }
        });
    }

    public function handle(User $user): void
    {
        $candidates = [];

        foreach (Asset::with(['assetType', 'site'])->actionable()
            ->where('next_service_due', '<=', today()->addDays(Asset::DUE_SOON_DAYS)->toDateString())->get() as $asset) {
            if ($asset->next_service_due === null) {
                continue;
            }
            $overdue = $asset->service_status === 'overdue';
            $days = (int) abs(today()->diffInDays($asset->next_service_due));
            $dayLabel = $days === 1 ? 'day' : 'days';
            $candidates[] = [
                'type' => $overdue ? 'overdue_asset' : 'due_soon_asset',
                'title' => $overdue ? 'Overdue Service' : 'Service Due Soon',
                'message' => "{$asset->assetType->type_name} (".($asset->serial_number ?: 'No S/N').") at {$asset->site->site_name} ".
                    ($overdue ? "is {$days} {$dayLabel} overdue for service" : "is due in {$days} {$dayLabel}"),
                'entity_type' => 'asset',
                'entity_id' => $asset->id,
            ];
        }

        foreach (Certificate::with(['site', 'certificateType'])->actionable()->where('expiry_date', '<=', today()->addDays(Certificate::EXPIRING_SOON_DAYS)->toDateString())->get() as $certificate) {
            $expired = $certificate->status === 'expired';
            $days = (int) abs(today()->diffInDays($certificate->expiry_date));
            $dayLabel = $days === 1 ? 'day' : 'days';
            $candidates[] = [
                'type' => $expired ? 'expired_cert' : 'expiring_cert',
                'title' => $expired ? 'Certificate Expired' : 'Certificate Expiring',
                'message' => "{$certificate->certificateType->name} certificate at {$certificate->site->site_name} ".
                    ($expired ? "expired {$days} {$dayLabel} ago" : "expires in {$days} {$dayLabel}"),
                'entity_type' => 'certificate',
                'entity_id' => $certificate->id,
            ];
        }

        foreach (Inspection::with('asset.site')->actionable()->whereIn('result', ['fail', 'needs_repair'])
            ->where('id', fn ($query) => $query->select('latest.id')->from('inspections as latest')
                ->whereColumn('latest.asset_id', 'inspections.asset_id')->whereNull('latest.entered_in_error_at')
                ->orderByDesc('latest.inspection_date')->orderByDesc('latest.id')->limit(1))->get() as $inspection) {
            $label = $inspection->result === 'fail' ? 'Failed' : 'Needs Repair';
            $candidates[] = [
                'type' => 'failed_inspection',
                'title' => 'Inspection '.$label,
                'message' => "Inspection on {$inspection->inspection_date->toDateString()} for asset (".
                    ($inspection->asset->serial_number ?: 'No S/N').") at {$inspection->asset->site->site_name} — {$label}",
                'entity_type' => 'inspection',
                'entity_id' => $inspection->id,
            ];
        }

        DB::transaction(function () use ($user, $candidates): void {
            FireNotification::where('user_id', $user->id)->where('is_read', true)
                ->where('created_at', '<', today()->subDays(30)->startOfDay())->delete();

            $existing = FireNotification::where('user_id', $user->id)->get()
                ->keyBy(fn (FireNotification $notification): string => "{$notification->type}:{$notification->entity_type}:{$notification->entity_id}");

            foreach ($candidates as $candidate) {
                $key = "{$candidate['type']}:{$candidate['entity_type']}:{$candidate['entity_id']}";
                $notification = $existing->pull($key);

                if ($notification === null) {
                    FireNotification::firstOrCreate([
                        'user_id' => $user->id,
                        'type' => $candidate['type'],
                        'entity_type' => $candidate['entity_type'],
                        'entity_id' => $candidate['entity_id'],
                    ], $candidate);

                    continue;
                }

                $notification->fill(['title' => $candidate['title'], 'message' => $candidate['message']]);
                if ($notification->isDirty()) {
                    $notification->save();
                }
            }

            foreach ($existing->pluck('id')->chunk(500) as $staleIds) {
                FireNotification::where('user_id', $user->id)->whereIn('id', $staleIds)->delete();
            }
        });
    }
}
