<?php

namespace App\Reports;

use Illuminate\Support\Facades\Storage;
use Throwable;

class ReportBranding
{
    public function logoDataUri(): ?string
    {
        $path = config('fire-tracker.report.logo_path');
        if (! is_string($path) || $path === '') {
            return null;
        }

        try {
            $stream = Storage::disk((string) config('fire-tracker.report.logo_disk'))->readStream($path);
            if (! is_resource($stream)) {
                return null;
            }
            try {
                $bytes = stream_get_contents($stream, 1_048_577);
            } finally {
                fclose($stream);
            }

            if (! is_string($bytes) || strlen($bytes) > 1_048_576) {
                return null;
            }

            $image = @getimagesizefromstring($bytes);
            if ($image === false || ! in_array($image['mime'], ['image/png', 'image/jpeg'], true)) {
                return null;
            }

            return 'data:'.$image['mime'].';base64,'.base64_encode($bytes);
        } catch (Throwable $exception) {
            report($exception);

            return null;
        }
    }
}
