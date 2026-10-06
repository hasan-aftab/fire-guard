<?php

namespace App\Jobs;

use App\Actions\ReminderEligibility;
use App\Mail\AssetInspectionReminderMail;
use App\Mail\CertificateExpiryReminderMail;
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

        if (! app(ReminderEligibility::class)->current($delivery) || ! $this->recipientEligible($delivery)) {
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

    private function recipientEligible(ReminderDelivery $delivery): bool
    {
        if (filter_var($delivery->recipient, FILTER_VALIDATE_EMAIL) === false
            || ReminderRecipient::isClientContactEmail($delivery->recipient)) {
            return false;
        }

        if ($delivery->audience === 'configured') {
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

        return true;
    }
}
