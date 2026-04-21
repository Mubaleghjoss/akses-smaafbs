<?php

namespace App\Models\Concerns;

use App\Support\GoogleDrive\GoogleDriveSyncState;

trait HasGoogleDriveSyncState
{
    public const GDRIVE_STATUS_INACTIVE = GoogleDriveSyncState::STATUS_INACTIVE;

    public const GDRIVE_STATUS_CONFIG_INCOMPLETE = GoogleDriveSyncState::STATUS_CONFIG_INCOMPLETE;

    public const GDRIVE_STATUS_QUEUED = GoogleDriveSyncState::STATUS_QUEUED;

    public const GDRIVE_STATUS_UPLOADING = GoogleDriveSyncState::STATUS_UPLOADING;

    public const GDRIVE_STATUS_SYNCED = GoogleDriveSyncState::STATUS_SYNCED;

    public const GDRIVE_STATUS_FAILED = GoogleDriveSyncState::STATUS_FAILED;

    public const GDRIVE_STATUS_SKIPPED = GoogleDriveSyncState::STATUS_SKIPPED;

    public const GDRIVE_SYNC_MODE_CREATED = GoogleDriveSyncState::SYNC_MODE_CREATED;

    public const GDRIVE_SYNC_MODE_REPLACED = GoogleDriveSyncState::SYNC_MODE_REPLACED;

    public const GDRIVE_SYNC_MODE_RESTORED = GoogleDriveSyncState::SYNC_MODE_RESTORED;

    public function resolvedDriveUrl(): ?string
    {
        return $this->gdrive_folder_url ?: $this->gdrive_file_url;
    }

    public function markGoogleDriveStatus(string $status, int $progress, ?string $message): void
    {
        $this->forceFill([
            'gdrive_upload_status' => $status,
            'gdrive_upload_progress' => max(0, min(100, $progress)),
            'gdrive_upload_message' => $message,
        ])->saveQuietly();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function markGoogleDriveSynced(array $attributes = []): void
    {
        $syncMode = $attributes['gdrive_last_sync_mode'] ?? null;

        $this->forceFill(array_merge([
            'gdrive_upload_status' => self::GDRIVE_STATUS_SYNCED,
            'gdrive_upload_progress' => 100,
            'gdrive_upload_message' => GoogleDriveSyncState::successMessage(is_string($syncMode) ? $syncMode : null),
            'gdrive_uploaded_at' => now(),
        ], $attributes))->saveQuietly();
    }

    public static function googleDriveStatusLabel(?string $status): string
    {
        return GoogleDriveSyncState::statusLabel($status);
    }

    public static function googleDriveStatusColor(?string $status): string
    {
        return GoogleDriveSyncState::statusColor($status);
    }

    public static function googleDriveSyncModeLabel(?string $mode): string
    {
        return GoogleDriveSyncState::syncModeLabel($mode);
    }

    public static function googleDriveSyncModeColor(?string $mode): string
    {
        return GoogleDriveSyncState::syncModeColor($mode);
    }

    public static function googleDriveSyncModeOptions(): array
    {
        return GoogleDriveSyncState::syncModeOptions();
    }
}
