<?php

namespace App\Support\Storage;

use App\Support\Assessment\Reporting\AssessmentReportCacheCleaner;
use Illuminate\Support\Facades\Storage;

final class AppStorageMaintenance
{
    /** @return array<string, array{files:int,bytes:int}> */
    public function run(bool $apply, bool $includeMediaBackups = false): array
    {
        $result = [];
        $classCache = app(AssessmentReportCacheCleaner::class)->clean($apply);
        $result['PDF kelas kedaluwarsa'] = ['files' => $classCache['files'], 'bytes' => $classCache['bytes']];
        $result['File sementara'] = $this->cleanOldLocalFiles(
            ['livewire-tmp', 'assessment-imports', 'assessment-reports/.tmp'],
            now()->subHours((int) config('storage_maintenance.temporary_hours', 24))->getTimestamp(),
            $apply,
        );
        $result['Log lama'] = $this->cleanOldLogFiles(
            now()->subDays((int) config('storage_maintenance.log_days', 14))->getTimestamp(),
            $apply,
        );
        $orphans = app(AssessmentReportOrphanManager::class);
        $result['File rapor yatim ke karantina'] = $orphans->quarantine($apply);
        $result['Karantina yatim lewat 7 hari'] = $orphans->purgeExpired(
            $apply,
            (int) config('storage_maintenance.orphan_quarantine_days', 7),
        );
        $result['Backup media terverifikasi'] = $this->cleanOldMediaBackups(
            now()->subDays((int) config('storage_maintenance.media_backup_days', 7))->getTimestamp(),
            $apply && $includeMediaBackups,
        );

        return $result;
    }

    private function cleanOldLocalFiles(array $directories, int $cutoff, bool $apply): array
    {
        $disk = Storage::disk('local');
        $files = 0;
        $bytes = 0;

        foreach ($directories as $directory) {
            foreach ($disk->allFiles($directory) as $path) {
                if ($disk->lastModified($path) > $cutoff) {
                    continue;
                }

                $files++;
                $bytes += (int) $disk->size($path);
                if ($apply) {
                    $disk->delete($path);
                }
            }
        }

        return compact('files', 'bytes');
    }

    private function cleanOldLogFiles(int $cutoff, bool $apply): array
    {
        $files = 0;
        $bytes = 0;

        foreach (glob(storage_path('logs/*.log')) ?: [] as $path) {
            if (! is_file($path) || filemtime($path) > $cutoff || basename($path) === 'laravel.log') {
                continue;
            }

            $files++;
            $bytes += (int) filesize($path);
            if ($apply) {
                @unlink($path);
            }
        }

        return compact('files', 'bytes');
    }

    private function cleanOldMediaBackups(int $cutoff, bool $apply): array
    {
        $disk = Storage::disk('local');
        $files = 0;
        $bytes = 0;

        foreach ($disk->directories('media-backups') as $directory) {
            $manifest = $directory.'/manifest.json';
            if (! $disk->exists($manifest) || $disk->lastModified($manifest) > $cutoff) {
                continue;
            }

            $directoryFiles = $disk->allFiles($directory);
            $files += count($directoryFiles);
            $bytes += array_sum(array_map(fn (string $path): int => (int) $disk->size($path), $directoryFiles));
            if ($apply) {
                $disk->deleteDirectory($directory);
            }
        }

        return compact('files', 'bytes');
    }
}
