<?php

return [
    'headers_enabled' => env('SECURITY_HEADERS_ENABLED', true),

    /*
    | CSP dimulai sebagai report-only. Mengaktifkan mode enforce harus dilakukan
    | setelah Filament, Livewire, passkey, MathJax, serta embed materi diuji.
    */
    'csp_mode' => env('SECURITY_CSP_MODE', 'report-only'),

    'csp_policy' => implode('; ', [
        "default-src 'self'",
        "base-uri 'self'",
        "object-src 'none'",
        "form-action 'self'",
        "frame-ancestors 'self'",
        "img-src 'self' data: blob: https:",
        "font-src 'self' data:",
        "connect-src 'self'",
        "script-src 'self' https://cdn.jsdelivr.net",
        "style-src 'self' 'unsafe-inline'",
        "frame-src https://www.youtube.com https://www.youtube-nocookie.com https://drive.google.com",
        "media-src 'self' blob: https:",
        "worker-src 'self' blob:",
        "manifest-src 'self'",
    ]),

    /*
    | Naikkan bertahap: 300 -> 86400 -> 2592000 -> 31536000.
    | Jangan memakai includeSubDomains/preload sebelum semua subdomain diaudit.
    */
    'hsts_max_age' => max(0, (int) env('SECURITY_HSTS_MAX_AGE', 300)),

    'referrer_policy' => env('SECURITY_REFERRER_POLICY', 'strict-origin-when-cross-origin'),

    'permissions_policy' => env(
        'SECURITY_PERMISSIONS_POLICY',
        'accelerometer=(), camera=(), geolocation=(), gyroscope=(), magnetometer=(), microphone=(self), payment=(), usb=()'
    ),
];
