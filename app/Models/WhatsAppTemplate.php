<?php

namespace App\Models;

use Database\Factories\WhatsAppTemplateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $waba_id
 * @property string $purpose
 * @property string $name
 * @property string $language
 * @property string|null $meta_status
 * @property string|null $category
 * @property list<string> $parameter_keys
 * @property bool $is_enabled
 * @property Carbon|null $last_synced_at
 * @property string|null $last_error
 */
#[Fillable(['waba_id', 'purpose', 'name', 'language', 'meta_status', 'category', 'parameter_keys', 'is_enabled', 'last_synced_at', 'last_error'])]
class WhatsAppTemplate extends Model
{
    protected $table = 'whatsapp_templates';

    /** @use HasFactory<WhatsAppTemplateFactory> */
    use HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['parameter_keys' => 'array', 'is_enabled' => 'boolean', 'last_synced_at' => 'datetime'];
    }
}
