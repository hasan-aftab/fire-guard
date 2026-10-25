<?php

use App\Models\Asset;
use App\Models\Certificate;

return [
    'disk' => env('FIRE_TRACKER_DISK', 'local'),
    'photo_max_kilobytes' => 20480,
    'document_max_kilobytes' => 25600,
    'upload_chunk_bytes' => 1048576,
    'reminders' => [
        'asset_offsets' => [14, 7, 1, 0],
        'certificate_offsets' => [30, 14, 7, 1, 0],
        'overdue_once' => true,
        'send_at' => '08:00',
        'processing_review_after_minutes' => 120,
    ],
    'dashboard' => [
        /*
         * Horizon presets are inclusive offsets from the as-of date: 7 means
         * "today through today + 7", so the day strip gets 8 cells. The 14-day
         * preset is the default and is tied to the model's own due-soon
         * threshold so the default window always covers exactly the band that
         * Asset::serviceStatus() calls due soon.
         */
        'horizon_presets' => [
            '7d' => 7,
            '14d' => Asset::DUE_SOON_DAYS,
            '30d' => Certificate::EXPIRING_SOON_DAYS,
        ],
        'default_horizon' => '14d',
        'due_soon_days' => Asset::DUE_SOON_DAYS,
        'certificate_alert_days' => Certificate::EXPIRING_SOON_DAYS,
        'compliance_months' => 12,
        'pass_rate' => [
            'success' => 80,
            'warning' => 60,
        ],
    ],
    'report' => [
        'disk' => env('REPORT_DISK'),
        'company_name' => env('REPORT_COMPANY_NAME', 'FireGuard'),
        'company_phone' => env('REPORT_COMPANY_PHONE'),
        'company_email' => env('REPORT_COMPANY_EMAIL'),
        'logo_disk' => env('REPORT_LOGO_DISK', env('FIRE_TRACKER_DISK', 'local')),
        'logo_path' => env('REPORT_LOGO_PATH'),
        'max_sites' => 30,
        'max_assets' => 200,
        'max_certificates' => 200,
        'max_inspections' => 150,
        'max_photos' => 24,
    ],
];
