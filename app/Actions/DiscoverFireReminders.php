<?php

namespace App\Actions;

use App\Jobs\SendReminderEmail;
use App\Jobs\SendWhatsAppReminder;
use App\Models\Asset;
use App\Models\Certificate;
use App\Models\Client;
use App\Models\ReminderDelivery;
use App\Models\ReminderRecipient;
use App\Models\Site;
use App\Models\User;
use App\Models\WhatsAppMessage;
use App\Services\WhatsAppPhone;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;
use Throwable;

class DiscoverFireReminders
{
    public function run(CarbonImmutable $today): int
    {
        ReminderDelivery::query()->where('status', 'processing')
            ->where('updated_at', '<', now()->subMinutes(config('fire-tracker.reminders.processing_review_after_minutes')))
            ->update(['status' => 'uncertain', 'last_error' => 'Worker stopped during delivery; confirm the provider outcome before retrying.', 'updated_at' => now()]);
        WhatsAppMessage::query()->where('status', 'processing')
            ->where('updated_at', '<', now()->subMinutes(config('fire-tracker.reminders.processing_review_after_minutes')))
            ->update(['status' => 'uncertain', 'last_error' => 'Worker stopped during Meta handoff; review before retrying.', 'updated_at' => now()]);

        $created = 0;
        $recipients = $this->recipients();

        foreach (config('fire-tracker.reminders.asset_offsets') as $offset) {
            $created += $this->assets($today->addDays($offset)->toDateString(), $offset, $recipients);
        }
        foreach (config('fire-tracker.reminders.certificate_offsets') as $offset) {
            $created += $this->certificates($today->addDays($offset)->toDateString(), $offset, $recipients);
        }

        if (config('fire-tracker.reminders.overdue_once')) {
            $created += $this->assets($today->toDateString(), -1, $recipients, true);
            $created += $this->certificates($today->toDateString(), -1, $recipients, true);
        }

        return $created;
    }

    /** @return list<array{email: string, name: string, audience: string}> */
    private function recipients(): array
    {
        $configured = ReminderRecipient::query()->orderBy('email')->get(['email']);
        $recipients = [];
        if ($configured->isNotEmpty()) {
            foreach ($configured as $recipient) {
                if (ReminderRecipient::isClientContactEmail($recipient->email)) {
                    continue;
                }
                $recipients[] = ['email' => $recipient->email, 'name' => 'team', 'audience' => 'configured'];
            }

            return $recipients;
        }

        $admins = User::query()->where('role', User::ROLE_ADMIN)->whereNotNull('email_verified_at')
            ->orderBy('id')->get(['name', 'email']);
        foreach ($admins as $admin) {
            if (ReminderRecipient::isClientContactEmail($admin->email)) {
                continue;
            }
            $recipients[] = ['email' => $admin->email, 'name' => $admin->name, 'audience' => 'staff'];
        }

        return $recipients;
    }

    /** @param list<array{email: string, name: string, audience: string}> $recipients */
    private function assets(string $date, int $offset, array $recipients, bool $overdue = false): int
    {
        $created = 0;
        Asset::query()
            ->with(['site.client', 'assetType'])
            ->where('status', 'active')
            ->whereNull('entered_in_error_at')
            ->whereHas('site', fn ($query) => $query->where('is_active', true)->whereNull('entered_in_error_at')
                ->whereHas('client', fn ($client) => $client->where('is_active', true)->whereNull('entered_in_error_at')))
            ->whereDate('next_service_due', $overdue ? '<' : '=', $date)
            ->chunkById(100, function ($assets) use ($offset, $recipients, &$created): void {
                foreach ($assets as $asset) {
                    $created += $this->record('asset', $asset, $asset->next_service_due->toDateString(), $offset,
                        trim($asset->assetType->type_name.' '.($asset->serial_number ?? '')), $recipients);
                }
            });

        return $created;
    }

    /** @param list<array{email: string, name: string, audience: string}> $recipients */
    private function certificates(string $date, int $offset, array $recipients, bool $overdue = false): int
    {
        $created = 0;
        Certificate::query()
            ->with(['site.client', 'asset.assetType', 'certificateType'])
            ->whereNull('entered_in_error_at')
            ->whereHas('site', fn ($query) => $query->where('is_active', true)->whereNull('entered_in_error_at')
                ->whereHas('client', fn ($client) => $client->where('is_active', true)->whereNull('entered_in_error_at')))
            ->whereDoesntHave('asset', fn ($query) => $query->where('status', 'decommissioned')->orWhereNotNull('entered_in_error_at'))
            ->whereDate('expiry_date', $overdue ? '<' : '=', $date)
            ->chunkById(100, function ($certificates) use ($offset, $recipients, &$created): void {
                foreach ($certificates as $certificate) {
                    $name = $certificate->certificateType->name;
                    if ($certificate->asset !== null) {
                        $name .= ' — '.$certificate->asset->assetType->type_name;
                        if ($certificate->asset->serial_number !== null) {
                            $name .= ' '.$certificate->asset->serial_number;
                        }
                    }
                    $created += $this->record('certificate', $certificate, $certificate->expiry_date->toDateString(), $offset, $name, $recipients);
                }
            });

        return $created;
    }

