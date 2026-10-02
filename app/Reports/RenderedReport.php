<?php

namespace App\Reports;

readonly class RenderedReport
{
    public function __construct(public string $bytes, public string $filename) {}
}
