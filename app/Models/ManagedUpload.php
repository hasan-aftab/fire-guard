<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\ManagedUploadFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property int $user_id
 * @property string $kind
 * @property int $record_id
 * @property string $storage_disk
 * @property string $original_name
 * @property int $file_size
 * @property int $total_chunks
 * @property int $next_chunk
 * @property string $status
 * @property CarbonImmutable $expires_at
 */
#[Fillable(['user_id', 'kind', 'record_id', 'storage_disk', 'original_name', 'file_size', 'total_chunks', 'next_chunk', 'status', 'expires_at'])]
class ManagedUpload extends Model
{
    /** @use HasFactory<ManagedUploadFactory> */
    use HasFactory;

    use HasUuids;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['expires_at' => 'immutable_datetime'];
    }
}
