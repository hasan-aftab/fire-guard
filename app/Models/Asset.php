<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\AssetFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $site_id
 * @property int $asset_type_id
 * @property string|null $serial_number
 * @property string|null $location_desc
 * @property CarbonImmutable|null $install_date
 * @property CarbonImmutable|null $last_service_date
 * @property CarbonImmutable|null $next_service_due
 * @property string $status
 * @property-read string $service_status
 * @property CarbonImmutable|null $entered_in_error_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Site $site
 * @property-read AssetType $assetType
 * @property-read Collection<int, Inspection> $inspections
 * @property-read Collection<int, Certificate> $certificates
 */
#[Fillable(['site_id', 'asset_type_id', 'serial_number', 'location_desc', 'install_date', 'last_service_date', 'next_service_due', 'status'])]
class Asset extends Model
{
    public const DUE_SOON_DAYS = 14;

    /** @use HasFactory<AssetFactory> */
    use HasFactory;

    /** @return Attribute<string, never> */
    protected function serviceStatus(): Attribute
    {
        return Attribute::get(function (): string {
            if ($this->next_service_due === null) {
                return 'unknown';
            }

            $today = today()->toImmutable();
            if ($this->next_service_due->isBefore($today)) {
                return 'overdue';
            }

            return $this->next_service_due->isAfter($today->addDays(self::DUE_SOON_DAYS)) ? 'ok' : 'due_soon';
        });
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'install_date' => 'immutable_date:Y-m-d',
            'last_service_date' => 'immutable_date:Y-m-d',
            'next_service_due' => 'immutable_date:Y-m-d',
            'entered_in_error_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<Site, $this> */
    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    /** @return BelongsTo<AssetType, $this> */
    public function assetType(): BelongsTo
    {
        return $this->belongsTo(AssetType::class);
    }

    /** @return HasMany<Inspection, $this> */
    public function inspections(): HasMany
    {
        return $this->hasMany(Inspection::class);
    }

    /** @return HasMany<Certificate, $this> */
    public function certificates(): HasMany
    {
        return $this->hasMany(Certificate::class);
    }
}
