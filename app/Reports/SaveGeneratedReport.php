<?php

namespace App\Reports;

use App\Models\Client;
use App\Models\GeneratedReport;
use App\Models\Site;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class SaveGeneratedReport
{
    public function __construct(private GenerateReport $generator) {}

    public function generate(ReportFilters $filters, User $user, ?GeneratedReport $source = null): GeneratedReport
    {
        $rendered = $this->generator->generate($filters);
        $client = Client::query()->findOrFail($filters->clientId);
        $site = $filters->siteId === null ? null : Site::query()->findOrFail($filters->siteId);
        $diskName = (string) (config('fire-tracker.report.disk') ?: config('fire-tracker.disk'));
        $path = 'fire-tracker/reports/'.now()->format('Y/m').'/'.Str::uuid().'.pdf';

        try {
            $disk = Storage::disk($diskName);
            if (! $disk->put($path, $rendered->bytes, ['visibility' => 'private'])) {
                throw new RuntimeException('The PDF could not be stored.');
            }
        } catch (Throwable $exception) {
            if (isset($disk)) {
                try {
                    $disk->delete($path);
                } catch (Throwable $cleanupException) {
                    report($cleanupException);
                }
            }

            throw new RuntimeException('The PDF could not be stored.', 0, $exception);
        }

        try {
            return GeneratedReport::query()->create([
                'report_type' => $filters->type,
                'client_id' => $filters->clientId,
                'site_id' => $filters->siteId,
                'date_from' => $filters->dateFrom,
                'date_to' => $filters->dateTo,
                'include_entered_in_error' => $filters->includeEnteredInError,
                'storage_disk' => $diskName,
                'storage_path' => $path,
                'original_filename' => $rendered->filename,
                'file_size' => strlen($rendered->bytes),
                'sha256' => hash('sha256', $rendered->bytes),
                'generated_by' => $user->id,
                'generated_at' => now(),
                'regenerated_from_id' => $source?->id,
                'metadata' => [
                    'client_name' => $client->company_name,
                    'site_name' => $site === null ? '' : $site->site_name,
                    'report_title' => $filters->type->title(),
                    'generated_by_name' => $user->name,
                ],
            ]);
        } catch (Throwable $exception) {
            try {
                if (! $disk->delete($path)) {
                    report(new RuntimeException('Failed to clean up orphaned report PDF at '.$path));
                }
            } catch (Throwable $cleanupException) {
                report($cleanupException);
            }

            throw new RuntimeException('The report history record could not be saved.', 0, $exception);
        }
    }
}
