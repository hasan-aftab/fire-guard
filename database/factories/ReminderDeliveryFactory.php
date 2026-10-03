<?php

namespace Database\Factories;

use App\Models\Asset;
use App\Models\Client;
use App\Models\ReminderDelivery;
use App\Models\Site;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ReminderDelivery> */
class ReminderDeliveryFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        $client = Client::factory()->create();
        $site = Site::factory()->for($client)->create();
        $asset = Asset::factory()->for($site)->create();

        return [
            'channel' => 'email',
            'reminder_type' => 'asset',
            'subject_id' => $asset->id,
            'client_id' => $client->id,
            'site_id' => $site->id,
            'asset_id' => $asset->id,
            'audience' => 'client',
            'recipient' => $client->email,
            'recipient_name' => $client->contact_name,
            'client_name' => $client->company_name,
            'client_contact_name' => $client->contact_name,
            'client_phone' => $client->phone,
            'client_email' => $client->email,
            'site_name' => $site->site_name,
            'site_address' => $site->address,
            'subject_name' => $asset->assetType->type_name,
            'due_date' => $asset->next_service_due,
            'reminder_offset' => 7,
            'status' => 'pending',
        ];
    }
}
