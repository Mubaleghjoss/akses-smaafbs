<?php

namespace App\Console\Commands;

use App\Support\ServerSync\MariaDbDumpNormalizer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * Sinkronkan TABEL TERTENTU dari server (data siswa, guru, akun) ke database lokal.
 * Aman: backup lokal dulu, tabel ditimpa penuh (structure + data persis server).
 */
class ServerSyncTables extends Command
{
    protected $signature = 'server-sync:tabel
        {tables?* : Tabel yang disinkronkan (default: data_siswa guru_tendik users model_has_roles model_has_permissions)}
        {--check : Hanya daftarkan tabel yang tersedia di server, tanpa pull}
        {--no-backup : Lewati backup lokal sebelum import}';

    protected $description = 'Sinkronkan tabel pilihan (siswa/guru/akun) dari server ke DB lokal';

    public function handle(MariaDbDumpNormalizer $normalizer): int
    {
        $tables = $this->argument('tables') ?: [
            'data_siswa', 'guru_tendik', 'users', 'model_has_roles', 'model_has_permissions',
        ];
        $tables = array_values(array_unique(array_filter(array_map('trim', $tables))));
        $db = (array) config('server_sync.remote.db');
        $remoteDb = (string) ($db['database'] ?? '');
        $ssh = $this->sshCommand();

        if ($this->option('check')) {
            return $this->checkRemoteTables($ssh, $db, $remoteDb, $tables);
        }

        if ($tables === []) {
            $this->error('Tidak ada tabel yang diminta.');

            return self::FAILURE;
        }

        $this->info('Tabel yang akan disinkronkan: '.implode(', ', $tables));
        $this->info('Server: '.config('server_sync.ssh.user').'@'.config('server_sync.ssh.host').':'.config('server_sync.ssh.port'));

        $ts = now()->format('Ymd-His');
        $tempDir = storage_path("app/server-sync-tmp/tabel-{$ts}");
        File::ensureDirectoryExists($tempDir);
        $dumpPath = $tempDir.DIRECTORY_SEPARATOR.'server.sql';

        // 1. Backup lokal (tabel yang sama) sebelum ditimpa
        if (! $this->option('no-backup')) {
            $backupDir = storage_path("app/server-sync-backups/tabel-{$ts}");
            File::ensureDirectoryExists($backupDir);
            $this->info('Backup lokal sebelum import: '.$backupDir);
            $this->dumpLocalTables($tables, $backupDir.DIRECTORY_SEPARATOR.'local-before.sql');
        }

        // 2. Unduh dump remote (hanya tabel diminta)
        $this->info('Mengunduh dump server ('.implode(', ', $tables).')...');
        $this->downloadRemoteDump($ssh, $db, $remoteDb, $tables, $dumpPath);

        // 3. Normalisasi (MariaDB server -> MySQL lokal)
        $norm = $normalizer->normalizeForLocalClient($dumpPath);
        if ($norm['removed_lines'] > 0) {
            $this->warn("Normalisasi dump: {$norm['removed_lines']} baris disesuaikan.");
        }

        // 4. Import ke DB lokal
        $this->info('Import ke database lokal...');
        $this->importLocalDump($norm['path']);

        // 5. Verifikasi jumlah baris
        $this->newLine();
        $this->info('Hasil setelah import:');
        foreach ($tables as $t) {
            try {
                $count = (int) \Illuminate\Support\Facades\DB::table($t)->count();
                $this->info("  {$t}: {$count} baris");
            } catch (\Throwable $e) {
                $this->warn("  {$t}: gagal hitung ({$e->getMessage()})");
            }
        }

        $this->info('Selesai. Sumber dump: '.$dumpPath);

        return self::SUCCESS;
    }

    protected function checkRemoteTables(array $ssh, array $db, string $remoteDb, array $wanted): int
    {
        $sql = "SELECT table_name FROM information_schema.tables WHERE table_schema='".addslashes($remoteDb)."' AND table_name IN ('".implode("','", $wanted)."')";
        $process = new Process([...$ssh, $this->remoteMysqlCommand($db, $remoteDb, '-N -e '.$this->shellArg($sql))]);
        $process->setTimeout(60);
        $process->run();

        if (! $process->isSuccessful()) {
            $this->error('Gagal: '.trim($process->getErrorOutput() ?: $process->getOutput()));

            return self::FAILURE;
        }

        $found = array_values(array_filter(preg_split('/\s+/', trim($process->getOutput())) ?: []));
        $this->info('Tabel tersedia di server:');
        foreach ($wanted as $t) {
            $this->info('  '.$t.': '.(in_array($t, $found, true) ? 'ADA' : 'TIDAK ADA'));
        }

        return self::SUCCESS;
    }

    protected function downloadRemoteDump(array $ssh, array $db, string $remoteDb, array $tables, string $dumpPath): void
    {
        $handle = fopen($dumpPath, 'wb');
        if ($handle === false) {
            throw new RuntimeException("Tidak bisa membuat file dump: {$dumpPath}");
        }

        try {
            $process = new Process([...$ssh, $this->remoteMysqldumpCommand($db, $remoteDb, $tables)]);
            $process->setTimeout((int) config('server_sync.timeout', 3600));
            $stderr = '';
            $process->run(function (string $type, string $buffer) use ($handle, &$stderr): void {
                if ($type === Process::OUT) {
                    fwrite($handle, $buffer);

                    return;
                }
                $stderr .= $buffer;
            });

            if (! $process->isSuccessful()) {
                throw new RuntimeException('Unduh dump gagal: '.trim($stderr ?: $process->getErrorOutput() ?: $process->getOutput()));
            }
        } finally {
            fclose($handle);
        }

        $size = File::size($dumpPath);
        if ($size < 1024) {
            throw new RuntimeException("Dump terlalu kecil ({$size} byte) — kemungkinan gagal. File: {$dumpPath}");
        }
        $this->info('Dump diterima: '.number_format($size / 1024, 1).' KB');
    }

