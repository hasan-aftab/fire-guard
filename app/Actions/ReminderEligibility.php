<?php

namespace App\Actions;

use App\Models\Asset;
use App\Models\Certificate;
use App\Models\ReminderDelivery;

class ReminderEligibility
{
    public function current(ReminderDelivery $delivery): bool
    {
        $subject = $delivery->reminder_type === 'asset'
            ? Asset::query()->with('site.client')->find($delivery->subject_id)
            : Certificate::query()->with(['site.client', 'asset'])->find($delivery->subject_id);
        if ($subject === null || $subject->entered_in_error_at !== null) {
            return false;
        }
        $site = $subject->site;
        $client = $site->client;
        if (! $site->is_active || $site->entered_in_error_at !== null || ! $client->is_active || $client->entered_in_error_at !== null
            || $client->id !== $delivery->client_id || $site->id !== $delivery->site_id) {
            return false;
        }
        if ($subject instanceof Asset) {
            return $subject->status === 'active' && $subject->next_service_due?->toDateString() === $delivery->due_date->toDateString();
        }

        return $subject->expiry_date->toDateString() === $delivery->due_date->toDateString()
            && ($subject->asset === null || ($subject->asset->status === 'active' && $subject->asset->entered_in_error_at === null));
    }
}
