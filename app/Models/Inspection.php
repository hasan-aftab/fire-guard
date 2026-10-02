<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\InspectionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $asset_id
 * @property int|null $technician_id
 * @property CarbonImmutable $inspection_date
 * @property string $result
 * @property string|null $notes
 * @property CarbonImmutable|null $next_due_date
 * @property CarbonImmutable|null $entered_in_error_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Asset $asset
 * @property-read Technician|null $technician
 */
#[Fillable(['asset_id', 'technician_id', 'inspection_date', 'result', 'notes', 'next_due_date'])]
class Inspection extends Model
{
    /** @use HasFactory<InspectionFactory> */
    use HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'inspection_date' => 'immutable_date:Y-m-d',
            'next_due_date' => 'immutable_date:Y-m-d',
            'entered_in_error_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<Asset, $this> */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    /** @return BelongsTo<Technician, $this> */
    public function technician(): BelongsTo
    {
        return $this->belongsTo(Technician::class);
    }

    /** @return HasMany<InspectionPhoto, $this> */
    public function photos(): HasMany
    {
        return $this->hasMany(InspectionPhoto::class)->whereNull('pending_deletion_at')->orderBy('id');
    }
}
