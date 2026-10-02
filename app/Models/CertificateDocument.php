<?php

namespace App\Models;

use Database\Factories\CertificateDocumentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** @property int $id @property int $certificate_id @property string $storage_disk @property string $storage_path @property string $original_name @property string $mime_type @property int $file_size @property \Carbon\CarbonImmutable|null $pending_deletion_at */
#[Fillable(['storage_disk', 'storage_path', 'original_name', 'mime_type', 'file_size', 'pending_deletion_at'])]
class CertificateDocument extends Model
{
    /** @use HasFactory<CertificateDocumentFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $hidden = ['storage_disk', 'storage_path', 'pending_deletion_at'];

    /** @return BelongsTo<Certificate, $this> */
    public function certificate(): BelongsTo
    {
        return $this->belongsTo(Certificate::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['pending_deletion_at' => 'immutable_datetime'];
    }
}