    protected function importLocalDump(string $dumpPath): void
    {
        $lc = (array) config('database.connections.mysql');
        $cmd = [
            (string) config('server_sync.binaries.mysql', 'mysql'),
            '--host='.(string) ($lc['host'] ?? '127.0.0.1'),
            '--port='.(string) ($lc['port'] ?? 3306),
            '--user='.(string) ($lc['username'] ?? 'root'),
            '--default-character-set=utf8mb4',
        ];
        if (filled((string) ($lc['password'] ?? ''))) {
            $cmd[] = '--password='.(string) $lc['password'];
        }
        $cmd[] = (string) ($lc['database'] ?? 'aksessmaafbs');

        $sql = (string) file_get_contents($dumpPath);
        if ($sql === '') {
            throw new RuntimeException("Dump kosong: {$dumpPath}");
        }

        $process = new Process($cmd);
        $process->setTimeout((int) config('server_sync.timeout', 3600));
        $process->setInput($sql);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new RuntimeException('Import lokal gagal: '.trim($process->getErrorOutput() ?: $process->getOutput()));
        }
    }

    protected function dumpLocalTables(array $tables, string $outPath): void
    {
        $lc = (array) config('database.connections.mysql');
        $cmd = [
            (string) config('server_sync.binaries.mysqldump', 'mysqldump'),
            '--single-transaction',
            '--skip-lock-tables',
            '--default-character-set=utf8mb4',
            '--host='.(string) ($lc['host'] ?? '127.0.0.1'),
            '--port='.(string) ($lc['port'] ?? 3306),
            '--user='.(string) ($lc['username'] ?? 'root'),
        ];
        if (filled((string) ($lc['password'] ?? ''))) {
            $cmd[] = '--password='.(string) $lc['password'];
        }
        $cmd[] = (string) ($lc['database'] ?? 'aksessmaafbs');
        array_push($cmd, ...$tables);

        $handle = fopen($outPath, 'wb');
        if ($handle === false) {
            throw new RuntimeException("Tidak bisa membuat backup lokal: {$outPath}");
        }
        try {
            $process = new Process($cmd);
            $process->setTimeout(600);
            $stderr = '';
            $process->run(function (string $type, string $buffer) use ($handle, &$stderr): void {
                if ($type === Process::OUT) {
                    fwrite($handle, $buffer);

                    return;
                }
                $stderr .= $buffer;
            });
            if (! $process->isSuccessful()) {
                throw new RuntimeException('Backup lokal gagal: '.trim($stderr ?: $process->getErrorOutput()));
            }
        } finally {
            fclose($handle);
        }
    }

    protected function remoteMysqldumpCommand(array $db, string $remoteDb, array $tables): string
    {
        $parts = [
            'mysqldump',
            '--single-transaction',
            '--quick',
            '--skip-lock-tables',
            '--default-character-set=utf8mb4',
            '--host='.$this->shellArg((string) ($db['host'] ?? 'localhost')),
            '--port='.$this->shellArg((string) ($db['port'] ?? 3306)),
            '--user='.$this->shellArg((string) ($db['username'] ?? '')),
        ];
        if (filled((string) ($db['password'] ?? ''))) {
            $parts[] = '--password='.$this->shellArg((string) $db['password']);
        }
        $parts[] = $this->shellArg($remoteDb);
        array_push($parts, ...array_map(fn (string $t): string => $this->shellArg($t), $tables));

        return implode(' ', $parts);
    }

    protected function remoteMysqlCommand(array $db, string $remoteDb, string $afterDb): string
    {
        $parts = [
            'mysql',
            '--host='.$this->shellArg((string) ($db['host'] ?? 'localhost')),
            '--port='.$this->shellArg((string) ($db['port'] ?? 3306)),
            '--user='.$this->shellArg((string) ($db['username'] ?? '')),
        ];
        if (filled((string) ($db['password'] ?? ''))) {
            $parts[] = '--password='.$this->shellArg((string) $db['password']);
        }
        $parts[] = $this->shellArg($remoteDb);
        $parts[] = $afterDb;

        return implode(' ', $parts);
    }

    protected function shellArg(string $value): string
    {
        return "'".str_replace("'", "'\\''", $value)."'";
    }

    /** @return array<int, string> */
    protected function sshCommand(): array
    {
        return [
            (string) config('server_sync.binaries.ssh', 'ssh'),
            '-o', 'BatchMode=yes',
            '-o', 'ConnectTimeout=15',
            '-o', 'StrictHostKeyChecking=accept-new',
            '-o', 'UserKnownHostsFile='.(string) config('server_sync.ssh.known_hosts_file'),
            '-o', 'GlobalKnownHostsFile=NUL',
            '-p', (string) config('server_sync.ssh.port', 2223),
            '-i', (string) config('server_sync.ssh.identity_file'),
            (string) config('server_sync.ssh.user').'@'.(string) config('server_sync.ssh.host'),
        ];
    }
}