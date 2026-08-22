<?php

$bool = static fn (string $key, bool $default = false): bool => filter_var(
    env($key, $default),
    FILTER_VALIDATE_BOOL,
);

return [
    'receiver' => [
        'enabled' => $bool('STUDENT_SYNC_RECEIVER_ENABLED', false),
        'client_id' => env('STUDENT_SYNC_RECEIVER_CLIENT_ID'),
        'secret' => env('STUDENT_SYNC_RECEIVER_SECRET'),
    ],
    'client' => [
        'enabled' => $bool('STUDENT_SYNC_CLIENT_ENABLED', false),
        'server_url' => rtrim((string) env('STUDENT_SYNC_SERVER_URL', 'https://app.smaafbs.sch.id'), '/'),
        'client_id' => env('STUDENT_SYNC_CLIENT_ID'),
        'secret' => env('STUDENT_SYNC_SECRET'),
        'timeout' => (int) env('STUDENT_SYNC_TIMEOUT', 60),
    ],
    'security' => [
        'clock_skew_seconds' => (int) env('STUDENT_SYNC_CLOCK_SKEW', 300),
        'preview_ttl_seconds' => (int) env('STUDENT_SYNC_PREVIEW_TTL', 900),
        'max_batch' => (int) env('STUDENT_SYNC_MAX_BATCH', 250),
    ],
    'denied_fields' => [
        'id', 'created_at', 'updated_at', 'status', 'kategori_non_aktif',
        'alasan_non_aktif', 'tanggal_non_aktif', 'spmb_synced_at',
        'spmb_source_updated_at',
    ],
];
