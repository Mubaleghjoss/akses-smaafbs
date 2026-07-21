<?php

return [
    'submission_queue' => [
        'enabled' => (bool) env('LITERACY_SUBMISSION_QUEUE_ENABLED', true),
        'active_slots' => max(1, (int) env('LITERACY_SUBMISSION_ACTIVE_SLOTS', 15)),
        'poll_seconds' => max(2, (int) env('LITERACY_SUBMISSION_POLL_SECONDS', 5)),
        'wait_ttl_minutes' => max(1, (int) env('LITERACY_SUBMISSION_WAIT_TTL_MINUTES', 10)),
        'admission_ttl_seconds' => 20,
        'processing_ttl_seconds' => 120,
        'completed_ttl_hours' => 24,
    ],

    'similarity_queue' => env('LITERACY_SIMILARITY_QUEUE', 'literacy-analysis'),
];
