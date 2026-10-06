<?php

namespace App\Jobs;

use App\Models\WhatsAppAccount;
use App\Models\WhatsAppMessage;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ProcessWhatsAppWebhook implements ShouldQueue
{
    use Queueable;

    /** @param list<array<string, mixed>> $events */
    public function __construct(public array $events) {}

    public function handle(): void
    {
        foreach ($this->events as $event) {
            $this->apply($event);
        }
    }

    /** @param array<string, mixed> $event */
    private function apply(array $event): void
    {
        $account = WhatsAppAccount::query()->where('phone_number_id', $event['phone_number_id'] ?? '')
            ->where('waba_id', $event['waba_id'] ?? '')->first();
        if ($account === null) {
            return;
        }
        DB::transaction(function () use ($event, $account): void {
            $message = WhatsAppMessage::query()->where('meta_message_id', $event['id'] ?? '')
                ->where('whatsapp_account_id', $account->id)->lockForUpdate()->first();
            if ($message === null) {
                return;
            }
            $status = $event['status'] ?? null;
            if (! in_array($status, ['sent', 'delivered', 'read', 'failed'], true)) {
                return;
            }
            if ($status === 'failed' && in_array($message->status, ['delivered', 'read'], true)) {
                return;
            }
            $timestamp = $event['timestamp'] ?? null;
            if (! is_numeric($timestamp) || (int) $timestamp <= 0) {
                return;
            }
            $at = Carbon::createFromTimestamp((int) $timestamp);
            $field = $status.'_at';
            $changes = [];
            if ($message->{$field} === null) {
                $changes[$field] = $at;
            }
            $rank = ['submitted' => 0, 'sent' => 1, 'delivered' => 2, 'read' => 3];
            if ($status === 'failed') {
                if (! in_array($message->status, ['delivered', 'read'], true)) {
                    $changes['status'] = 'failed';
                    $code = $event['code'] ?? null;
                    $changes['failure_code'] = is_scalar($code) ? (string) $code : null;
                    $changes['last_error'] = $changes['failure_code']
                        ? sprintf('Meta reported a delivery failure (code %s).', $changes['failure_code'])
                        : 'Meta reported a delivery failure.';
                }
            } elseif ($rank[$status] > ($rank[$message->status] ?? -1)
                && ($message->status !== 'failed' || in_array($status, ['delivered', 'read'], true))) {
                $changes['status'] = $status;
                if ($message->status === 'failed') {
                    $changes['failure_code'] = null;
                    $changes['last_error'] = null;
                }
            }
            if ($changes === []) {
                return;
            }
            $message->update($changes);
            if ($message->reminder_delivery_id !== null && isset($changes['status'])) {
                $deliveryChanges = ['status' => $changes['status']];
                if (array_key_exists('last_error', $changes) && $changes['last_error'] === null) {
                    $deliveryChanges['last_error'] = null;
                }
                if (isset($changes['sent_at'])) {
                    $deliveryChanges['sent_at'] = $changes['sent_at'];
                }
                if (isset($changes['failed_at'])) {
                    $deliveryChanges['failed_at'] = $changes['failed_at'];
                    $deliveryChanges['last_error'] = $changes['last_error'] ?? null;
                }
                $message->reminderDelivery?->update($deliveryChanges);
            }
        });
    }
}
