<?php

namespace App\Support\ServerSync;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Process\Process;

class ServerDataPuller
{
    public function __construct(
        protected MariaDbDumpNormalizer $dumpNormalizer,
        protected StorageDirectorySwapper $storageSwapper,
    ) {}

    /**
     * @return array<int, string>
     */
    public function readinessErrors(): array
    {
        $errors = [];

        if (! app()->environment('local')) {
            $errors[] = 'Fitur tarik server hanya boleh dijalankan saat APP_ENV=local.';
        }

        foreach ([
            'SERVER_SYNC_SSH_HOST' => config('server_sync.ssh.host'),
            'SERVER_SYNC_SSH_USER' => config('server_sync.ssh.user'),
            'SERVER_SYNC_REMOTE_PATH' => config('server_sync.remote.base_path'),
            'SERVER_SYNC_REMOTE_DB_DATABASE' => config('server_sync.remote.db.database'),
            'SERVER_SYNC_REMOTE_DB_USERNAME' => config('server_sync.remote.db.username'),
        ] as $key => $value) {
            if (! filled($value)) {
                $errors[] = "Env {$key} belum diisi.";
            }
        }

        $connection = config('database.default');
        $driver = (string) config("database.connections.{$connection}.driver");

        if (! in_array($driver, ['mysql', 'mariadb'], true)) {
            $errors[] = 'Database lokal harus memakai koneksi mysql/mariadb agar bisa ditimpa dari dump server.';
        }

        if ($this->storagePairs() === []) {
            $errors[] = 'Env SERVER_SYNC_STORAGE_PATHS belum berisi pasangan folder storage yang valid.';
        }

        return $errors;
    }

    public function isReady(): bool
    {
        return $this->readinessErrors() === [];
    }

    public function testConnection(): void
    {
        $errors = $this->readinessErrors();

        if ($errors !== []) {
            throw new RuntimeException(implode("\n", $errors));
        }

        $this->assertRemoteAccess();
    }

    /**
     * @param  callable(string): void|null  $line
     * @return array{backup_path: ?string, dump_path: string, storage_paths: array<int, string>}
     */
    public function pull(?callable $line = null): array
    {
        $errors = $this->readinessErrors();

        if ($errors !== []) {
            throw new RuntimeException(implode("\n", $errors));
        }

        set_time_limit(0);
        $this->assertRemoteAccess($line);

        $timestamp = now()->format('Ymd-His');
        $backupPath = null;
        $tempPath = storage_path("app/server-sync-tmp/{$timestamp}");
        $remoteDumpPath = $tempPath.DIRECTORY_SEPARATOR.'server.sql';

        File::ensureDirectoryExists($tempPath);

        if ((bool) config('server_sync.backup.enabled', true)) {
            $backupPath = storage_path("app/server-sync-backups/{$timestamp}");
            $this->backupLocalState($backupPath, $line);
        }

        $this->downloadRemoteDatabase($remoteDumpPath, $line);
        $normalizedDump = $this->dumpNormalizer->normalizeForLocalClient($remoteDumpPath);

        if ($normalizedDump['removed_lines'] > 0) {
            $this->emit(
                $line,
                'Menyesuaikan format dump MariaDB server agar kompatibel dengan MySQL lokal...',
            );
        }

        if ($normalizedDump['excluded_tables'] !== []) {
            $this->emit(
                $line,
                'Menjaga sesi admin lokal agar login tidak terputus saat database ditimpa...',
            );
        }

        $this->importLocalDatabase($normalizedDump['path'], $line);

        $syncedStoragePaths = $this->pullStoragePaths($tempPath, $line);

        return [
            'backup_path' => $backupPath,
            'dump_path' => $remoteDumpPath,
            'storage_paths' => $syncedStoragePaths,
        ];
    }

    protected function assertRemoteAccess(?callable $line = null): void
    {
        $this->emit($line, 'Memeriksa SSH, folder server, dan akses database...');

        $process = $this->process([
            ...$this->sshCommand(),
            $this->remotePreflightCommand(),
        ]);

        $stderr = '';
        $process->run(function (string $type, string $buffer) use (&$stderr): void {
            if ($type === Process::ERR) {
                $stderr .= $buffer;
            }
        });

        if (! $process->isSuccessful()) {
            $detail = trim($stderr)
                ?: trim($process->getErrorOutput())
                ?: trim($process->getOutput())
                ?: 'Proses SSH berhenti dengan exit code '.($process->getExitCode() ?? 'tidak diketahui').'.';

            throw new RuntimeException(
                'Preflight server gagal: '.$detail
            );
        }
    }

