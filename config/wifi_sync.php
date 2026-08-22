<?php

return [
    // Sinkron akun WiFi dari API baca aplikasi MikroTik (mikrotik.smaafbs.sch.id).
    // Endpoint read-only: GET {base_url}/api-hotspot.php dengan header Bearer token.
    // Nilai nyata diisi di .env (jangan commit).
    'enabled' => (bool) env('WIFI_SYNC_ENABLED', false),
    'base_url' => env('WIFI_SYNC_BASE_URL', ''),
    'token' => env('WIFI_SYNC_TOKEN', ''),
    'timeout' => (int) env('WIFI_SYNC_TIMEOUT', 30),
];
