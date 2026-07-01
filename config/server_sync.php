<?php

$bool = static fn (string $key, bool $default = false): bool => filter_var(
    env($key, $default),
    FILTER_VALIDATE_BOOL,
);

return [
    'env_path' => env(
        'SERVER_SYNC_ENV_PATH',
        app()->runningUnitTests() ? storage_path('framework/testing/server-sync.env') : base_path('.env'),
    ),

    'api' => [
        'enabled' => $bool('SERVER_SYNC_API_ENABLED', true),
        'domain' => env('SERVER_SYNC_DOMAIN', 'https://app.smaafbs.sch.id'),
    ],

    'ssh' => [
        'host' => env('SERVER_SYNC_SSH_HOST'),
        'user' => env('SERVER_SYNC_SSH_USER'),
        'port' => (int) env('SERVER_SYNC_SSH_PORT', 22),
        'identity_file' => env('SERVER_SYNC_SSH_KEY'),
        'known_hosts_file' => env(
            'SERVER_SYNC_SSH_KNOWN_HOSTS',
            storage_path('app/server-sync/ssh/known_hosts'),
        ),
    ],

    'remote' => [
        'base_path' => env('SERVER_SYNC_REMOTE_PATH'),
        'db' => [
            'host' => env('SERVER_SYNC_REMOTE_DB_HOST', 'localhost'),
            'port' => (int) env('SERVER_SYNC_REMOTE_DB_PORT', 3306),
            'database' => env('SERVER_SYNC_REMOTE_DB_DATABASE'),
            'username' => env('SERVER_SYNC_REMOTE_DB_USERNAME'),
            'password' => env('SERVER_SYNC_REMOTE_DB_PASSWORD'),
        ],
    ],

    'storage_paths' => env(
        'SERVER_SYNC_STORAGE_PATHS',
        'public/storage:public/storage,storage/app/public:storage/app/public,storage/app/private:storage/app/private',
    ),

    'binaries' => [
        'ssh' => env('SERVER_SYNC_SSH_BINARY', 'ssh'),
        'scp' => env('SERVER_SYNC_SCP_BINARY', 'scp'),
        'mysql' => env('SERVER_SYNC_MYSQL_BINARY', 'mysql'),
        'mysqldump' => env('SERVER_SYNC_MYSQLDUMP_BINARY', 'mysqldump'),
        'schtasks' => env('SERVER_SYNC_SCHTASKS_BINARY', 'schtasks.exe'),
    ],

    'runner' => [
        'task_name' => env('SERVER_SYNC_RUNNER_TASK', 'Akses2ServerSyncRunner'),
    ],

    'backup' => [
        'enabled' => $bool('SERVER_SYNC_BACKUP_LOCAL', true),
    ],

    'timeout' => (int) env('SERVER_SYNC_TIMEOUT', 3600),
];
