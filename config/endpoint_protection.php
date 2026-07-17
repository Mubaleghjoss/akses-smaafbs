<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Endpoint Categories / Protection Matrix
    |--------------------------------------------------------------------------
    |
    | Keep endpoint classes discoverable in one place. Each category can point
    | to a named limiter and a degradation profile for expensive UI surfaces.
    |
    */

    'endpoint_categories' => [
        'admin_auth' => [
            'description' => 'Admin authentication endpoints (Filament login).',
            'routes' => [
                '/admin/login',
            ],
            'named_limiter' => null,
            'livewire_rate_limit_attempts' => 5,
            'livewire_rate_limit_decay_seconds' => 60,
            'degradation_profile' => null,
            'notes' => 'Rate limiting is currently enforced inside App\\Filament\\Pages\\Auth\\Login::authenticate via Livewire rateLimit().',
        ],
        'admin_panel_async' => [
            'description' => 'Filament/Livewire async update and upload endpoints used by authenticated admin CRUD interactions.',
            'routes' => [
                '/livewire-{asset_hash}/update',
                '/livewire-{asset_hash}/upload-file',
            ],
            'named_limiter' => null,
            'degradation_profile' => null,
            'notes' => 'Intentionally left without route-level throttle so normal admin list/filter/form/modal async flows are not degraded. Login throttling remains enforced in App\\Filament\\Pages\\Auth\\Login only.',
        ],
        'admin_exports_downloads' => [
            'description' => 'Authenticated admin export/download document endpoints.',
            'routes' => [
                '/admin/prokers/export/{periode_tahun}',
                '/admin/data-siswas/export',
                '/admin/guru-tendiks/export',
                '/admin/uks-records/export',
                '/admin/boarding-rapots/{boardingRapot}/export',
                '/admin/berkas-gurus/{berkasGuru}/download',
            ],
            'named_limiter' => 'admin_exports',
            'degradation_profile' => 'admin_heavy_widgets',
        ],
        'public_reads' => [
            'description' => 'Public read-only website pages and detail endpoints.',
            'routes' => [
                '/',
                '/agenda',
                '/berita',
                '/siswa',
                '/perpustakaan',
            ],
            'named_limiter' => 'public_reads',
            'degradation_profile' => null,
        ],
        'public_billing_actions' => [
            'description' => 'Public billing lookups and payment proof upload actions.',
            'routes' => [
                '/tagihan/detail',
                '/tagihan/bayar',
                '/tagihan/{code}',
            ],
            'named_limiter' => 'public_billing_lookup',
            'degradation_profile' => null,
        ],
        'public_billing_uploads' => [
            'description' => 'Public billing payment proof submission endpoint.',
            'routes' => [
                '/tagihan/bayar [POST]',
            ],
            'named_limiter' => 'public_billing_payment_upload',
            'degradation_profile' => null,
        ],
        'public_library_downloads' => [
            'description' => 'Public e-book download endpoint.',
            'routes' => [
                '/perpustakaan/buku/{book}/download',
            ],
            'named_limiter' => 'public_library_downloads',
            'degradation_profile' => null,
        ],
        'public_agenda_feeds' => [
            'description' => 'Public agenda JSON feed endpoint.',
            'routes' => [
                '/agenda/events',
            ],
            'named_limiter' => 'public_agenda_events',
            'degradation_profile' => null,
        ],
        'tagihan_student_integration' => [
            'description' => 'Private server-to-server student master feed for the billing application.',
            'routes' => [
                '/api/v1/integrations/tagihan/students',
            ],
            'named_limiter' => 'tagihan_student_api',
            'degradation_profile' => null,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Named Limiter Declarations
    |--------------------------------------------------------------------------
    |
    | Registered in AppServiceProvider so middleware / future tasks can attach
    | by name without hard-coding numbers in many places.
    |
    */

    'named_limiters' => [
        'admin_exports' => [
            'attempts' => 20,
            'decay_seconds' => 60,
            'by' => 'user_or_ip',
        ],
        'public_reads' => [
            'attempts' => 180,
            'decay_seconds' => 60,
            'by' => 'ip',
        ],
        'public_billing_lookup' => [
            'attempts' => 30,
            'decay_seconds' => 60,
            'by' => 'ip',
            'response' => [
                'type' => 'redirect_route_flash',
                'route' => 'billing.index',
                'message' => 'Permintaan cek tagihan terlalu sering. Silakan tunggu sebentar lalu coba lagi.',
            ],
        ],
        'public_billing_payment_upload' => [
            'attempts' => 6,
            'decay_seconds' => 60,
            'by' => 'ip',
            'response' => [
                'type' => 'redirect_back_error',
                'message' => 'Pengiriman bukti pembayaran terlalu sering. Mohon tunggu sebentar sebelum mengirim ulang.',
            ],
        ],
        'public_library_downloads' => [
            'attempts' => 20,
            'decay_seconds' => 60,
            'by' => 'ip',
            'response' => [
                'type' => 'redirect_back_flash',
                'message' => 'Unduhan terlalu sering dari perangkat ini. Silakan tunggu sebentar lalu coba lagi.',
            ],
        ],
        'public_agenda_events' => [
            'attempts' => 60,
            'decay_seconds' => 60,
            'by' => 'ip',
            'response' => [
                'type' => 'json',
                'message' => 'Permintaan agenda terlalu sering. Silakan coba lagi dalam beberapa saat.',
            ],
        ],
        'tagihan_student_api' => [
            'attempts' => 30,
            'decay_seconds' => 60,
            'by' => 'ip',
            'response' => [
                'type' => 'json',
                'message' => 'Terlalu banyak permintaan. Silakan coba lagi nanti.',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Graceful Degradation Policy
    |--------------------------------------------------------------------------
    |
    | Local/project-owned switch for expensive dynamic menu/dashboard sections.
    | Later tasks can consume this through App\Support\Security\EndpointProtectionPolicy.
    |
    */

    'graceful_degradation' => [
        'enabled' => (bool) env('GRACEFUL_DEGRADATION_ENABLED', false),
        'profiles' => [
            'admin_heavy_widgets' => [
                'description' => 'Prefer lightweight admin menu/dashboard rendering under pressure.',
                'menu' => [
                    'skip_expensive_dynamic_sections' => (bool) env('GRACEFUL_DEGRADATION_MENU_SKIP_EXPENSIVE_DYNAMIC_SECTIONS', false),
                ],
                'dashboard' => [
                    'skip_expensive_widgets' => (bool) env('GRACEFUL_DEGRADATION_DASHBOARD_SKIP_EXPENSIVE_WIDGETS', false),
                ],
            ],
            'public_chrome' => [
                'description' => 'Prefer lightweight public layout chrome under pressure.',
                'layout' => [
                    'skip_decorative_surfaces' => (bool) env('GRACEFUL_DEGRADATION_PUBLIC_SKIP_DECORATIVE_CHROME', false),
                ],
            ],
        ],
    ],

    /*
    |----------------------------------------------------------------------
    | Admin Performance Monitoring
    |----------------------------------------------------------------------
    |
    | Lightweight request logging for slow admin and admin-origin Livewire
    | traffic. Use this to identify real hotspots before optimizing blindly.
    |
    */

    'performance_monitoring' => [
        'enabled' => (bool) env('ADMIN_PERFORMANCE_MONITOR_ENABLED', env('APP_ENV') === 'local'),
        'request_threshold_ms' => (int) env('ADMIN_PERFORMANCE_REQUEST_THRESHOLD_MS', 1500),
        'query_threshold_ms' => (int) env('ADMIN_PERFORMANCE_QUERY_THRESHOLD_MS', 800),
        'log_channel' => env('ADMIN_PERFORMANCE_LOG_CHANNEL', 'admin_performance'),
        'single_query_threshold_ms' => (int) env('ADMIN_SLOW_QUERY_THRESHOLD_MS', 400),
        'query_log_channel' => env('ADMIN_QUERY_LOG_CHANNEL', 'admin_queries'),
    ],
];
