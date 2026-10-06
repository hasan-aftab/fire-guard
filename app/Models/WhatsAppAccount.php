<?php

namespace App\Models;

use Database\Factories\WhatsAppAccountFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string $display_phone_number
 * @property string $phone_number_id
 * @property string $waba_id
 * @property string $access_token
 * @property bool $is_active
 * @property bool $is_test
 * @property bool $is_default
 * @property int|null $default_slot
 * @property Carbon|null $last_verified_at
 * @property string|null $last_error
 * @property int $messages_count
 * @property int $delivered_count
 * @property int $outcomes_count
 * @property string|null $messages_max_created_at
 */
#[Fillable(['name', 'display_phone_number', 'phone_number_id', 'waba_id', 'access_token', 'is_active', 'is_test', 'is_default', 'default_slot', 'last_verified_at', 'last_error'])]
#[Hidden(['access_token'])]
class WhatsAppAccount extends Model
{
    protected $table = 'whatsapp_accounts';

    /** @use HasFactory<WhatsAppAccountFactory> */
    use HasFactory;

    /** @return HasMany<WhatsAppMessage, $this> */
    public function messages(): HasMany
    {
        return $this->hasMany(WhatsAppMessage::class, 'whatsapp_account_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['access_token' => 'encrypted', 'is_active' => 'boolean', 'is_test' => 'boolean', 'is_default' => 'boolean', 'last_verified_at' => 'datetime'];
    }
}
