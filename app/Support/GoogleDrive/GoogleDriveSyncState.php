<?php

namespace App\Support\GoogleDrive;

class GoogleDriveSyncState
{
    public const STATUS_INACTIVE = 'inactive';

    public const STATUS_CONFIG_INCOMPLETE = 'config_incomplete';

    public const STATUS_QUEUED = 'queued';

    public const STATUS_UPLOADING = 'uploading';

    public const STATUS_SYNCED = 'synced';

    public const STATUS_FAILED = 'failed';

    public const STATUS_SKIPPED = 'skipped';

    public const SYNC_MODE_CREATED = 'created';

    public const SYNC_MODE_REPLACED = 'replaced';

    public const SYNC_MODE_RESTORED = 'restored';

    public static function statusLabel(?string $status): string
    {
        return match ((string) $status) {
            self::STATUS_INACTIVE => 'Nonaktif',
            self::STATUS_CONFIG_INCOMPLETE => 'Belum Lengkap',
            self::STATUS_QUEUED => 'Dalam Antrean',
            self::STATUS_UPLOADING => 'Mengunggah',
            self::STATUS_SYNCED => 'Tersinkron',
            self::STATUS_FAILED => 'Gagal',
            self::STATUS_SKIPPED => 'Lewati',
            default => '-',
        };
    }

    public static function statusColor(?string $status): string
    {
        return match ((string) $status) {
            self::STATUS_SYNCED => 'success',
            self::STATUS_UPLOADING => 'info',
            self::STATUS_QUEUED => 'warning',
            self::STATUS_FAILED => 'danger',
            self::STATUS_CONFIG_INCOMPLETE => 'gray',
            self::STATUS_INACTIVE => 'gray',
            self::STATUS_SKIPPED => 'gray',
            default => 'gray',
        };
    }

    public static function syncModeLabel(?string $mode): string
    {
        return match ((string) $mode) {
            self::SYNC_MODE_CREATED => 'Baru',
            self::SYNC_MODE_REPLACED => 'Diganti',
            self::SYNC_MODE_RESTORED => 'Dipulihkan',
            default => '-',
        };
    }

    public static function syncModeColor(?string $mode): string
    {
        return match ((string) $mode) {
            self::SYNC_MODE_CREATED => 'success',
            self::SYNC_MODE_REPLACED => 'info',
            self::SYNC_MODE_RESTORED => 'warning',
            default => 'gray',
        };
    }

    public static function syncModeOptions(): array
    {
        return [
            self::SYNC_MODE_CREATED => self::syncModeLabel(self::SYNC_MODE_CREATED),
            self::SYNC_MODE_REPLACED => self::syncModeLabel(self::SYNC_MODE_REPLACED),
            self::SYNC_MODE_RESTORED => self::syncModeLabel(self::SYNC_MODE_RESTORED),
        ];
    }

    public static function successMessage(?string $syncMode): string
    {
        return match ((string) $syncMode) {
            self::SYNC_MODE_RESTORED => 'Mirror Google Drive berhasil dipulihkan dari file lokal.',
            self::SYNC_MODE_REPLACED => 'Mirror Google Drive berhasil diperbarui dari file lokal.',
            default => 'Semua file berhasil tersimpan di Google Drive.',
        };
    }
}
