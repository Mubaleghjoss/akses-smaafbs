<?php

return [
    'token' => env('TAGIHAN_STUDENT_API_TOKEN'),
    'require_https' => (bool) env(
        'TAGIHAN_STUDENT_API_REQUIRE_HTTPS',
        env('APP_ENV') === 'production',
    ),
    'max_per_page' => 100,
];
