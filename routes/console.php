<?php

use App\Filament\Resources\BerkasGuruResource;
use App\Filament\Resources\BerkasSiswaResource;
use App\Filament\Resources\UserResource;
use App\Models\BerkasGuru;
use App\Models\BerkasSiswa;
use App\Models\User;
use App\Support\Admin\AdminModuleAccess;
use App\Support\Assessment\Reporting\AssessmentReportQueueGate;
use App\Support\Perpustakaan\LiteracySubmissionQueue;
use App\Support\ServerSync\ServerDataPuller;
use Filament\Facades\Filament;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('app:admin-performance-report {--limit=10 : Jumlah baris teratas per section}', function () {
    $limit = max(1, (int) $this->option('limit'));
    $logDirectory = storage_path('logs');
    $requestLogs = File::glob($logDirectory.DIRECTORY_SEPARATOR.'admin-performance*.log') ?: [];
    $queryLogs = File::glob($logDirectory.DIRECTORY_SEPARATOR.'admin-queries*.log') ?: [];

    $extractContext = function (string $line): ?array {
        $firstBrace = strpos($line, '{');
        $lastBrace = strrpos($line, '}');

        if ($firstBrace === false || $lastBrace === false || $lastBrace <= $firstBrace) {
            return null;
        }

        $payload = substr($line, $firstBrace, ($lastBrace - $firstBrace) + 1);
        $decoded = json_decode($payload, true);

        return is_array($decoded) ? $decoded : null;
    };

    $requestEntries = collect($requestLogs)
        ->flatMap(function (string $path) use ($extractContext): array {
            return collect(file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [])
                ->map(fn (string $line): ?array => str_contains($line, 'Slow admin request detected')
                    ? $extractContext($line)
                    : null)
                ->filter()
                ->values()
                ->all();
        });

    $queryEntries = collect($queryLogs)
        ->flatMap(function (string $path) use ($extractContext): array {
            return collect(file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [])
                ->map(fn (string $line): ?array => str_contains($line, 'Slow admin query detected')
                    ? $extractContext($line)
                    : null)
                ->filter()
                ->values()
                ->all();
        });

    if ($requestEntries->isEmpty() && $queryEntries->isEmpty()) {
        $this->warn('Belum ada data performa admin. Gunakan panel admin dulu, lalu cek lagi.');

        return 0;
    }

    $this->info('Ringkasan Performa Admin');
    $this->newLine();

    if ($requestEntries->isNotEmpty()) {
        $requestSummary = $requestEntries
            ->groupBy(fn (array $entry): string => (string) ($entry['path'] ?? 'unknown'))
            ->map(function ($items, string $path): array {
                $items = collect($items);

                return [
                    'path' => $path,
                    'hits' => $items->count(),
                    'max_ms' => (int) $items->max('duration_ms'),
                    'avg_ms' => (int) round($items->avg('duration_ms') ?? 0),
                    'avg_query_ms' => (int) round($items->avg('query_time_ms') ?? 0),
                    'avg_queries' => (int) round($items->avg('query_count') ?? 0),
                    'route' => (string) ($items->pluck('route_name')->filter()->mode()[0] ?? '-'),
                ];
            })
            ->sortByDesc('max_ms')
            ->take($limit)
            ->values()
            ->all();

        $this->line('Top Request Lambat');
        $this->table(
            ['path', 'hits', 'max_ms', 'avg_ms', 'avg_query_ms', 'avg_queries', 'route'],
            $requestSummary,
        );
    }

    if ($queryEntries->isNotEmpty()) {
        $querySummary = $queryEntries
            ->groupBy(function (array $entry): string {
                $sql = trim((string) ($entry['sql'] ?? ''));

                return Str::limit(preg_replace('/\s+/', ' ', $sql) ?: 'unknown', 90, '...');
            })
            ->map(function ($items, string $sql): array {
                $items = collect($items);

                return [
                    'sql' => $sql,
                    'hits' => $items->count(),
                    'max_ms' => (int) $items->max('time_ms'),
                    'avg_ms' => (int) round($items->avg('time_ms') ?? 0),
                    'path' => (string) ($items->pluck('path')->filter()->mode()[0] ?? '-'),
                    'route' => (string) ($items->pluck('route_name')->filter()->mode()[0] ?? '-'),
                ];
            })
            ->sortByDesc('max_ms')
            ->take($limit)
            ->values()
            ->all();

        $this->line('Top Query Lambat');
        $this->table(
            ['sql', 'hits', 'max_ms', 'avg_ms', 'path', 'route'],
            $querySummary,
        );
    }

    return 0;
})->purpose('Ringkas log request dan query lambat admin');

