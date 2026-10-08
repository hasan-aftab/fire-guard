<?php

namespace App\Jobs;

use App\Actions\ReminderEligibility;
use App\Actions\WhatsAppTemplatePayload;
use App\Models\Client;
use App\Models\ReminderDelivery;
use App\Models\WhatsAppAccount;
use App\Models\WhatsAppMessage;
use App\Models\WhatsAppTemplate;
use App\Services\MetaWhatsAppClient;
use App\Services\WhatsAppPhone;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendWhatsAppReminder implements ShouldQueue
{
    use Queueable;

    public const CONFIG_REASON = 'No verified default sender or approved template is available.';

    public int $tries = 1;

    public int $timeout = 30;

    public function __construct(public ?int $deliveryId = null, public ?int $messageId = null) {}

    public function handle(MetaWhatsAppClient $meta, WhatsAppTemplatePayload $mapper, ReminderEligibility $eligibility, WhatsAppPhone $phone): void
    {
        $delivery = $this->deliveryId === null ? null : ReminderDelivery::query()->find($this->deliveryId);
        if ($delivery !== null) {
            if ($delivery->channel !== 'whatsapp' || $delivery->status !== 'pending') {
                return;
            }
            $client = Client::query()->find($delivery->client_id);
            $valid = false;
            try {
                $valid = $client !== null && $client->whatsapp_reminders_enabled
                    && $client->whatsapp_phone === $delivery->recipient
                    && $phone->normalize($delivery->recipient) === $delivery->recipient;
            } catch (\InvalidArgumentException) {
                // Invalid recipient is handled as an ineligible delivery.
            }
            if (! $valid || ! $eligibility->current($delivery)) {
                $delivery->update(['status' => 'skipped', 'last_error' => 'Record, schedule, consent, or recipient changed before delivery.']);

                return;
            }
        }

        $account = $delivery === null
            ? WhatsAppAccount::query()->find(WhatsAppMessage::query()->find($this->messageId)?->whatsapp_account_id)
            : WhatsAppAccount::query()->where('is_active', true)->where('is_test', false)->where('is_default', true)->whereNotNull('last_verified_at')->whereNull('last_error')->first();
        $template = $account === null ? null : WhatsAppTemplate::query()->where('waba_id', $account->waba_id)
            ->where('purpose', $delivery !== null ? $delivery->reminder_type : WhatsAppMessage::query()->find($this->messageId)?->template?->purpose)
            ->where('is_enabled', true)->where('meta_status', 'APPROVED')->whereNull('last_error')->first();

        $message = $delivery === null ? WhatsAppMessage::query()->find($this->messageId) : WhatsAppMessage::query()->firstOrCreate(
            ['reminder_delivery_id' => $delivery->id],
            ['recipient' => $delivery->recipient, 'is_test' => false, 'status' => 'queued'],
        );
        if ($message === null || ! in_array($message->status, ['queued', 'skipped'], true)) {
            return;
        }
        if ($account === null || ! $account->is_active || $account->last_verified_at === null || $account->last_error !== null
            || $template === null || ($delivery === null && $message->whatsapp_template_id !== $template->id)) {
            $message->update(['status' => 'skipped', 'last_error' => self::CONFIG_REASON]);
            $delivery?->update(['status' => 'skipped', 'last_error' => self::CONFIG_REASON]);

            return;
        }

        $values = $delivery === null ? ($message->test_values ?? []) : $mapper->values($delivery);
        try {
            $components = $mapper->components($template, $values);
        } catch (\InvalidArgumentException) {
            $message->update(['status' => 'skipped', 'last_error' => 'Template parameter mapping is incomplete.']);
            $delivery?->update(['status' => 'skipped', 'last_error' => 'Template parameter mapping is incomplete.']);

            return;
        }

        $claimed = WhatsAppMessage::query()->whereKey($message->id)->whereIn('status', ['queued', 'skipped'])->update([
            'status' => 'processing', 'attempt_count' => DB::raw('attempt_count + 1'), 'updated_at' => now(),
        ]);
        if ($claimed === 0) {
            return;
        }
        $delivery?->update(['status' => 'processing', 'attempt_count' => DB::raw('attempt_count + 1')]);
        $message->update([
            'whatsapp_account_id' => $account->id, 'whatsapp_template_id' => $template->id,
            'sender_display_phone_number' => $account->display_phone_number, 'phone_number_id' => $account->phone_number_id,
            'waba_id' => $account->waba_id, 'template_name' => $template->name, 'template_language' => $template->language,
        ]);

        try {
            $result = $meta->send($account, $message->recipient, $template, $components);
        } catch (Throwable $exception) {
            $message->update(['status' => 'uncertain', 'last_error' => 'Meta outcome is unknown after a network or worker error. Review before retrying.']);
            $delivery?->update(['status' => 'uncertain', 'last_error' => 'Meta outcome is unknown after a network or worker error. Review before retrying.']);
            Log::warning('WhatsApp send outcome uncertain', ['message_id' => $message->id, 'error_type' => class_basename($exception)]);

            return;
        }

        if (! $result['ok']) {
            $reason = 'Meta rejected the request (code '.$result['code'].'.)'.MetaWhatsAppClient::suffix($result['code']);
            $message->update(['status' => 'failed', 'failed_at' => now(), 'failure_code' => $result['code'], 'last_error' => $reason]);
            $delivery?->update(['status' => 'failed', 'failed_at' => now(), 'last_error' => $reason]);

            return;
        }
        $wamid = data_get($result['data'], 'messages.0.id');
        if (! is_string($wamid) || ! str_starts_with($wamid, 'wamid.')) {
            $message->update(['status' => 'uncertain', 'last_error' => 'Meta accepted the request without a usable message ID. Review before retrying.']);
            $delivery?->update(['status' => 'uncertain', 'last_error' => 'Meta accepted the request without a usable message ID. Review before retrying.']);

            return;
        }
        $message->update(['status' => 'submitted', 'submitted_at' => now(), 'meta_message_id' => $wamid, 'last_error' => null]);
        $delivery?->update(['status' => 'submitted', 'last_error' => null]);
    }

    public function failed(?Throwable $exception): void
    {
        WhatsAppMessage::query()->when($this->messageId, fn ($query) => $query->whereKey($this->messageId))
            ->when($this->deliveryId, fn ($query) => $query->where('reminder_delivery_id', $this->deliveryId))
            ->where('status', 'processing')->update(['status' => 'uncertain', 'last_error' => 'Worker stopped during Meta handoff; review before retrying.']);
        if ($this->deliveryId !== null) {
            ReminderDelivery::query()->whereKey($this->deliveryId)->where('status', 'processing')
                ->update(['status' => 'uncertain', 'last_error' => 'Worker stopped during Meta handoff; review before retrying.']);
        }
    }
}
