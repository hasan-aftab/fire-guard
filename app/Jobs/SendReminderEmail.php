<?php

namespace App\Jobs;

use App\Mail\AssetInspectionReminderMail;
use App\Mail\CertificateExpiryReminderMail;
use App\Models\Asset;
use App\Models\Certificate;
use App\Models\ReminderDelivery;
use App\Models\ReminderRecipient;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendReminderEmail implements ShouldQueue
{
    use Queueable;

    public const INELIGIBLE_REASON = 'Record, schedule, or recipient changed before delivery.';

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [300, 1800];

    public int $timeout = 60;

    public function __construct(public int $deliveryId) {}

    public function handle(): void
    {
        $delivery = ReminderDelivery::query()->findOrFail($this->deliveryId);
        if (! in_array($delivery->status, ['pending', 'failed'], true)) {
            return;
        }

        if (! $this->stillEligible($delivery)) {
            ReminderDelivery::query()->whereKey($delivery->id)->whereIn('status', ['pending', 'failed'])
                ->update(['status' => 'skipped', 'last_error' => self::INELIGIBLE_REASON, 'updated_at' => now()]);

            return;
        }

        $claimed = ReminderDelivery::query()->whereKey($delivery->id)->whereIn('status', ['pending', 'failed'])
            ->update(['status' => 'processing', 'attempt_count' => DB::raw('attempt_count + 1'), 'updated_at' => now()]);
        if ($claimed === 0) {
            return;
        }

        try {
            $mail = $delivery->reminder_type === 'asset'
                ? new AssetInspectionReminderMail($delivery)
                : new CertificateExpiryReminderMail($delivery);
            Mail::to($delivery->recipient)->send($mail);
            $delivery->update(['status' => 'sent', 'sent_at' => now(), 'failed_at' => null, 'last_error' => null]);
        } catch (Throwable $exception) {
            $delivery->update(['status' => 'failed', 'failed_at' => now(), 'last_error' => class_basename($exception)]);
            Log::warning('Fire reminder delivery failed', ['delivery_id' => $delivery->id, 'error_type' => class_basename($exception)]);
            throw $exception;
        }
    }

    public function failed(?Throwable $exception): void
    {
        ReminderDelivery::query()->whereKey($this->deliveryId)->where('status', 'processing')
            ->update(['status' => 'uncertain', 'last_error' => 'Worker stopped during delivery; confirm the provider outcome before retrying.', 'updated_at' => now()]);
    }

    private function stillEligible(ReminderDelivery $delivery): bool
    {
        if (filter_var($delivery->recipient, FILTER_VALIDATE_EMAIL) === false) {
            return false;
        }

        $subject = $delivery->reminder_type === 'asset'
            ? Asset::query()->with('site.client')->find($delivery->subject_id)
            : Certificate::query()->with(['site.client', 'asset'])->find($delivery->subject_id);
        if ($subject === null || $subject->entered_in_error_at !== null) {
            return false;
        }
        $site = $subject->site;
        $client = $site->client;
        if (! $site->is_active || $site->entered_in_error_at !== null || ! $client->is_active || $client->entered_in_error_at !== null) {
            return false;
        }
        if ($client->id !== $delivery->client_id || $site->id !== $delivery->site_id) {
            return false;
        }
        if (in_array($delivery->audience, ['configured', 'configured_client'], true)) {
            if (! ReminderRecipient::query()->where('email', $delivery->recipient)->exists()) {
                return false;
            }
        } elseif ($delivery->audience === 'staff') {
            if (ReminderRecipient::query()->exists()
                || ! User::query()->where('email', $delivery->recipient)->where('role', User::ROLE_ADMIN)
                    ->whereNotNull('email_verified_at')->exists()) {
                return false;
            }
        } else {
            return false;
        }
        if ($subject instanceof Asset) {
            return $subject->status === 'active' && $subject->next_service_due?->toDateString() === $delivery->due_date->toDateString();
        }

        return $subject->expiry_date->toDateString() === $delivery->due_date->toDateString()
            && ($subject->asset === null || ($subject->asset->status === 'active' && $subject->asset->entered_in_error_at === null));
    }
}
