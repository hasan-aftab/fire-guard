<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\CertificateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $site_id
 * @property int|null $asset_id
 * @property string $certificate_type
 * @property CarbonImmutable $issue_date
 * @property CarbonImmutable $expiry_date
 * @property string|null $issued_by
 * @property string|null $document_url
 * @property string $status
 * @property CarbonImmutable|null $entered_in_error_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Site $site
 * @property-read Asset|null $asset
 */
#[Fillable(['site_id', 'asset_id', 'certificate_type', 'issue_date', 'expiry_date', 'issued_by', 'document_url'])]
class Certificate extends Model
{
    public const EXPIRING_SOON_DAYS = 30;

    /** @use HasFactory<CertificateFactory> */
    use HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'issue_date' => 'immutable_date:Y-m-d',
            'expiry_date' => 'immutable_date:Y-m-d',
            'entered_in_error_at' => 'immutable_datetime',
        ];
    }

    /** @return Attribute<string, never> */
    protected function status(): Attribute
    {
        return Attribute::get(function (): string {
            $today = today()->toImmutable();

            if ($this->expiry_date->isBefore($today)) {
                return 'expired';
            }

            return $this->expiry_date->isAfter($today->addDays(self::EXPIRING_SOON_DAYS)) ? 'valid' : 'expiring_soon';
        });
    }

    /** @return BelongsTo<Site, $this> */
    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    /** @return BelongsTo<Asset, $this> */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }
}
