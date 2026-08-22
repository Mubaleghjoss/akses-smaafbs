<?php

return [
    'enabled' => env('WEBAUTHN_ENABLED', false),
    'rp_name' => env('WEBAUTHN_RP_NAME', 'SMA AFBS'),
    'rp_id' => env('WEBAUTHN_RP_ID', parse_url((string) env('APP_URL', ''), PHP_URL_HOST) ?: 'localhost'),
    'origin' => rtrim((string) env('WEBAUTHN_ORIGIN', env('APP_URL', 'http://localhost')), '/'),
    'timeout_seconds' => max(15, (int) env('WEBAUTHN_TIMEOUT_SECONDS', 60)),
    'challenge_ttl_minutes' => max(1, (int) env('WEBAUTHN_CHALLENGE_TTL_MINUTES', 5)),
    'max_credentials_per_user' => max(1, (int) env('WEBAUTHN_MAX_CREDENTIALS_PER_USER', 5)),
];