Schedule::call(function (): void {
    $updateWorkerState = function (array $values): void {
        if (! Schema::hasTable('perpustakaan_literasi_submission_queue_states')) {
            return;
        }

        $availableValues = collect($values)
            ->filter(fn (mixed $value, string $column): bool => Schema::hasColumn(
                'perpustakaan_literasi_submission_queue_states',
                $column,
            ))
            ->all();

        if ($availableValues !== []) {
            DB::table('perpustakaan_literasi_submission_queue_states')
                ->where('scope', LiteracySubmissionQueue::SCOPE)
                ->update($availableValues + ['updated_at' => now()]);
        }
    };

    $updateWorkerState(['scheduler_heartbeat_at' => now()]);

    if (app(LiteracySubmissionQueue::class)->analysisShouldWait()) {
        $updateWorkerState(['worker_status' => 'paused_for_submissions']);

        return;
    }

    $updateWorkerState([
        'worker_started_at' => now(),
        'worker_status' => 'running',
    ]);

    try {
        $literacyQueue = (string) config('literacy.similarity_queue', 'literacy-analysis');

        Artisan::call('queue:work', [
            '--queue' => implode(',', array_values(array_unique([$literacyQueue, 'default']))),
            '--stop-when-empty' => true,
            '--max-jobs' => 3,
            '--max-time' => 20,
            '--tries' => 3,
            '--sleep' => 1,
            '--timeout' => 120,
            '--no-interaction' => true,
        ]);

        $updateWorkerState(['worker_status' => 'idle']);
    } catch (Throwable $exception) {
        $updateWorkerState(['worker_status' => 'error']);

        throw $exception;
    } finally {
        $updateWorkerState(['worker_finished_at' => now()]);
    }
})
    ->everyMinute()
    ->name('queue-controlled-worker')
    ->withoutOverlapping(10);

Schedule::call(function (): void {
    if (! app(AssessmentReportQueueGate::class)->shouldRun()) {
        return;
    }

    Artisan::call('queue:work', [
        '--queue' => (string) config('assessment.reports.queue', 'assessment-reports'),
        '--stop-when-empty' => true,
        // Shared hosting selalu memproses tepat satu PDF pada setiap putaran.
        '--max-jobs' => 1,
        '--max-time' => max(10, (int) config('assessment.reports.worker.max_time', 50)),
        '--tries' => 3,
        '--sleep' => 1,
        '--timeout' => max(60, (int) config('assessment.reports.worker.timeout', 180)),
        '--no-interaction' => true,
    ]);
})
    ->everyMinute()
    ->name('assessment-report-worker')
    ->withoutOverlapping(10);

Schedule::call(function (): void {
    if (Schema::hasTable('perpustakaan_literasi_submission_events')) {
        DB::table('perpustakaan_literasi_submission_events')
            ->where('occurred_at', '<', now()->subDays(30))
            ->delete();
    }

    if (Schema::hasTable('perpustakaan_literasi_network_checks')) {
        DB::table('perpustakaan_literasi_network_checks')
            ->where('checked_at', '<', now()->subDays(90))
            ->delete();
    }
})
    ->dailyAt('02:15')
    ->name('literacy-operational-log-cleanup')
    ->withoutOverlapping();

Schedule::command('assessment:cleanup-report-cache --apply')
    ->hourly()
    ->name('assessment-report-cache-cleanup')
    ->withoutOverlapping();

Schedule::command('app:storage-maintain --apply')
    ->dailyAt('02:40')
    ->name('application-storage-maintenance')
    ->withoutOverlapping();

Schedule::command('app:storage-audit')
    ->dailyAt('03:00')
    ->name('hosting-storage-audit')
    ->withoutOverlapping();

