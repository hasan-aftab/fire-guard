<?php

namespace App\Models;

use App\ReportType;
use Carbon\CarbonImmutable;
use Database\Factories\GeneratedReportFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property ReportType $report_type
 * @property int $client_id
 * @property int|null $site_id
 * @property CarbonImmutable $date_from
 * @property CarbonImmutable $date_to
 * @property bool $include_entered_in_error
 * @property string $storage_disk
 * @property string $storage_path
 * @property string $original_filename
 * @property int $file_size
 * @property string $sha256
 * @property int|null $generated_by
 * @property CarbonImmutable $generated_at
 * @property array<string, string>|null $metadata
 * @property int|null $regenerated_from_id
 */
#[Fillable(['report_type', 'client_id', 'site_id', 'date_from', 'date_to', 'include_entered_in_error', 'storage_disk', 'storage_path', 'original_filename', 'file_size', 'sha256', 'generated_by', 'generated_at', 'metadata', 'regenerated_from_id'])]
class GeneratedReport extends Model
{
    /** @use HasFactory<GeneratedReportFactory> */
    use HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'report_type' => ReportType::class,
            'date_from' => 'immutable_date',
            'date_to' => 'immutable_date',
            'include_entered_in_error' => 'boolean',
            'file_size' => 'integer',
            'generated_at' => 'immutable_datetime',
            'metadata' => 'array',
        ];
    }

    /** @return BelongsTo<Client, $this> */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /** @return BelongsTo<Site, $this> */
    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    /** @return BelongsTo<User, $this> */
    public function generator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }
}
