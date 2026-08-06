<?php

return [
    'submission_queue' => [
        'enabled' => (bool) env('LITERACY_SUBMISSION_QUEUE_ENABLED', true),
        'active_slots' => max(1, (int) env('LITERACY_SUBMISSION_ACTIVE_SLOTS', 10)),
        'poll_seconds' => max(2, (int) env('LITERACY_SUBMISSION_POLL_SECONDS', 5)),
        'poll_middle_seconds' => max(5, (int) env('LITERACY_SUBMISSION_POLL_MIDDLE_SECONDS', 12)),
        'poll_far_seconds' => max(10, (int) env('LITERACY_SUBMISSION_POLL_FAR_SECONDS', 25)),
        'poll_middle_position' => max(1, (int) env('LITERACY_SUBMISSION_POLL_MIDDLE_POSITION', 30)),
        'poll_far_position' => max(31, (int) env('LITERACY_SUBMISSION_POLL_FAR_POSITION', 100)),
        'wait_ttl_minutes' => max(1, (int) env('LITERACY_SUBMISSION_WAIT_TTL_MINUTES', 10)),
        'mass_mode_enabled' => (bool) env('LITERACY_SUBMISSION_MASS_MODE_ENABLED', true),
        'initial_jitter_seconds' => max(0, (int) env('LITERACY_SUBMISSION_INITIAL_JITTER_SECONDS', 30)),
        'normal_initial_jitter_seconds' => max(0, (int) env('LITERACY_SUBMISSION_NORMAL_INITIAL_JITTER_SECONDS', 2)),
        'retry_delays_seconds' => array_values(array_filter(
            array_map('intval', explode(',', (string) env('LITERACY_SUBMISSION_RETRY_DELAYS_SECONDS', '5,10,20,30'))),
            fn (int $seconds): bool => $seconds > 0,
        )),
        'retry_window_seconds' => max(60, (int) env('LITERACY_SUBMISSION_RETRY_WINDOW_SECONDS', 600)),
        'draft_ttl_hours' => max(1, (int) env('LITERACY_SUBMISSION_DRAFT_TTL_HOURS', 12)),
        'analysis_idle_seconds' => max(30, (int) env('LITERACY_SUBMISSION_ANALYSIS_IDLE_SECONDS', 180)),
        'admission_ttl_seconds' => max(30, (int) env('LITERACY_SUBMISSION_ADMISSION_TTL_SECONDS', 60)),
        'processing_ttl_seconds' => 120,
        'completed_ttl_hours' => 24,
    ],

    'similarity_queue' => env('LITERACY_SIMILARITY_QUEUE', 'literacy-analysis'),
    'similarity_threshold' => min(100, max(0, (float) env('LITERACY_SIMILARITY_THRESHOLD', 80))),

    'school_monitor' => [
        'token' => env('LITERACY_SCHOOL_MONITOR_TOKEN'),
        'stale_minutes' => max(2, (int) env('LITERACY_SCHOOL_MONITOR_STALE_MINUTES', 10)),
    ],
];
