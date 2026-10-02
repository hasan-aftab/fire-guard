<?php

namespace App\Http\Controllers;

use App\Http\Requests\GenerateReportRequest;
use App\Reports\GenerateReport;
use Illuminate\Http\Response;
use RuntimeException;

class ReportController extends Controller
{
    public function __invoke(GenerateReportRequest $request, GenerateReport $generator): Response
    {
        $filters = $request->filters();

        try {
            $report = $generator->generate($filters);
        } catch (RuntimeException $exception) {
            report($exception);

            return response('The PDF could not be generated right now. Please try again later.', 503, [
                'Content-Type' => 'text/plain; charset=UTF-8',
                'Cache-Control' => 'private, no-store',
            ]);
        }

        $disposition = $request->validated('disposition', 'inline') === 'download' ? 'attachment' : 'inline';

        return response($report->bytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => $disposition.'; filename="'.$report->filename.'"',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
