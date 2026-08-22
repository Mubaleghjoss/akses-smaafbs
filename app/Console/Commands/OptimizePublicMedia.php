<?php

namespace App\Console\Commands;

use App\Support\Media\PublicImageOptimizer;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class OptimizePublicMedia extends Command
{
    protected $signature = 'media:optimize-public
        {--dry-run : Tampilkan inventaris dan estimasi tanpa menulis file/database}
        {--apply : Optimalkan satu batch dan simpan manifest rollback}
        {--batch=25 : Jumlah maksimum referensi gambar per eksekusi}
        {--rollback= : ID atau path manifest yang akan dikembalikan}';

    protected $description = 'Optimalkan gambar public storage secara bertahap dengan backup dan rollback manifest';

    public function handle(PublicImageOptimizer $optimizer): int
    {
        if (filled($this->option('rollback'))) {
            return $this->rollback((string) $this->option('rollback'));
        }

        if (! $this->option('dry-run') && ! $this->option('apply')) {
            $this->error('Pilih salah satu: --dry-run, --apply, atau --rollback=<manifest>.');

            return self::INVALID;
        }

        $tasks = $this->tasks();

        if ($tasks->isEmpty()) {
            $this->info('Tidak ada gambar yang perlu dioptimalkan.');

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            return $this->dryRun($tasks);
        }

        return $this->applyBatch($tasks, $optimizer);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    protected function tasks(): Collection
    {
        $tasks = collect();

        foreach ([
            ['perpustakaan_literasi_materials', 'image_path', 'material'],
            ['perpustakaan_literasi_questions', 'image_path', 'question'],
            ['struktur_organisasis', 'foto', 'structure'],
            ['berita', 'gambar', 'content'],
        ] as [$table, $column, $profile]) {
            $tasks = $tasks->concat($this->directColumnTasks($table, $column, $profile));
        }

        if (Schema::hasTable('pengaturan')
            && Schema::hasColumns('pengaturan', ['id', 'nama_pengaturan', 'nilai_pengaturan'])) {
            $settings = DB::table('pengaturan')
                ->whereIn('nama_pengaturan', ['logo_path', 'favicon_path'])
                ->whereNotNull('nilai_pengaturan')
                ->get(['id', 'nama_pengaturan', 'nilai_pengaturan']);

            foreach ($settings as $setting) {
                $path = $this->normalizePath((string) $setting->nilai_pengaturan);

                if (! $this->isOptimizablePath($path)) {
                    continue;
                }

                $tasks->push([
                    'table' => 'pengaturan',
                    'id' => (int) $setting->id,
                    'column' => 'nilai_pengaturan',
                    'profile' => $setting->nama_pengaturan === 'favicon_path' ? 'favicon' : 'logo',
                    'path' => $path,
                    'original_value' => (string) $setting->nilai_pengaturan,
                    'embedded' => false,
                ]);
            }
        }

        foreach ([
            ['perpustakaan_literasi_materials', 'reading_content', 'literasi/materials/reading', 'content'],
            ['profil_sekolahs', 'fasilitas', 'identitas-sekolah/fasilitas', 'content'],
            ['profil_sekolahs', 'fasilitas', 'profil-sekolah/fasilitas', 'content'],
            ['berita', 'tracker_documentation_media', 'news/documentation', 'content'],
            ['berita_updates', 'documentation_media', 'news/documentation', 'content'],
            ['prestasis', 'dokumentasi', 'prestasi', 'content'],
            ['prestasis', 'sertifikat_files', 'prestasi', 'content'],
        ] as [$table, $column, $directory, $profile]) {
            $tasks = $tasks->concat($this->embeddedColumnTasks($table, $column, $directory, $profile));
        }

        return $tasks
            ->filter(fn (array $task): bool => ! Str::endsWith($task['path'], [
                '-optimized.webp',
                '-thumb.webp',
                '-favicon.png',
                '-pwa-192.png',
                '-pwa-512.png',
            ]))
            ->unique(fn (array $task): string => implode('|', [
                $task['table'],
                $task['id'],
                $task['column'],
                $task['path'],
            ]))
            ->values();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    protected function directColumnTasks(string $table, string $column, string $profile): Collection
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumns($table, ['id', $column])) {
            return collect();
        }

        return DB::table($table)
            ->whereNotNull($column)
            ->where($column, '!=', '')
            ->get(['id', $column])
            ->map(function (object $row) use ($table, $column, $profile): ?array {
                $value = (string) $row->{$column};
                $path = $this->normalizePath($value);

                if (! $this->isOptimizablePath($path)) {
                    return null;
                }

                return [
                    'table' => $table,
                    'id' => (int) $row->id,
                    'column' => $column,
                    'profile' => $profile,
                    'path' => $path,
                    'original_value' => $value,
                    'embedded' => false,
                ];
            })
            ->filter()
            ->values();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    protected function embeddedColumnTasks(
        string $table,
        string $column,
        string $directory,
        string $profile,
    ): Collection {
        if (! Schema::hasTable($table) || ! Schema::hasColumns($table, ['id', $column])) {
            return collect();
        }

        $tasks = collect();
        $quotedDirectory = preg_quote(trim($directory, '/'), '#');

        foreach (DB::table($table)->whereNotNull($column)->get(['id', $column]) as $row) {
            $value = (string) $row->{$column};
            preg_match_all(
                '#(?:https?://[^"\'\s]+/storage/|/?storage/)?(?<path>'.$quotedDirectory.'/[^"\'<>\s\\\\]+?\.(?:avif|gif|jpe?g|png|webp))#i',
                $value,
                $matches,
            );

            foreach (array_unique($matches['path'] ?? []) as $matchedPath) {
                $path = $this->normalizePath((string) $matchedPath);

                if (! $this->isOptimizablePath($path)) {
                    continue;
                }

                $tasks->push([
                    'table' => $table,
                    'id' => (int) $row->id,
                    'column' => $column,
                    'profile' => $profile,
                    'path' => $path,
                    'original_value' => $value,
                    'embedded' => true,
                ]);
            }
        }

        return $tasks;
    }

    protected function dryRun(Collection $tasks): int
    {
        $public = Storage::disk('public');
        $totalBytes = 0;
        $existing = 0;

        foreach ($tasks as $task) {
            if (! $public->exists($task['path'])) {
                continue;
            }

            $existing++;
            $totalBytes += (int) $public->size($task['path']);
        }

        $this->table(
            ['Metrik', 'Nilai'],
            [
                ['Referensi ditemukan', number_format($tasks->count(), 0, ',', '.')],
                ['File tersedia', number_format($existing, 0, ',', '.')],
                ['Ukuran sumber', $this->formatBytes($totalBytes)],
                ['Estimasi setelah optimasi', $this->formatBytes((int) round($totalBytes * 0.25))],
                ['Potensi penghematan', $this->formatBytes((int) round($totalBytes * 0.75))],
            ],
        );

        $this->newLine();
        $this->line('Jalankan --apply --batch=25 untuk memproses satu batch dengan backup.');

        return self::SUCCESS;
    }

    protected function applyBatch(Collection $tasks, PublicImageOptimizer $optimizer): int
    {
        $batchSize = min(100, max(1, (int) $this->option('batch')));
        $batch = $tasks->take($batchSize);
        $manifestId = now()->format('Ymd-His').'-'.Str::lower(Str::random(6));
        $manifestDirectory = 'media-backups/'.$manifestId;
        $public = Storage::disk('public');
        $private = Storage::disk('local');
        $entries = [];
        $optimizedCache = [];
        $originalBytes = 0;
        $optimizedBytes = 0;
        $failures = 0;

        foreach ($batch as $task) {
            try {
                if (! $public->exists($task['path'])) {
                    throw new RuntimeException('File sumber tidak ditemukan.');
                }

                $backupPath = $manifestDirectory.'/files/'.$task['path'];

                if (! $private->exists($backupPath)) {
                    $readStream = $public->readStream($task['path']);

                    if (! is_resource($readStream)) {
                        throw new RuntimeException('File sumber tidak dapat dibaca untuk backup.');
                    }

                    try {
                        $private->writeStream($backupPath, $readStream);
                    } finally {
                        fclose($readStream);
                    }
                }

                $cacheKey = $task['profile'].'|'.$task['path'];

                if (! isset($optimizedCache[$cacheKey])) {
                    $optimizedCache[$cacheKey] = $task['profile'] === 'favicon'
                        ? $optimizer->optimizeBrandingIcons($task['path'])
                        : $optimizer->optimize($task['path'], $task['profile']);
                }

                $result = $optimizedCache[$cacheKey];
                $newPath = $task['profile'] === 'favicon'
                    ? $result['favicon_path']
                    : $result['path'];
                $currentValue = (string) DB::table($task['table'])
                    ->where('id', $task['id'])
                    ->value($task['column']);
                $newValue = $task['embedded']
                    ? str_replace($task['path'], $newPath, $currentValue)
                    : $newPath;

                DB::table($task['table'])
                    ->where('id', $task['id'])
                    ->update([$task['column'] => $newValue]);

                $generatedPaths = $task['profile'] === 'favicon'
                    ? array_values($result)
                    : array_values(array_filter([
                        $result['path'] ?? null,
                        $result['thumbnail_path'] ?? null,
                    ]));

                $entries[] = [
                    'table' => $task['table'],
                    'id' => $task['id'],
                    'column' => $task['column'],
                    'old_value' => $currentValue,
                    'new_value' => $newValue,
                    'source_path' => $task['path'],
                    'backup_path' => $backupPath,
                    'generated_paths' => $generatedPaths,
                ];

                $sourceBytes = (int) $public->size($task['path']);
                $resultBytes = collect($generatedPaths)
                    ->filter(fn (string $path): bool => $public->exists($path))
                    ->sum(fn (string $path): int => (int) $public->size($path));
                $originalBytes += $sourceBytes;
                $optimizedBytes += $resultBytes;
                $this->line('OK  '.$task['table'].'#'.$task['id'].' · '.$task['path']);
            } catch (Throwable $exception) {
                $failures++;
                $this->warn('GAGAL '.$task['table'].'#'.$task['id'].' · '.$task['path'].' · '.$exception->getMessage());
            }
        }

        $manifest = [
            'id' => $manifestId,
            'created_at' => now()->toIso8601String(),
            'entries' => $entries,
        ];
        $manifestPath = $manifestDirectory.'/manifest.json';
        $private->put(
            $manifestPath,
            json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        );

        $this->newLine();
        $this->table(
            ['Metrik', 'Nilai'],
            [
                ['Berhasil', number_format(count($entries), 0, ',', '.')],
                ['Gagal', number_format($failures, 0, ',', '.')],
                ['Sumber', $this->formatBytes($originalBytes)],
                ['Hasil + thumbnail', $this->formatBytes($optimizedBytes)],
                ['Manifest rollback', $manifestId],
                ['Sisa referensi', number_format(max(0, $tasks->count() - $batch->count()), 0, ',', '.')],
            ],
        );

        return $failures > 0 && $entries === [] ? self::FAILURE : self::SUCCESS;
    }

    protected function rollback(string $manifestReference): int
    {
        $private = Storage::disk('local');
        $public = Storage::disk('public');
        $manifestPath = Str::endsWith($manifestReference, '.json')
            ? ltrim(str_replace('\\', '/', $manifestReference), '/')
            : 'media-backups/'.trim($manifestReference, '/').'/manifest.json';

        if (! $private->exists($manifestPath)) {
            $this->error('Manifest rollback tidak ditemukan: '.$manifestPath);

            return self::FAILURE;
        }

        $manifest = json_decode((string) $private->get($manifestPath), true);
        $entries = array_reverse($manifest['entries'] ?? []);

        foreach ($entries as $entry) {
            if (Schema::hasTable($entry['table'])
                && Schema::hasColumns($entry['table'], ['id', $entry['column']])) {
                DB::table($entry['table'])
                    ->where('id', $entry['id'])
                    ->update([$entry['column'] => $entry['old_value']]);
            }

            if ($private->exists($entry['backup_path'])) {
                $readStream = $private->readStream($entry['backup_path']);

                if (is_resource($readStream)) {
                    try {
                        $public->writeStream($entry['source_path'], $readStream);
                    } finally {
                        fclose($readStream);
                    }
                }
            }

            foreach ($entry['generated_paths'] ?? [] as $generatedPath) {
                if ($generatedPath !== $entry['source_path']) {
                    $public->delete($generatedPath);
                }
            }
        }

        $this->info('Rollback selesai untuk manifest '.($manifest['id'] ?? $manifestReference).'.');

        return self::SUCCESS;
    }

    protected function normalizePath(string $value): string
    {
        $path = trim($value);

        if (Str::startsWith($path, ['http://', 'https://'])) {
            $urlPath = (string) parse_url($path, PHP_URL_PATH);
            $path = Str::after($urlPath, '/storage/');
        }

        $path = str_replace('\\', '/', $path);
        $path = preg_replace('#^/?(?:public/)?storage/#', '', $path) ?: $path;

        return ltrim($path, '/');
    }

    protected function isOptimizablePath(string $path): bool
    {
        return $path !== ''
            && preg_match('/\.(?:avif|gif|jpe?g|png|webp|svg)$/i', $path) === 1
            && Storage::disk('public')->exists($path);
    }

    protected function formatBytes(int $bytes): string
    {
        if ($bytes >= 1024 * 1024) {
            return number_format($bytes / 1024 / 1024, 2, ',', '.').' MB';
        }

        if ($bytes >= 1024) {
            return number_format($bytes / 1024, 2, ',', '.').' KB';
        }

        return number_format($bytes, 0, ',', '.').' B';
    }
}
