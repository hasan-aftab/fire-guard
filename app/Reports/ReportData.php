<?php

namespace App\Reports;

use App\ReportType;

readonly class ReportData
{
    /**
     * @param  array<string, mixed>  $client
     * @param  list<array<string, mixed>>  $sites
     * @param  array<string, int>  $summary
     */
    public function __construct(
        public ReportType $type,
        public array $client,
        public array $sites,
        public string $dateFrom,
        public string $dateTo,
        public string $generatedAt,
        public bool $includeEnteredInError,
        public array $summary,
        public ?string $logoDataUri,
    ) {}
}
