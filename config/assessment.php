<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Feature flag
    |--------------------------------------------------------------------------
    |
    | Modul dapat dipasang dan dimigrasikan tanpa langsung terlihat oleh
    | pengguna. Aktifkan setelah master serta hak akses selesai diverifikasi.
    |
    */
    'enabled' => (bool) env('ASSESSMENT_MODULE_ENABLED', false),

    'formula_version' => env('ASSESSMENT_FORMULA_VERSION', '1.0.0'),

    'reports' => [
        // Disk "local" memakai storage/app/private pada aplikasi ini.
        'disk' => env('ASSESSMENT_REPORT_DISK', 'local'),
        'queue' => env('ASSESSMENT_REPORT_QUEUE', 'assessment-reports'),
        'path' => 'assessment-reports',
        'individual_mode' => env('ASSESSMENT_REPORT_INDIVIDUAL_MODE', 'stream'),
        'class_cache_hours' => max(1, (int) env('ASSESSMENT_REPORT_CLASS_CACHE_HOURS', 24)),
        'render' => [
            'active_slots' => max(1, (int) env('ASSESSMENT_REPORT_RENDER_ACTIVE_SLOTS', 1)),
            'lock_seconds' => max(30, (int) env('ASSESSMENT_REPORT_RENDER_LOCK_SECONDS', 180)),
            'retry_after_seconds' => max(5, (int) env('ASSESSMENT_REPORT_RENDER_RETRY_AFTER_SECONDS', 10)),
        ],
        'worker' => [
            'max_time' => (int) env('ASSESSMENT_REPORT_WORKER_MAX_TIME', 50),
            'timeout' => (int) env('ASSESSMENT_REPORT_WORKER_TIMEOUT', 180),
        ],
        'pipeline' => [
            'students_per_job' => max(1, (int) env('ASSESSMENT_REPORT_STUDENTS_PER_JOB', 3)),
            'max_seconds' => max(10, (int) env('ASSESSMENT_REPORT_PIPELINE_MAX_SECONDS', 40)),
        ],
    ],

    'share_links' => [
        'default_expiry_hours' => (int) env('ASSESSMENT_SHARE_EXPIRY_HOURS', 24),
        'allowed_expiry_days' => [1, 3, 7],
        'rate_limit_per_minute' => (int) env('ASSESSMENT_SHARE_RATE_LIMIT', 30),
    ],
];
