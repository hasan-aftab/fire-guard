<?php

namespace App\Http\Controllers;

use App\Http\Requests\GenerateReportRequest;
use App\Http\Requests\ReportHistoryRequest;
use App\Models\Client;
use App\Models\GeneratedReport;
use App\Models\Site;
use App\Reports\ReportFilters;
use App\Reports\SaveGeneratedReport;
use App\ReportType;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class ReportController extends Controller
{
    public function index(ReportHistoryRequest $request): InertiaResponse|RedirectResponse
    {
        $filters = $request->safe()->except(['page']);
        $records = GeneratedReport::query()
            ->when($filters['type'] ?? null, fn ($query, $type) => $query->where('report_type', $type))
            ->when($filters['client_id'] ?? null, fn ($query, $id) => $query->where('client_id', $id))
            ->when($filters['site_id'] ?? null, fn ($query, $id) => $query->where('site_id', $id))
            ->when($filters['generated_from'] ?? null, fn ($query, $date) => $query->where('generated_at', '>=', $date.' 00:00:00'))
            ->when($filters['generated_to'] ?? null, fn ($query, $date) => $query->where('generated_at', '<=', $date.' 23:59:59'))
            ->when($filters['period_from'] ?? null, fn ($query, $date) => $query->where('date_from', '>=', $date))
            ->when($filters['period_to'] ?? null, fn ($query, $date) => $query->where('date_to', '<', CarbonImmutable::parse($date)->addDay()->toDateString()))
            ->orderByDesc('generated_at')->orderByDesc('id')->paginate(10)->withQueryString();

        if ($records->currentPage() > $records->lastPage()) {
            return to_route('reports.index', $filters + ['page' => $records->lastPage()]);
        }

        $recentId = $request->session()->get('generated_report_id');
        $recent = $recentId ? GeneratedReport::query()->find((int) $recentId) : null;

        return Inertia::render('reports', [
            'records' => $records->through(fn (GeneratedReport $report): array => $this->summary($report)),
            'filters' => $filters,
            'clients' => Client::query()->orderBy('company_name')->get(['id', 'company_name']),
            'sites' => Site::query()->orderBy('site_name')->get(['id', 'client_id', 'site_name']),
            'reportTypes' => array_map(fn (ReportType $type): array => ['value' => $type->value, 'label' => $type->title()], ReportType::cases()),
            'recentReport' => $recent ? $this->summary($recent) : null,
        ]);
    }

    public function store(GenerateReportRequest $request, SaveGeneratedReport $reports): RedirectResponse
    {
        try {
            $report = $reports->generate($request->filters(), $request->user());
        } catch (RuntimeException $exception) {
            report($exception);

            return back()->withErrors(['report' => 'The PDF could not be generated or saved. Please try again.']);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Report generated and saved.']);

        return to_route('reports.index')->with('generated_report_id', $report->id);
    }

    public function legacyPdf(GenerateReportRequest $request, SaveGeneratedReport $reports): Response
    {
        try {
            $report = $reports->generate($request->filters(), $request->user());
        } catch (RuntimeException $exception) {
            report($exception);

            return response('The PDF could not be generated right now. Please try again later.', 503, [
                'Content-Type' => 'text/plain; charset=UTF-8',
                'Cache-Control' => 'private, no-store',
            ]);
        }

        $disposition = $request->validated('disposition', 'inline') === 'download' ? 'attachment' : 'inline';

        try {
            return response(Storage::disk($report->storage_disk)->get($report->storage_path), 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => $disposition.'; filename="'.$report->original_filename.'"',
                'Cache-Control' => 'private, no-store',
                'X-Content-Type-Options' => 'nosniff',
            ]);
        } catch (Throwable $exception) {
            report($exception);

            return response('The PDF was saved, but could not be opened. Find it in Report History.', 503, [
                'Content-Type' => 'text/plain; charset=UTF-8',
                'Cache-Control' => 'private, no-store',
            ]);
        }
    }

    public function show(GeneratedReport $generatedReport): StreamedResponse|Response
    {
        return $this->file($generatedReport, false);
    }

    public function download(GeneratedReport $generatedReport): StreamedResponse|Response
    {
        return $this->file($generatedReport, true);
    }

    public function regenerate(GeneratedReport $generatedReport, SaveGeneratedReport $reports): RedirectResponse
    {
        $filters = new ReportFilters(
            $generatedReport->report_type,
            $generatedReport->client_id,
            $generatedReport->site_id,
            $generatedReport->date_from->toDateString(),
            $generatedReport->date_to->toDateString(),
            $generatedReport->include_entered_in_error,
        );

        try {
            $report = $reports->generate($filters, request()->user(), $generatedReport);
        } catch (RuntimeException $exception) {
            report($exception);

            return back()->withErrors(['report' => 'The report could not be generated again. Please try again.']);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'A new report was generated and saved.']);

        return to_route('reports.index')->with('generated_report_id', $report->id);
    }

    private function file(GeneratedReport $report, bool $download): StreamedResponse|Response
    {
        try {
            $disk = Storage::disk($report->storage_disk);
            if (! $disk->exists($report->storage_path)) {
                throw new RuntimeException('Stored report '.$report->id.' is missing.');
            }

            $stream = $disk->readStream($report->storage_path);
            if (! is_resource($stream)) {
                throw new RuntimeException('Stored report '.$report->id.' could not be read.');
            }
            try {
                $hash = hash_init('sha256');
                hash_update_stream($hash, $stream);
                if (! hash_equals($report->sha256, hash_final($hash))) {
                    throw new RuntimeException('Stored report '.$report->id.' failed its integrity check.');
                }
            } finally {
                fclose($stream);
            }

            return $disk->response($report->storage_path, $report->original_filename, [
                'Content-Type' => 'application/pdf',
                'Cache-Control' => 'private, no-store',
                'X-Content-Type-Options' => 'nosniff',
            ], $download ? 'attachment' : 'inline');
        } catch (Throwable $exception) {
            report($exception);

            return response('The saved PDF is unavailable. Please contact an administrator.', 404, [
                'Content-Type' => 'text/plain; charset=UTF-8',
                'Cache-Control' => 'private, no-store',
            ]);
        }
    }

    /** @return array<string, mixed> */
    private function summary(GeneratedReport $report): array
    {
        return [
            'id' => $report->id,
            'report_type' => $report->report_type->value,
            'title' => $report->metadata['report_title'] ?? $report->report_type->title(),
            'client_id' => $report->client_id,
            'client_name' => $report->metadata['client_name'] ?? '',
            'site_id' => $report->site_id,
            'site_name' => $report->metadata['site_name'] ?? '',
            'date_from' => $report->date_from->toDateString(),
            'date_to' => $report->date_to->toDateString(),
            'include_entered_in_error' => $report->include_entered_in_error,
            'generated_at' => $report->generated_at->toIso8601String(),
            'generated_by_name' => $report->metadata['generated_by_name'] ?? 'Unknown',
            'regenerated_from_id' => $report->regenerated_from_id,
        ];
    }
}