Artisan::command('app:backfill-module-access-levels {--dry-run : Tampilkan perubahan tanpa menyimpan} {--force : Paksa tulis ulang user yang sudah punya module_access_levels}', function () {
    $panel = Filament::getPanel('admin');

    if (! $panel) {
        $this->error('Panel admin tidak ditemukan.');

        return 1;
    }

    $scanned = 0;
    $updated = 0;
    $skippedAdmin = 0;
    $skippedNonPanel = 0;
    $skippedExplicit = 0;
    $skippedUnchanged = 0;

    User::query()
        ->with(['roles', 'permissions'])
        ->orderBy('id')
        ->chunkById(100, function ($users) use ($panel, &$scanned, &$updated, &$skippedAdmin, &$skippedNonPanel, &$skippedExplicit, &$skippedUnchanged): void {
            /** @var User $user */
            foreach ($users as $user) {
                $scanned++;

                if ($user->hasRole('admin')) {
                    $skippedAdmin++;

                    continue;
                }

                if (! $user->canAccessPanel($panel)) {
                    $skippedNonPanel++;

                    continue;
                }

                $hasExplicitLevels = ! is_null($user->getRawOriginal('module_access_levels'));

                if ($hasExplicitLevels && ! $this->option('force')) {
                    $skippedExplicit++;

                    continue;
                }

                $desiredLevels = AdminModuleAccess::effectiveLevels($user);
                $currentLevels = $hasExplicitLevels
                    ? AdminModuleAccess::normalizeLevels($user->module_access_levels ?? [])
                    : null;

                if ($currentLevels === $desiredLevels && ! $this->option('force')) {
                    $skippedUnchanged++;

                    continue;
                }

                if ($this->option('dry-run')) {
                    $updated++;
                    $this->line("[dry-run] user #{$user->id} {$user->username} => ".json_encode($desiredLevels, JSON_UNESCAPED_UNICODE));

                    continue;
                }

                UserResource::syncScopedModuleConfiguration($user, [
                    'roles' => $user->roles->pluck('id')->all(),
                    'module_access_levels' => $desiredLevels,
                    'allowed_navigation_items' => $user->allowed_navigation_items ?? [],
                ]);

                $updated++;
                $this->info("updated user #{$user->id} {$user->username}");
            }
        });

    $this->newLine();
    $this->table(['scanned', 'updated', 'skip_admin', 'skip_non_panel', 'skip_explicit', 'skip_unchanged'], [[
        $scanned,
        $updated,
        $skippedAdmin,
        $skippedNonPanel,
        $skippedExplicit,
        $skippedUnchanged,
    ]]);

    return 0;
})->purpose('Backfill existing panel users into module_access_levels');

Artisan::command('app:normalize-berkas-drive {scope=all : siswa|guru|all} {--without-sync : Hanya rapikan file lokal/database tanpa antrekan sinkron ulang}', function () {
    $scope = strtolower((string) $this->argument('scope'));

    if (! in_array($scope, ['all', 'siswa', 'guru'], true)) {
        $this->error('Scope harus salah satu dari: all, siswa, guru.');

        return 1;
    }

    $withoutSync = (bool) $this->option('without-sync');
    $summary = [
        'siswa' => ['scanned' => 0, 'normalized' => 0, 'queued' => 0, 'failed' => 0],
        'guru' => ['scanned' => 0, 'normalized' => 0, 'queued' => 0, 'failed' => 0],
    ];

    $runScope = function (string $label, iterable $records, callable $normalizer, callable $queuer) use (&$summary, $withoutSync): void {
        foreach ($records as $record) {
            $summary[$label]['scanned']++;

            try {
                if (! $record->hasUploadableFiles()) {
                    continue;
                }

                if ($normalizer($record)) {
                    $summary[$label]['normalized']++;
                }

                if (! $withoutSync) {
                    $queuer($record->fresh());
                    $summary[$label]['queued']++;
                }
            } catch (Throwable $exception) {
                $summary[$label]['failed']++;
                $this->warn("{$label} #{$record->getKey()} gagal: {$exception->getMessage()}");
            }
        }
    };

    if (in_array($scope, ['all', 'siswa'], true)) {
        $this->info('Memproses berkas siswa...');
        $runScope(
            'siswa',
            BerkasSiswa::query()->with(['siswa:id,nama', 'jenisBerkas:id,nama_berkas'])->orderBy('id')->lazyById(100),
            fn (BerkasSiswa $record): bool => BerkasSiswaResource::normalizeRecord($record),
            fn (BerkasSiswa $record): string => BerkasSiswaResource::queueGoogleDriveSync($record),
        );
    }

    if (in_array($scope, ['all', 'guru'], true)) {
        $this->info('Memproses berkas guru...');
        $runScope(
            'guru',
            BerkasGuru::query()->with(['guru:id,nama', 'jenisBerkas:id,nama_berkas'])->orderBy('id')->lazyById(100),
            fn (BerkasGuru $record): bool => BerkasGuruResource::normalizeRecord($record),
            fn (BerkasGuru $record): string => BerkasGuruResource::queueGoogleDriveSync($record),
        );
    }

    $this->newLine();
    $this->table(
        ['scope', 'scanned', 'normalized', 'queued', 'failed'],
        [
            ['siswa', $summary['siswa']['scanned'], $summary['siswa']['normalized'], $summary['siswa']['queued'], $summary['siswa']['failed']],
            ['guru', $summary['guru']['scanned'], $summary['guru']['normalized'], $summary['guru']['queued'], $summary['guru']['failed']],
        ],
    );

    $this->info($withoutSync
        ? 'Rapikan berkas selesai tanpa antre sinkron ulang.'
        : 'Rapikan berkas dan antre sinkron Google Drive selesai.');

    return 0;
})->purpose('Normalize nama/path berkas siswa/guru lalu opsional antrekan sinkron ulang ke Google Drive');

