<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\TechnicianFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $full_name
 * @property string|null $phone
 * @property string|null $email
 * @property string|null $license_number
 * @property CarbonImmutable|null $license_expiry
 * @property bool $is_active
 * @property CarbonImmutable|null $entered_in_error_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Inspection> $inspections
 */
#[Fillable(['full_name', 'phone', 'email', 'license_number', 'license_expiry', 'is_active'])]
class Technician extends Model
{
    /** @use HasFactory<TechnicianFactory> */
    use HasFactory;

    /**
     * The technician is still taking work and was not entered in error.
     *
     * @param  Builder<Technician>  $query
     */
    #[Scope]
    protected function actionable(Builder $query): void
    {
        $query->where('is_active', true)->whereNull('entered_in_error_at');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'license_expiry' => 'immutable_date:Y-m-d',
            'entered_in_error_at' => 'immutable_datetime',
        ];
    }

    /** @return HasMany<Inspection, $this> */
    public function inspections(): HasMany
    {
        return $this->hasMany(Inspection::class);
    }
}
