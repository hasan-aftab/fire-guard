<?php

namespace App\Reports;

use Illuminate\Support\Str;
use RuntimeException;
use Spatie\LaravelPdf\Facades\Pdf;
use Throwable;

class GenerateReport
{
    public function __construct(private ReportDataBuilder $dataBuilder) {}

    public function generate(ReportFilters $filters): RenderedReport
    {
        $report = $this->dataBuilder->build($filters);

        try {
            $bytes = Pdf::view('reports.'.$report->type->value, ['report' => $report])
                ->format('a4')
                ->dontCache()
                ->generatePdfContent();
        } catch (Throwable $exception) {
            throw new RuntimeException('The PDF could not be generated right now.', 0, $exception);
        }

        $slug = Str::slug((string) $report->client['name']) ?: 'client';
        $filename = $report->type->value.'-report-'.Str::limit($slug, 50, '').'-'.$report->dateTo.'.pdf';

        return new RenderedReport($bytes, $filename);
    }
}