Artisan::command('app:reset-data {--force : Wajib untuk menjalankan reset}', function () {
    if (! app()->environment('local')) {
        $this->error('Command ini hanya boleh dijalankan di APP_ENV=local.');

        return 1;
    }

    if (! $this->option('force')) {
        $this->error('Tambahkan flag --force untuk menjalankan reset.');

        return 1;
    }

    // Daftar tabel aplikasi (legacy + laravel) yang aman di-truncate untuk local testing.
    $tables = [
        // legacy
        'admin',
        'berita',
        'galeri',
        'calendar_events',
        'event_timeline',
        'data_siswa',
        'status_siswa',
        'kelas',
        'jenis_berkas',
        'berkas_siswa',
        'guru_tendik',
        'berkas_guru',
        'uks_records',
        'pengaturan',
        'visitor_counter',
        'log_aktivitas',
        'perpustakaan_aktivitas',
        'perpustakaan_buku',
        'perpustakaan_ebook_logs',
        'perpustakaan_hasil_literasi',
        'perpustakaan_kategori',
        'perpustakaan_lemari',
        'perpustakaan_statistik',
        'spp_bills',
        'spp_fee_types',
        'spp_payment_attachments',
        'spp_settings',
        'sync_settings',
        'sync_tombstones',

        // laravel
        'users',
        'password_reset_tokens',
        'sessions',
        'roles',
        'permissions',
        'model_has_roles',
        'model_has_permissions',
        'role_has_permissions',
        'cache',
        'cache_locks',
        'jobs',
        'job_batches',
        'failed_jobs',
    ];

    $tables = array_values(array_unique($tables));

    DB::beginTransaction();
    try {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        foreach ($tables as $table) {
            try {
                DB::table($table)->truncate();
                $this->line("truncated: {$table}");
            } catch (Throwable $e) {
                // Tabel mungkin tidak ada di DB tertentu; skip.
                $this->warn("skip: {$table} ({$e->getMessage()})");
            }
        }
        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        DB::commit();
    } catch (Throwable $e) {
        DB::rollBack();
        throw $e;
    }

    // Bersihkan file upload local.
    $storagePath = public_path('storage');
    if (File::exists($storagePath)) {
        File::cleanDirectory($storagePath);
        $this->info('public/storage dibersihkan.');
    }

    // Recreate folder dasar agar FileUpload tidak error.
    File::ensureDirectoryExists(public_path('storage/news'));
    File::ensureDirectoryExists(public_path('storage/gallery'));
    File::ensureDirectoryExists(public_path('storage/ebooks'));
    File::ensureDirectoryExists(public_path('storage/spp/payments'));
    File::ensureDirectoryExists(public_path('storage/documents/students'));
    File::ensureDirectoryExists(public_path('storage/documents/staff'));
    File::ensureDirectoryExists(public_path('storage/events/timeline'));

    // Jalankan seeder admin + roles kalau ada.
    $this->call('db:seed', [
        '--class' => 'InitialAdminSeeder',
        '--force' => true,
    ]);

    $this->info('Reset data selesai.');

    return 0;
})->purpose('Reset seluruh data (LOCAL ONLY)');

Artisan::command('app:pull-server-data {--force : Wajib untuk menimpa database dan storage lokal}', function () {
    $puller = app(ServerDataPuller::class);

    if (! $this->option('force')) {
        $this->error('Tambahkan flag --force untuk menarik data server dan menimpa data lokal.');

        return 1;
    }

    $errors = $puller->readinessErrors();

    if ($errors !== []) {
        foreach ($errors as $error) {
            $this->error($error);
        }

        return 1;
    }

    $result = $puller->pull(function (string $line): void {
        $this->line($line);
    });

    $this->newLine();
    $this->info('Tarik data server selesai.');
    $this->line('Backup lokal: '.($result['backup_path'] ?: 'dinonaktifkan'));
    $this->line('Dump server: '.$result['dump_path']);
    $this->line('Storage tersinkron: '.(implode(', ', $result['storage_paths']) ?: '-'));

    return 0;
})->purpose('Tarik database dan file storage server ke lokal lalu timpa data lokal (LOCAL ONLY)');