    /** @param list<array{email: string, name: string, audience: string}> $recipients */
    private function record(string $type, Asset|Certificate $subject, string $dueDate, int $offset, string $name, array $recipients): int
    {
        $site = $subject->site;
        $client = $site->client;
        $created = 0;
        foreach ($recipients as $recipient) {
            $created += $this->createDelivery($type, $subject, $client, $site, $dueDate, $offset, $name,
                $recipient['email'], $recipient['name'], $recipient['audience']);
        }
        if ($client->whatsapp_reminders_enabled) {
            $created += $this->createWhatsAppDelivery($type, $subject, $client, $site, $dueDate, $offset, $name);
        }

        return $created;
    }

    private function createWhatsAppDelivery(string $type, Asset|Certificate $subject, Client $client, Site $site,
        string $dueDate, int $offset, string $name): int
    {
        $recipient = $client->whatsapp_phone ?? '';
        $valid = false;
        try {
            $valid = $recipient !== '' && app(WhatsAppPhone::class)->normalize($recipient) === $recipient;
        } catch (\InvalidArgumentException) {
            // Invalid stored values remain visible as skipped reminders.
        }
        $identity = [
            'channel' => 'whatsapp', 'reminder_type' => $type, 'subject_id' => $subject->id,
            'due_date' => $dueDate, 'reminder_offset' => $offset, 'recipient' => $recipient,
        ];
        $snapshot = [
            'client_id' => $client->id, 'site_id' => $site->id,
            'asset_id' => $type === 'asset' ? $subject->id : null,
            'certificate_id' => $type === 'certificate' ? $subject->id : null,
            'audience' => 'client', 'recipient_name' => $client->contact_name,
            'client_name' => $client->company_name, 'site_name' => $site->site_name,
            'client_contact_name' => $client->contact_name, 'client_phone' => $client->phone,
            'client_email' => $client->email, 'site_address' => $site->address,
            'subject_name' => $name, 'status' => $valid ? 'pending' : 'skipped',
            'failed_at' => null, 'last_error' => $valid ? null : 'Client WhatsApp number is missing or invalid.',
        ];
        $delivery = ReminderDelivery::query()->createOrFirst($identity, $snapshot);
        if (! $delivery->wasRecentlyCreated) {
            if (! $valid || $delivery->status !== 'skipped' || $delivery->last_error !== SendWhatsAppReminder::CONFIG_REASON) {
                return 0;
            }
            $updated = ReminderDelivery::query()->whereKey($delivery->id)->where('status', 'skipped')
                ->where('last_error', SendWhatsAppReminder::CONFIG_REASON)
                ->update(['status' => 'pending', 'last_error' => null, 'updated_at' => now()]);
            if ($updated === 0) {
                return 0;
            }
        }
        if ($valid) {
            try {
                SendWhatsAppReminder::dispatch($delivery->id);
            } catch (Throwable $exception) {
                $delivery->update(['status' => 'failed', 'failed_at' => now(), 'last_error' => 'Queue dispatch failed.']);
                Log::warning('WhatsApp reminder queue dispatch failed', ['delivery_id' => $delivery->id, 'error_type' => class_basename($exception)]);
            }
        }

        return 1;
    }

    private function createDelivery(string $type, Asset|Certificate $subject, Client $client, Site $site,
        string $dueDate, int $offset, string $name, string $recipient, string $recipientName, string $audience): int
    {
        $valid = filter_var($recipient, FILTER_VALIDATE_EMAIL) !== false;
        $identity = [
            'channel' => 'email', 'reminder_type' => $type, 'subject_id' => $subject->id,
            'due_date' => $dueDate, 'reminder_offset' => $offset,
            'recipient' => $recipient,
        ];
        $snapshot = [
            'client_id' => $client->id, 'site_id' => $site->id,
            'asset_id' => $type === 'asset' ? $subject->id : null,
            'certificate_id' => $type === 'certificate' ? $subject->id : null,
            'audience' => $audience,
            'recipient_name' => $recipientName,
            'client_name' => $client->company_name, 'site_name' => $site->site_name,
            'client_contact_name' => $client->contact_name,
            'client_phone' => $client->phone,
            'client_email' => $client->email,
            'site_address' => $site->address,
            'subject_name' => $name,
            'status' => $valid ? 'pending' : 'skipped',
            'failed_at' => null,
            'last_error' => $valid ? null : 'Recipient email is invalid.',
        ];
        $delivery = ReminderDelivery::query()->createOrFirst($identity, $snapshot);
        if (! $delivery->wasRecentlyCreated) {
            if (! $valid || $delivery->status !== 'skipped' || $delivery->last_error !== SendReminderEmail::INELIGIBLE_REASON) {
                return 0;
            }
            $requeued = ReminderDelivery::query()->whereKey($delivery->id)->where('status', 'skipped')
                ->where('last_error', SendReminderEmail::INELIGIBLE_REASON)
                ->update($snapshot + ['updated_at' => now()]);
            if ($requeued === 0) {
                return 0;
            }
            $delivery->refresh();
        }

        if ($valid) {
            try {
                SendReminderEmail::dispatch($delivery->id);
            } catch (Throwable $exception) {
                if ($delivery->refresh()->status === 'pending') {
                    $delivery->update(['status' => 'failed', 'failed_at' => now(), 'last_error' => 'Queue dispatch failed: '.class_basename($exception)]);
                    Log::warning('Fire reminder queue dispatch failed', ['delivery_id' => $delivery->id, 'error_type' => class_basename($exception)]);
                }
            }
        }

        return 1;
    }
}
