<?php

namespace Database\Factories;

use App\Models\Asset;
use App\Models\FireNotification;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<FireNotification> */
class FireNotificationFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['user_id' => User::factory(), 'type' => 'overdue_asset', 'title' => 'Overdue Service', 'message' => 'Equipment is overdue for service', 'entity_id' => Asset::factory(), 'entity_type' => 'asset', 'is_read' => false];
    }
}
