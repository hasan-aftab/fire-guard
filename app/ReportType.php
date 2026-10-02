<?php

namespace App;

enum ReportType: string
{
    case Inspection = 'inspection';
    case Service = 'service';
    case Maintenance = 'maintenance';
    case Audit = 'audit';

    public function title(): string
    {
        return match ($this) {
            self::Inspection => 'Inspection Report',
            self::Service => 'Service Report',
            self::Maintenance => 'Maintenance Report',
            self::Audit => 'Audit Report',
        };
    }
}
