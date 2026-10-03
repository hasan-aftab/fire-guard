<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\ReminderDeliveryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $channel
 * @property string $reminder_type
 * @property int $subject_id
 * @property int $client_id
 * @property int $site_id
 * @property int|null $asset_id
 * @property int|null $certificate_id
 * @property string $audience
 * @property string $recipient
 * @property string|null $recipient_name
 * @property string $client_name
 * @property string|null $client_contact_name
 * @property string|null $client_phone
 * @property string|null $client_email
 * @property string $site_name
 * @property string|null $site_address
 * @property string $subject_name
 * @property CarbonImmutable $due_date
 * @property int $reminder_offset
 * @property string $status
 * @property int $attempt_count
 * @property CarbonImmutable|null $sent_at
 * @property CarbonImmutable|null $failed_at
 * @property string|null $last_error
 */
#[Fillable(['channel', 'reminder_type', 'subject_id', 'client_id', 'site_id', 'asset_id', 'certificate_id', 'audience', 'recipient', 'recipient_name', 'client_name', 'client_contact_name', 'client_phone', 'client_email', 'site_name', 'site_address', 'subject_name', 'due_date', 'reminder_offset', 'status', 'attempt_count', 'sent_at', 'failed_at', 'last_error'])]
class ReminderDelivery extends Model
{
    /** @use HasFactory<ReminderDeliveryFactory> */
    use HasFactory;

    public function isClientAudience(): bool
    {
        return in_array($this->audience, ['client', 'configured_client'], true);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'due_date' => 'immutable_date:Y-m-d',
            'sent_at' => 'immutable_datetime',
            'failed_at' => 'immutable_datetime',
        ];
    }
}
