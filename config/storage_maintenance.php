<?php

return [
    'hosting_root' => env('HOSTING_STORAGE_ROOT'),
    'quota_gb' => max(1, (float) env('HOSTING_DISK_QUOTA_GB', 10)),
    'warning_percent' => 70,
    'critical_percent' => 80,
    'danger_percent' => 90,
    'temporary_hours' => 24,
    'log_days' => 14,
    'media_backup_days' => 7,
    'orphan_quarantine_days' => 7,
    'audit_cache_key' => 'hosting-storage-audit-v1',
];
