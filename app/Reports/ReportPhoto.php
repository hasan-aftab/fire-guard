<?php

namespace App\Reports;

use App\Actions\ManagedFiles;
use App\Models\InspectionPhoto;
use GdImage;
use Throwable;

class ReportPhoto
{
    public function __construct(private ManagedFiles $files) {}

    public function dataUri(InspectionPhoto $photo): ?string
    {
        if (! function_exists('imagecreatefromstring') || $photo->file_size > 20_000_000) {
            return null;
        }

        try {
            $stream = $this->files->readStream($photo);
            try {
                $bytes = stream_get_contents($stream, 20_000_001);
            } finally {
                fclose($stream);
            }

            if (! is_string($bytes) || strlen($bytes) > 20_000_000) {
                return null;
            }

            $dimensions = @getimagesizefromstring($bytes);
            if ($dimensions === false || $dimensions[0] * $dimensions[1] > 16_000_000) {
                return null;
            }

            $source = @imagecreatefromstring($bytes);
            if (! $source instanceof GdImage) {
                return null;
            }

            $scale = min(1, 1200 / max($dimensions[0], $dimensions[1]));
            $width = max(1, (int) round($dimensions[0] * $scale));
            $height = max(1, (int) round($dimensions[1] * $scale));
            $target = imagecreatetruecolor($width, $height);
            $white = imagecolorallocate($target, 255, 255, 255);
            if ($white === false) {
                return null;
            }
            imagefill($target, 0, 0, $white);
            imagecopyresampled($target, $source, 0, 0, 0, 0, $width, $height, $dimensions[0], $dimensions[1]);

            ob_start();
            try {
                $encoded = imagejpeg($target, null, 78);
                $result = ob_get_contents();
            } finally {
                ob_end_clean();
            }

            return $encoded && is_string($result) ? 'data:image/jpeg;base64,'.base64_encode($result) : null;
        } catch (Throwable $exception) {
            report($exception);

            return null;
        }
    }
}
