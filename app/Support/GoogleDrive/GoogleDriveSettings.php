<?php

namespace App\Support\GoogleDrive;

use App\Models\Pengaturan;
use App\Support\SiteSettings\SiteSettingKeys;

class GoogleDriveSettings
{
    public function __construct(
        public readonly bool $enabled,
        public readonly bool $autoSyncKomiteDocuments,
        public readonly bool $autoSyncBerkasSiswa,
        public readonly bool $autoSyncBerkasGuru,
        public readonly bool $autoSyncPrestasi,
        public readonly bool $autoSyncIdentitasSekolah,
        public readonly ?string $rootFolderId,
        public readonly ?string $sharedDriveId,
        public readonly ?string $serviceAccountJson,
    ) {}

    public static function fromDatabase(): self
    {
        $values = Pengaturan::values([
            SiteSettingKeys::GOOGLE_DRIVE_ENABLED,
            SiteSettingKeys::GOOGLE_DRIVE_AUTO_SYNC_KOMITE_DOCUMENTS,
            SiteSettingKeys::GOOGLE_DRIVE_AUTO_SYNC_BERKAS_SISWA,
            SiteSettingKeys::GOOGLE_DRIVE_AUTO_SYNC_BERKAS_GURU,
            SiteSettingKeys::GOOGLE_DRIVE_AUTO_SYNC_PRESTASI,
            SiteSettingKeys::GOOGLE_DRIVE_AUTO_SYNC_IDENTITAS_SEKOLAH,
            SiteSettingKeys::GOOGLE_DRIVE_ROOT_FOLDER_ID,
            SiteSettingKeys::GOOGLE_DRIVE_SHARED_DRIVE_ID,
            SiteSettingKeys::GOOGLE_DRIVE_SERVICE_ACCOUNT_JSON,
        ], [
            SiteSettingKeys::GOOGLE_DRIVE_AUTO_SYNC_KOMITE_DOCUMENTS => '1',
            SiteSettingKeys::GOOGLE_DRIVE_AUTO_SYNC_BERKAS_SISWA => '1',
            SiteSettingKeys::GOOGLE_DRIVE_AUTO_SYNC_BERKAS_GURU => '1',
            SiteSettingKeys::GOOGLE_DRIVE_AUTO_SYNC_PRESTASI => '1',
            SiteSettingKeys::GOOGLE_DRIVE_AUTO_SYNC_IDENTITAS_SEKOLAH => '1',
        ]);

        return new self(
            enabled: static::toBool($values[SiteSettingKeys::GOOGLE_DRIVE_ENABLED] ?? null),
            autoSyncKomiteDocuments: static::toBool($values[SiteSettingKeys::GOOGLE_DRIVE_AUTO_SYNC_KOMITE_DOCUMENTS] ?? '1'),
            autoSyncBerkasSiswa: static::toBool($values[SiteSettingKeys::GOOGLE_DRIVE_AUTO_SYNC_BERKAS_SISWA] ?? '1'),
            autoSyncBerkasGuru: static::toBool($values[SiteSettingKeys::GOOGLE_DRIVE_AUTO_SYNC_BERKAS_GURU] ?? '1'),
            autoSyncPrestasi: static::toBool($values[SiteSettingKeys::GOOGLE_DRIVE_AUTO_SYNC_PRESTASI] ?? '1'),
            autoSyncIdentitasSekolah: static::toBool($values[SiteSettingKeys::GOOGLE_DRIVE_AUTO_SYNC_IDENTITAS_SEKOLAH] ?? '1'),
            rootFolderId: static::normalizeFolderId($values[SiteSettingKeys::GOOGLE_DRIVE_ROOT_FOLDER_ID] ?? null),
            sharedDriveId: static::normalizeSharedDriveId($values[SiteSettingKeys::GOOGLE_DRIVE_SHARED_DRIVE_ID] ?? null),
            serviceAccountJson: static::toNullableString($values[SiteSettingKeys::GOOGLE_DRIVE_SERVICE_ACCOUNT_JSON] ?? null),
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromFormData(array $data): self
    {
        return new self(
            enabled: static::toBool($data['google_drive_enabled'] ?? false),
            autoSyncKomiteDocuments: static::toBool($data['google_drive_auto_sync_komite_documents'] ?? false),
            autoSyncBerkasSiswa: static::toBool($data['google_drive_auto_sync_berkas_siswa'] ?? false),
            autoSyncBerkasGuru: static::toBool($data['google_drive_auto_sync_berkas_guru'] ?? false),
            autoSyncPrestasi: static::toBool($data['google_drive_auto_sync_prestasi'] ?? false),
            autoSyncIdentitasSekolah: static::toBool($data['google_drive_auto_sync_identitas_sekolah'] ?? false),
            rootFolderId: static::normalizeFolderId($data['google_drive_root_folder_id'] ?? null),
            sharedDriveId: static::normalizeSharedDriveId($data['google_drive_shared_drive_id'] ?? null),
            serviceAccountJson: static::toNullableString($data['google_drive_service_account_json'] ?? null),
        );
    }

    public static function normalizeFolderId(mixed $value): ?string
    {
        $normalized = static::toNullableString($value);

        if ($normalized === null) {
            return null;
        }

        if (preg_match('~drive\.google\.com/(?:drive/)?folders/([^/?#]+)~i', $normalized, $matches) === 1) {
            return trim($matches[1]);
        }

        if (preg_match('~drive\.google\.com/(?:drive/)?drives/([^/?#]+)~i', $normalized, $matches) === 1) {
            return trim($matches[1]);
        }

        return $normalized;
    }

    public static function normalizeSharedDriveId(mixed $value): ?string
    {
        $normalized = static::toNullableString($value);

        if ($normalized === null) {
            return null;
        }

        if (preg_match('~drive\.google\.com/(?:drive/)?drives/([^/?#]+)~i', $normalized, $matches) === 1) {
            return trim($matches[1]);
        }

        if (preg_match('~drive\.google\.com/(?:drive/)?folders/([^/?#]+)~i', $normalized, $matches) === 1) {
            return trim($matches[1]);
        }

        return $normalized;
    }

    public function credentials(): ?array
    {
        if ($this->serviceAccountJson === null) {
            return null;
        }

        $decoded = json_decode($this->serviceAccountJson, true);

        return is_array($decoded) ? $decoded : null;
    }

    public function serviceAccountEmail(): ?string
    {
        $email = $this->credentials()['client_email'] ?? null;

        return is_string($email) && trim($email) !== '' ? trim($email) : null;
    }

    public function hasRootFolder(): bool
    {
        return $this->rootFolderId !== null;
    }

    public function isComplete(): bool
    {
        $credentials = $this->credentials();

        return $this->hasRootFolder()
            && is_array($credentials)
            && filled($credentials['client_email'] ?? null)
            && filled($credentials['private_key'] ?? null);
    }

    public function readinessLabel(): string
    {
        return match (true) {
            ! $this->enabled => 'Nonaktif',
            ! $this->isComplete() => 'Belum lengkap',
            default => 'Siap dipakai',
        };
    }

    private static function toBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return filter_var($value, FILTER_VALIDATE_BOOL);
    }

    private static function toNullableString(mixed $value): ?string
    {
        $normalized = trim((string) $value);

        return $normalized !== '' ? $normalized : null;
    }
}
