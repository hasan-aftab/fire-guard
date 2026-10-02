<?php

namespace App\Console\Commands;

use App\Actions\ManagedFiles;
use App\Models\CertificateDocument;
use App\Models\InspectionPhoto;
use App\Models\ManagedUpload;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Throwable;

#[Signature('fire-tracker:prune-files')]
#[Description('Remove expired upload chunks and retry managed file deletion')]
class PruneManagedFiles extends Command
{
    public function handle(ManagedFiles $files): int
    {
        $failed = 0;
        foreach ([InspectionPhoto::class, CertificateDocument::class] as $model) {
            $model::query()->whereNotNull('pending_deletion_at')->chunkById(100, function ($pending) use ($files, &$failed): void {
                foreach ($pending as $file) {
                    try {
                        $files->deleteObject($file);
                    } catch (Throwable $exception) {
                        $failed++;
                        report($exception);
                        $this->error('Could not remove managed file #'.$file->id.' from '.$file->storage_disk.'.');
                    }
                }
            });
        }
        ManagedUpload::query()->where('expires_at', '<', now())->chunkById(100, function ($uploads) use (&$failed): void {
            foreach ($uploads as $upload) {
                try {
                    if (Storage::disk($upload->storage_disk)->deleteDirectory('fire-tracker/pending/'.$upload->id)) {
                        $upload->delete();
                    } else {
                        $failed++;
                    }
                } catch (Throwable $exception) {
                    $failed++;
                    report($exception);
                }
            }
        });

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
