<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $email
 */
#[Fillable(['email'])]
class ReminderRecipient extends Model
{
    public static function isClientContactEmail(string $email): bool
    {
        return Client::query()->whereRaw('LOWER(email) = ?', [strtolower($email)])->exists();
    }
}
