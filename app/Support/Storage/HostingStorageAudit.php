<?php

namespace App\Support\Storage;

use FilesystemIterator;
use Illuminate\Support\Facades\Cache;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Throwable;

final class HostingStorageAudit
{
    /** @return array<string, mixed> */
    public function inspect(bool $record = true, ?int $usedBytesOverride = null): array
    {
        $hostingRoot = trim((string) config('storage_maintenance.hosting_root'));
        $quotaBytes = (int) round((float) config('storage_maintenance.quota_gb', 10) * 1073741824);
        $publicRoot = (string) config('filesystems.disks.public.root');
        $orphanReports = app(AssessmentReportOrphanManager::class)->inspect();
        $categories = [
            'Upload publik' => $this->directorySize($publicRoot),
            'Aset build aktif' => $this->directorySize(public_path('build')),
            'Storage privat' => $this->directorySize(storage_path('app/private')),
            'PDF rapor' => $this->directorySize(storage_path('app/private/assessment-reports')),
            'File rapor yatim' => $orphanReports['bytes'],
            'Karantina file yatim' => $this->directorySize(storage_path('app/private/orphan-quarantine')),
            'File impor' => $this->directorySize(storage_path('app/private/assessment-imports')),
            'Temporary upload' => $this->directorySize(storage_path('app/private/livewire-tmp')),
            'Backup media' => $this->directorySize(storage_path('app/private/media-backups')),
            'Log Laravel' => $this->directorySize(storage_path('logs')),
            'Cache framework' => $this->directorySize(storage_path('framework')),
        ];
        $latest = $this->latest();
        $usedBytes = $usedBytesOverride
            ?? ($hostingRoot !== '' ? data_get($latest, 'used_bytes') : null)
            ?? array_sum($categories);
        $percent = $quotaBytes > 0 ? round(((int) $usedBytes / $quotaBytes) * 100, 1) : null;
        $level = match (true) {
            $percent === null => 'unknown',
            $percent >= (float) config('storage_maintenance.danger_percent', 90) => 'danger',
            $percent >= (float) config('storage_maintenance.critical_percent', 80) => 'critical',
            $percent >= (float) config('storage_maintenance.warning_percent', 70) => 'warning',
            default => 'safe',
        };
        $result = [
            'scope' => $usedBytesOverride !== null || $hostingRoot !== '' ? 'hosting' : 'application',
            'used_bytes' => (int) $usedBytes,
            'quota_bytes' => $quotaBytes,
            'percent' => $percent,
            'level' => $level,
            'categories' => $categories,
            'audited_at' => now()->toIso8601String(),
        ];

        if ($record) {
            Cache::put((string) config('storage_maintenance.audit_cache_key'), $result, now()->addDays(2));
        }

        return $result;
    }

    /** @return array<string, mixed>|null */
    public function latest(): ?array
    {
        $value = Cache::get((string) config('storage_maintenance.audit_cache_key'));

        return is_array($value) ? $value : null;
    }

    private function directorySize(string $path): int
    {
        if ($path === '' || ! is_dir($path)) {
            return 0;
        }

        $bytes = 0;

        try {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
                RecursiveIteratorIterator::LEAVES_ONLY,
            );

            /** @var SplFileInfo $file */
            foreach ($iterator as $file) {
                if ($file->isFile() && ! $file->isLink()) {
                    $bytes += $file->getSize();
                }
            }
        } catch (Throwable) {
            return $bytes;
        }

        return $bytes;
    }
}