    protected function backupLocalState(string $backupPath, ?callable $line): void
    {
        File::ensureDirectoryExists($backupPath);

        $this->emit($line, 'Membuat backup database lokal...');
        $this->dumpLocalDatabase($backupPath.DIRECTORY_SEPARATOR.'local-before.sql');

        foreach ($this->storagePairs() as $pair) {
            $localPath = $this->localPath($pair['local']);

            if (! File::exists($localPath)) {
                continue;
            }

            $target = $backupPath.DIRECTORY_SEPARATOR.'files'.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $pair['local']);
            File::ensureDirectoryExists(dirname($target));

            if (File::isDirectory($localPath)) {
                File::copyDirectory($localPath, $target);
            } else {
                File::copy($localPath, $target);
            }
        }
    }

    protected function downloadRemoteDatabase(string $dumpPath, ?callable $line): void
    {
        $this->emit($line, 'Mengunduh dump database server...');

        $handle = fopen($dumpPath, 'wb');

        if ($handle === false) {
            throw new RuntimeException("Tidak bisa membuat file dump: {$dumpPath}");
        }

        try {
            $process = $this->process([
                ...$this->sshCommand(),
                $this->remoteMysqlDumpCommand(),
            ]);

            $stderr = '';
            $process->run(function (string $type, string $buffer) use ($handle, &$stderr): void {
                if ($type === Process::OUT) {
                    fwrite($handle, $buffer);

                    return;
                }

                $stderr .= $buffer;
            });
        } finally {
            fclose($handle);
        }

        if (! $process->isSuccessful()) {
            File::delete($dumpPath);

            throw new RuntimeException(trim($stderr) ?: 'Gagal mengunduh database server.');
        }

        if ((int) File::size($dumpPath) < 32) {
            throw new RuntimeException('Dump database server kosong atau tidak valid.');
        }
    }

    protected function importLocalDatabase(string $dumpPath, ?callable $line): void
    {
        $this->emit($line, 'Mengimpor database server ke database lokal...');

        $input = fopen($dumpPath, 'rb');

        if ($input === false) {
            throw new RuntimeException("Tidak bisa membaca dump: {$dumpPath}");
        }

        try {
            $process = $this->process($this->localMysqlCommand());
            $process->setInput($input);

            $stderr = '';
            $process->run(function (string $type, string $buffer) use (&$stderr): void {
                if ($type === Process::ERR) {
                    $stderr .= $buffer;
                }
            });
        } finally {
            fclose($input);
        }

        if (! $process->isSuccessful()) {
            throw new RuntimeException(trim($stderr) ?: 'Gagal mengimpor dump server ke database lokal.');
        }
    }

    /**
     * @return array<int, string>
     */
    protected function pullStoragePaths(string $tempPath, ?callable $line): array
    {
        $synced = [];

        foreach ($this->storagePairs() as $index => $pair) {
            $remotePath = $this->remotePath($pair['remote']);

            if (! $this->remoteDirectoryExists($remotePath)) {
                $this->emit($line, "Lewati storage server yang tidak ada: {$pair['remote']}");

                continue;
            }

            $localPath = $this->localPath($pair['local']);
            $stagingPath = $tempPath.DIRECTORY_SEPARATOR.'storage-staging'.DIRECTORY_SEPARATOR.$index;
            $previousPath = $tempPath.DIRECTORY_SEPARATOR.'storage-previous'.DIRECTORY_SEPARATOR.$index;
            $this->prepareLocalDirectory($stagingPath);

            $this->emit($line, "Mengunduh storage ke staging: {$pair['remote']}");

            $process = $this->process([
                ...$this->scpCommand(),
                $this->sshTarget().':'.rtrim($remotePath, '/').'/.',
                $stagingPath,
            ]);

            $stderr = '';
            $process->run(function (string $type, string $buffer) use (&$stderr): void {
                if ($type === Process::ERR) {
                    $stderr .= $buffer;
                }
            });

            if (! $process->isSuccessful()) {
                throw new RuntimeException(trim($stderr) ?: "Gagal menyalin storage {$pair['remote']}.");
            }

            $this->emit($line, "Mengaktifkan storage lokal: {$pair['local']}");
            $mode = $this->storageSwapper->replace($stagingPath, $localPath, $previousPath);

            if ($mode === StorageDirectorySwapper::MODE_MIRRORED) {
                $this->emit($line, "Folder aktif {$pair['local']} sedang dipakai, isi storage disalin langsung tanpa memindahkan folder root.");
            }

            $synced[] = $pair['local'];
        }

        return $synced;
    }

    protected function dumpLocalDatabase(string $dumpPath): void
    {
        $handle = fopen($dumpPath, 'wb');

        if ($handle === false) {
            throw new RuntimeException("Tidak bisa membuat backup database lokal: {$dumpPath}");
        }

        try {
            $process = $this->process($this->localMysqlDumpCommand());

            $stderr = '';
            $process->run(function (string $type, string $buffer) use ($handle, &$stderr): void {
                if ($type === Process::OUT) {
                    fwrite($handle, $buffer);

                    return;
                }

                $stderr .= $buffer;
            });
        } finally {
            fclose($handle);
        }

        if (! $process->isSuccessful()) {
            File::delete($dumpPath);

            throw new RuntimeException(trim($stderr) ?: 'Gagal membuat backup database lokal.');
        }
    }

    protected function remoteDirectoryExists(string $remotePath): bool
    {
        $process = $this->process([
            ...$this->sshCommand(),
            'test -d '.$this->shellArg($remotePath),
        ]);

        $process->run();

        return $process->isSuccessful();
    }

    protected function prepareLocalDirectory(string $localPath): void
    {
        if (File::exists($localPath)) {
            if (File::isDirectory($localPath)) {
                File::cleanDirectory($localPath);

                return;
            }

            File::delete($localPath);
        }

        File::ensureDirectoryExists($localPath);
    }

    /**
     * @return array<int, array{remote: string, local: string}>
     */
    protected function storagePairs(): array
    {
        $raw = (string) config('server_sync.storage_paths', '');
        $pairs = [];

        foreach (explode(',', $raw) as $item) {
            $item = trim($item);

            if ($item === '') {
                continue;
            }

            [$remote, $local] = array_pad(explode(':', $item, 2), 2, null);

            $remote = $this->normalizeRelativePath((string) $remote);
            $local = $this->normalizeRelativePath((string) ($local ?: $remote));

            if ($remote === null || $local === null) {
                continue;
            }

            $pairs[] = [
                'remote' => $remote,
                'local' => $local,
            ];
        }

        return $pairs;
    }

    protected function normalizeRelativePath(string $path): ?string
    {
        $path = trim(str_replace('\\', '/', $path), '/');

        if ($path === '' || str_contains($path, '..') || preg_match('/^(?:[A-Za-z]:|\/|~)/', $path) === 1) {
            return null;
        }

        return $path;
    }

    protected function localPath(string $relativePath): string
    {
        $basePath = realpath(base_path()) ?: base_path();
        $path = base_path(str_replace('/', DIRECTORY_SEPARATOR, $relativePath));

        File::ensureDirectoryExists(dirname($path));

        $parent = realpath(dirname($path));

        if ($parent === false || ! Str::startsWith($parent.DIRECTORY_SEPARATOR, $basePath.DIRECTORY_SEPARATOR)) {
            throw new RuntimeException("Path lokal di luar project tidak diizinkan: {$relativePath}");
        }

        return $path;
    }

    protected function remotePath(string $relativePath): string
    {
        return rtrim((string) config('server_sync.remote.base_path'), '/').'/'.$relativePath;
    }

    /**
     * @return array<int, string>
     */
    protected function sshCommand(): array
    {
        $command = [
            (string) config('server_sync.binaries.ssh', 'ssh'),
            '-o',
            'BatchMode=yes',
            '-o',
            'ConnectTimeout=15',
            '-o',
            'StrictHostKeyChecking=accept-new',
            '-o',
            'UserKnownHostsFile='.(string) config('server_sync.ssh.known_hosts_file'),
            '-o',
            'GlobalKnownHostsFile=NUL',
            '-p',
            (string) config('server_sync.ssh.port', 22),
        ];

        if (filled(config('server_sync.ssh.identity_file'))) {
            $command[] = '-i';
            $command[] = (string) config('server_sync.ssh.identity_file');
        }

        $command[] = $this->sshTarget();

        return $command;
    }

    /**
     * @return array<int, string>
     */
    protected function scpCommand(): array
    {
        $command = [
            (string) config('server_sync.binaries.scp', 'scp'),
            '-P',
            (string) config('server_sync.ssh.port', 22),
            '-r',
        ];

        if (filled(config('server_sync.ssh.identity_file'))) {
            $command[] = '-i';
            $command[] = (string) config('server_sync.ssh.identity_file');
        }

        return $command;
    }

    protected function sshTarget(): string
    {
        return config('server_sync.ssh.user').'@'.config('server_sync.ssh.host');
    }

    protected function remoteMysqlDumpCommand(): string
    {
        $db = config('server_sync.remote.db');
        $password = (string) ($db['password'] ?? '');

        $parts = [
            'mysqldump',
            '--single-transaction',
            '--quick',
            '--skip-lock-tables',
            '--default-character-set=utf8mb4',
            '--host='.$this->shellArg((string) $db['host']),
            '--port='.$this->shellArg((string) $db['port']),
            '--user='.$this->shellArg((string) $db['username']),
        ];

        if ($password !== '') {
            $parts[] = '--password='.$this->shellArg($password);
        }

        $parts[] = $this->shellArg((string) $db['database']);

        return implode(' ', $parts);
    }

    protected function remotePreflightCommand(): string
    {
        $checks = [
            $this->remoteDirectoryCheck(
                (string) config('server_sync.remote.base_path'),
                'Folder root server tidak ditemukan',
            ),
            $this->remoteCommandCheck('mysqldump', 'Perintah mysqldump tidak tersedia di server'),
            $this->remoteCommandCheck('mysql', 'Perintah mysql tidak tersedia di server'),
        ];

        foreach ($this->storagePairs() as $pair) {
            $checks[] = $this->remoteDirectoryCheck(
                $this->remotePath($pair['remote']),
                "Folder storage server tidak ditemukan: {$pair['remote']}",
            );
        }

        $checks[] = $this->remoteMysqlCheckCommand();

        return implode(' && ', $checks);
    }

    protected function remoteDirectoryCheck(string $path, string $message): string
    {
        return 'test -d '.$this->shellArg($path)
            .' || { printf %s\\n '.$this->shellArg($message).' >&2; exit 21; }';
    }

    protected function remoteCommandCheck(string $command, string $message): string
    {
        return 'command -v '.$this->shellArg($command).' >/dev/null 2>&1'
            .' || { printf %s\\n '.$this->shellArg($message).' >&2; exit 22; }';
    }

    protected function remoteMysqlCheckCommand(): string
    {
        $db = config('server_sync.remote.db');
        $password = (string) ($db['password'] ?? '');

        $parts = [
            'mysql',
            '--connect-timeout=10',
            '--default-character-set=utf8mb4',
            '--host='.$this->shellArg((string) $db['host']),
            '--port='.$this->shellArg((string) $db['port']),
            '--user='.$this->shellArg((string) $db['username']),
        ];

        if ($password !== '') {
            $parts[] = '--password='.$this->shellArg($password);
        }

        $parts[] = $this->shellArg((string) $db['database']);
        $parts[] = '--execute='.$this->shellArg('SELECT 1');
        $parts[] = '>/dev/null';

        return implode(' ', $parts);
    }

    /**
     * @return array<int, string>
     */
    protected function localMysqlCommand(): array
    {
        return [
            ...$this->localMysqlBaseCommand((string) config('server_sync.binaries.mysql', 'mysql')),
            (string) $this->localDbConfig('database'),
        ];
    }

    /**
     * @return array<int, string>
     */
    protected function localMysqlDumpCommand(): array
    {
        return [
            ...$this->localMysqlBaseCommand((string) config('server_sync.binaries.mysqldump', 'mysqldump')),
            '--single-transaction',
            '--quick',
            '--skip-lock-tables',
            (string) $this->localDbConfig('database'),
        ];
    }

    /**
     * @return array<int, string>
     */
    protected function localMysqlBaseCommand(string $binary): array
    {
        $password = (string) $this->localDbConfig('password');
        $command = [
            $binary,
            '--default-character-set=utf8mb4',
            '--host='.(string) $this->localDbConfig('host'),
            '--port='.(string) $this->localDbConfig('port'),
            '--user='.(string) $this->localDbConfig('username'),
        ];

        if ($password !== '') {
            $command[] = '--password='.$password;
        }

        return $command;
    }

    protected function localDbConfig(string $key): mixed
    {
        $connection = config('database.default');

        return config("database.connections.{$connection}.{$key}");
    }

    /**
     * @param  array<int, string>  $command
     */
    protected function process(array $command): Process
    {
        $process = new Process($command, base_path());
        $process->setTimeout((int) config('server_sync.timeout', 3600));
        $process->setIdleTimeout((int) config('server_sync.timeout', 3600));

        return $process;
    }

    protected function shellArg(string $value): string
    {
        return "'".str_replace("'", "'\\''", $value)."'";
    }

    protected function emit(?callable $line, string $message): void
    {
        if ($line !== null) {
            $line($message);
        }
    }
}
