<?php

namespace App\Models;

use Database\Factories\WhatsAppMessageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $reminder_delivery_id
 * @property int|null $whatsapp_account_id
 * @property int|null $whatsapp_template_id
 * @property bool $is_test
 * @property string $recipient
 * @property string|null $sender_display_phone_number
 * @property string|null $phone_number_id
 * @property string|null $waba_id
 * @property string|null $template_name
 * @property string|null $template_language
 * @property array<string, string>|null $test_values
 * @property string $status
 * @property string|null $meta_message_id
 * @property int $attempt_count
 * @property Carbon|null $submitted_at
 * @property Carbon|null $sent_at
 * @property Carbon|null $delivered_at
 * @property Carbon|null $read_at
 * @property Carbon|null $failed_at
 * @property string|null $failure_code
 * @property string|null $last_error
 * @property Carbon|null $created_at
 * @property-read WhatsAppAccount|null $account
 * @property-read WhatsAppTemplate|null $template
 * @property-read ReminderDelivery|null $reminderDelivery
 */
#[Fillable(['reminder_delivery_id', 'whatsapp_account_id', 'whatsapp_template_id', 'is_test', 'recipient', 'sender_display_phone_number', 'phone_number_id', 'waba_id', 'template_name', 'template_language', 'test_values', 'status', 'meta_message_id', 'attempt_count', 'submitted_at', 'sent_at', 'delivered_at', 'read_at', 'failed_at', 'failure_code', 'last_error'])]
class WhatsAppMessage extends Model
{
    protected $table = 'whatsapp_messages';

    /** @use HasFactory<WhatsAppMessageFactory> */
    use HasFactory;

    /** @return BelongsTo<ReminderDelivery, $this> */
    public function reminderDelivery(): BelongsTo
    {
        return $this->belongsTo(ReminderDelivery::class);
    }

    /** @return BelongsTo<WhatsAppAccount, $this> */
    public function account(): BelongsTo
    {
        return $this->belongsTo(WhatsAppAccount::class, 'whatsapp_account_id');
    }

    /** @return BelongsTo<WhatsAppTemplate, $this> */
    public function template(): BelongsTo
    {
        return $this->belongsTo(WhatsAppTemplate::class, 'whatsapp_template_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['is_test' => 'boolean', 'test_values' => 'array', 'submitted_at' => 'datetime', 'sent_at' => 'datetime', 'delivered_at' => 'datetime', 'read_at' => 'datetime', 'failed_at' => 'datetime'];
    }
}
