<?php

return [
    'disk' => env('FIRE_TRACKER_DISK', 'local'),
    'photo_max_kilobytes' => 20480,
    'document_max_kilobytes' => 25600,
    'upload_chunk_bytes' => 1048576,
    'report' => [
        'disk' => env('REPORT_DISK'),
        'company_name' => env('REPORT_COMPANY_NAME', 'Fire Tracker'),
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
