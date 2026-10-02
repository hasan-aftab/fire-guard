<?php

namespace App\Reports;

use App\ReportType;

readonly class ReportFilters
{
    public function __construct(
        public ReportType $type,
        public int $clientId,
        public ?int $siteId,
        public string $dateFrom,
        public string $dateTo,
        public bool $includeEnteredInError,
    ) {}
}
