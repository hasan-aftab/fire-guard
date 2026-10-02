<?php

namespace App\Actions;

use App\Models\Certificate;
use App\Models\CertificateDocument;
use App\Models\Inspection;
use App\Models\InspectionPhoto;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class ManagedFiles
{
    /** @return array{storage_disk: string, storage_path: string, original_name: string, mime_type: string, file_size: int} */
    public function store(UploadedFile $file, string $directory, ?string $storageDisk = null): array
    {
        $disk = $storageDisk ?? (string) config('fire-tracker.disk');
        $path = Storage::disk($disk)->putFile($directory, $file, ['visibility' => 'private']);
        if ($path === false) {
            throw new RuntimeException('The file could not be stored. Please try again.');
        }

        return [
            'storage_disk' => $disk,
            'storage_path' => $path,
            'original_name' => Str::of(str_replace('\\', '/', $file->getClientOriginalName()))->afterLast('/')->replaceMatches('/[^\pL\pN._ -]/u', '_')->limit(200, '')->toString(),
            'mime_type' => (string) $file->getMimeType(),
            'file_size' => $file->getSize(),
        ];
    }

    /** @param array{storage_disk: string, storage_path: string, original_name: string, mime_type: string, file_size: int} $metadata */
    public function attach(string $kind, int $recordId, array $metadata): void
    {
        if ($kind === 'photo') {
            $photo = Inspection::findOrFail($recordId)->photos()->create($metadata);
            $this->createThumbnail($photo);

            return;
        }

        [$oldDocument, $document] = DB::transaction(function () use ($recordId, $metadata): array {
            $certificate = Certificate::query()->whereKey($recordId)->lockForUpdate()->firstOrFail();
            $oldDocument = CertificateDocument::query()->where('certificate_id', $certificate->id)
                ->whereNull('pending_deletion_at')->orderByDesc('id')->first();
            $oldDocument?->update(['pending_deletion_at' => now()]);
            $document = $certificate->document()->create($metadata);

            return [$oldDocument, $document];
        });
        $this->createThumbnail($document);

        if ($oldDocument !== null) {
            try {
                $this->deleteObject($oldDocument);
            } catch (Throwable $exception) {
                report($exception);
            }
        }
    }

    public function response(InspectionPhoto|CertificateDocument $file, bool $download = false): StreamedResponse
    {
        abort_if($file->pending_deletion_at !== null, 404);
        $disk = Storage::disk($file->storage_disk);
        abort_unless($disk->exists($file->storage_path), 404);

        return $disk->response($file->storage_path, $file->original_name, [
            'Content-Type' => $file->mime_type,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ], $download ? 'attachment' : 'inline');
    }

    public function thumbnailResponse(InspectionPhoto|CertificateDocument $file): StreamedResponse
    {
        abort_if($file->pending_deletion_at !== null, 404);
        abort_unless(str_starts_with($file->mime_type, 'image/'), 404);
        $disk = Storage::disk($file->storage_disk);
        $path = $this->thumbnailPath($file);
        if (! $disk->exists($path)) {
            $this->createThumbnail($file);
        }
        if (! $disk->exists($path)) {
            return $this->response($file);
        }

        return $disk->response($path, $file->original_name.'.webp', [
            'Content-Type' => 'image/webp',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function createThumbnail(InspectionPhoto|CertificateDocument $file): void
    {
        if (! str_starts_with($file->mime_type, 'image/') || ! function_exists('imagewebp')) {
            return;
        }
        $disk = Storage::disk($file->storage_disk);
        $path = $this->thumbnailPath($file);
        if ($disk->exists($path)) {
            return;
        }

        try {
            $stream = $disk->readStream($file->storage_path);
            if (! is_resource($stream)) {
                return;
            }
            try {
                $contents = stream_get_contents($stream);
            } finally {
                fclose($stream);
            }
            if ($contents === false) {
                return;
            }
            $dimensions = getimagesizefromstring($contents);
            if ($dimensions === false || $dimensions[0] * $dimensions[1] > 16_000_000) {
                return;
            }
            $source = imagecreatefromstring($contents);
            if ($source === false) {
                return;
            }
            $scale = min(1, 256 / max($dimensions[0], $dimensions[1]));
            $thumbnail = imagescale($source, max(1, (int) round($dimensions[0] * $scale)), max(1, (int) round($dimensions[1] * $scale)));
            if ($thumbnail === false) {
                return;
            }
            ob_start();
            try {
                $encoded = imagewebp($thumbnail, null, 75);
                $bytes = ob_get_contents();
            } finally {
                ob_end_clean();
            }
            if ($encoded && is_string($bytes)) {
                $disk->put($path, $bytes, ['visibility' => 'private']);
            }
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    /** @return resource */
    public function readStream(InspectionPhoto|CertificateDocument $file)
    {
        if ($file->pending_deletion_at !== null) {
            throw new RuntimeException('The managed file is pending removal.');
        }

        $stream = Storage::disk($file->storage_disk)->readStream($file->storage_path);
        if (! is_resource($stream)) {
            throw new RuntimeException('The managed file could not be read.');
        }

        return $stream;
    }

    public function deleteObject(InspectionPhoto|CertificateDocument $file): void
    {
        $disk = Storage::disk($file->storage_disk);
        if ($disk->exists($file->storage_path) && ! $disk->delete($file->storage_path)) {
            throw new RuntimeException('The stored file could not be removed. Please try again.');
        }

        $thumbnailPath = $this->thumbnailPath($file);
        if ($disk->exists($thumbnailPath) && ! $disk->delete($thumbnailPath)) {
            throw new RuntimeException('The file preview could not be removed. Please try again.');
        }

        $file->delete();
    }

    private function thumbnailPath(InspectionPhoto|CertificateDocument $file): string
    {
        return $file->storage_path.'.thumbnail.webp';
    }

    public function remove(InspectionPhoto|CertificateDocument $file): void
    {
        $file->update(['pending_deletion_at' => now()]);

        try {
            $this->deleteObject($file);
        } catch (Throwable $exception) {
            try {
                if (Storage::disk($file->storage_disk)->exists($file->storage_path)) {
                    $file->update(['pending_deletion_at' => null]);
                }
            } catch (Throwable $recoveryException) {
                report($recoveryException);
            }

            throw $exception;
        }
    }
}
