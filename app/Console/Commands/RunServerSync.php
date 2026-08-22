<?php

namespace App\Console\Commands;

use App\Support\Admin\Dashboard\DashboardCacheSupport;
use App\Support\ServerSync\ServerDataPuller;
use App\Support\ServerSync\ServerSyncRunStore;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class RunServerSync extends Command
{
    protected $signature = 'server-sync:run
        {operation : test atau pull}
        {--request-id= : ID proses sinkron}';

    protected $description = 'Menjalankan uji koneksi atau tarik data server dari proses CLI.';

    public function handle(ServerDataPuller $puller, ServerSyncRunStore $store): int
    {
        $operation = (string) $this->argument('operation');
        $runId = (string) ($this->option('request-id') ?: '');

        if (! in_array($operation, ['test', 'pull'], true) || $runId === '') {
            $this->error('Operation atau request-id tidak valid.');

            return self::INVALID;
        }

        $startedAt = microtime(true);
        $store->markRunning($runId);
        $store->append($runId, 'info', 'Runner CLI aktif.');
        $store->append(
            $runId,
            'info',
            $operation === 'pull' ? 'Tarik data server dimulai.' : 'Uji koneksi server dimulai.',
        );

        $writeLog = function (string $level, string $message) use ($runId, $operation, $store): void {
            $store->append($runId, $level, $message);
            Log::channel('server_sync')->{$level === 'error' ? 'error' : 'info'}($message, [
                'run_id' => $runId,
                'operation' => $operation,
            ]);
        };

        try {
            if ($operation === 'test') {
                $writeLog('info', 'Memeriksa SSH, folder sumber, mysqldump, dan database server.');
                $puller->testConnection();
                $result = null;
                $writeLog('success', 'Koneksi server berhasil. Semua pemeriksaan preflight lulus.');
            } else {
                $result = $puller->pull(fn (string $message): mixed => $writeLog('info', $message));
                DashboardCacheSupport::forgetModule('google_drive_monitor');
                $writeLog(
                    'success',
                    'Tarik data selesai. Storage: '.(implode(', ', $result['storage_paths']) ?: '-'),
                );
            }

            $store->finish($runId, 'success', $result);

            Log::channel('server_sync')->info('Proses sinkron server selesai.', [
                'run_id' => $runId,
                'operation' => $operation,
                'status' => 'success',
                'duration_seconds' => round(microtime(true) - $startedAt, 2),
            ]);

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $writeLog('error', trim($exception->getMessage()));
            $store->finish($runId, 'error');

            Log::channel('server_sync')->error('Proses sinkron server gagal.', [
                'run_id' => $runId,
                'operation' => $operation,
                'duration_seconds' => round(microtime(true) - $startedAt, 2),
                'exception' => $exception,
            ]);

            return self::FAILURE;
        }
    }
}
