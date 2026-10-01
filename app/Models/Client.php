<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\ClientFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $company_name
 * @property string|null $contact_name
 * @property string|null $phone
 * @property string|null $email
 * @property string|null $billing_address
 * @property string|null $notes
 * @property bool $is_active
 * @property CarbonImmutable|null $entered_in_error_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Site> $sites
 */
#[Fillable(['company_name', 'contact_name', 'phone', 'email', 'billing_address', 'notes', 'is_active'])]
class Client extends Model
{
    /** @use HasFactory<ClientFactory> */
    use HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'entered_in_error_at' => 'immutable_datetime',
        ];
    }

    /** @return HasMany<Site, $this> */
    public function sites(): HasMany
    {
        return $this->hasMany(Site::class);
    }
}
