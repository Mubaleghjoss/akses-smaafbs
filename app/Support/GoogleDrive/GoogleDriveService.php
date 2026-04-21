<?php

namespace App\Support\GoogleDrive;

use App\Jobs\SyncBerkasGuruToGoogleDrive;
use App\Jobs\SyncBerkasSiswaToGoogleDrive;
use App\Jobs\SyncKomiteDocumentToGoogleDrive;
use App\Jobs\SyncPrestasiToGoogleDrive;
use App\Jobs\SyncProfilSekolahToGoogleDrive;
use App\Models\BerkasGuru;
use App\Models\BerkasSiswa;
use App\Models\KomiteDocument;
use App\Models\Prestasi;
use App\Models\ProfilSekolah;
use App\Support\Documents\ManagedDocumentNaming;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class GoogleDriveService
{
    private const TOKEN_ENDPOINT = 'https://oauth2.googleapis.com/token';

    private const FILES_ENDPOINT = 'https://www.googleapis.com/drive/v3/files';

    private const UPLOAD_ENDPOINT = 'https://www.googleapis.com/upload/drive/v3/files';

    public function __construct(
        private readonly HttpFactory $http,
    ) {}

    public function settings(?array $data = null): GoogleDriveSettings
    {
        return $data === null
            ? GoogleDriveSettings::fromDatabase()
            : GoogleDriveSettings::fromFormData($data);
    }

    /**
     * @return array{ok: bool, message: string, email: string|null, folder_name: string|null}
     */
    public function testConnection(?GoogleDriveSettings $settings = null): array
    {
        $settings ??= $this->settings();

        if (! $settings->enabled) {
            return [
                'ok' => false,
                'message' => 'Google Drive masih nonaktif.',
                'email' => $settings->serviceAccountEmail(),
                'folder_name' => null,
            ];
        }

        if (! $settings->isComplete()) {
            return [
                'ok' => false,
                'message' => 'Konfigurasi Google Drive belum lengkap.',
                'email' => $settings->serviceAccountEmail(),
                'folder_name' => null,
            ];
        }

        try {
            $token = $this->fetchAccessToken($settings);
            $folder = $this->fetchFileMetadata($token, (string) $settings->rootFolderId);
        } catch (Throwable $exception) {
            throw new RuntimeException($this->formatConnectionExceptionMessage($exception), previous: $exception);
        }

        return [
            'ok' => true,
            'message' => 'Koneksi Google Drive berhasil.',
            'email' => $settings->serviceAccountEmail(),
            'folder_name' => (string) ($folder['name'] ?? null),
        ];
    }

    public function queueKomiteDocumentSync(KomiteDocument $record): string
    {
        if (! $record->hasUploadableFiles()) {
            $record->markGoogleDriveStatus(KomiteDocument::GDRIVE_STATUS_SKIPPED, 0, 'Tidak ada file lokal untuk diunggah.');

            return KomiteDocument::GDRIVE_STATUS_SKIPPED;
        }

        $settings = $this->settings();

        if (! $settings->enabled || ! $settings->autoSyncKomiteDocuments) {
            $record->markGoogleDriveStatus(KomiteDocument::GDRIVE_STATUS_INACTIVE, 0, 'Sinkronisasi Google Drive sedang nonaktif.');

            return KomiteDocument::GDRIVE_STATUS_INACTIVE;
        }

        if (! $settings->isComplete()) {
            $record->markGoogleDriveStatus(KomiteDocument::GDRIVE_STATUS_CONFIG_INCOMPLETE, 0, 'Konfigurasi Google Drive belum lengkap.');

            return KomiteDocument::GDRIVE_STATUS_CONFIG_INCOMPLETE;
        }

        $record->markGoogleDriveStatus(KomiteDocument::GDRIVE_STATUS_QUEUED, 0, 'Menunggu antrean upload Google Drive.');
        SyncKomiteDocumentToGoogleDrive::dispatch($record->getKey());

        return KomiteDocument::GDRIVE_STATUS_QUEUED;
    }

    public function queueBerkasSiswaSync(BerkasSiswa $record): string
    {
        return $this->queueSingleFileSync(
            $record,
            $this->settings()->autoSyncBerkasSiswa,
            'Sinkronisasi otomatis Google Drive untuk berkas siswa sedang nonaktif.',
            fn (BerkasSiswa $queuedRecord): mixed => SyncBerkasSiswaToGoogleDrive::dispatch($queuedRecord->getKey()),
        );
    }

    public function queueBerkasGuruSync(BerkasGuru $record): string
    {
        return $this->queueSingleFileSync(
            $record,
            $this->settings()->autoSyncBerkasGuru,
            'Sinkronisasi otomatis Google Drive untuk berkas guru sedang nonaktif.',
            fn (BerkasGuru $queuedRecord): mixed => SyncBerkasGuruToGoogleDrive::dispatch($queuedRecord->getKey()),
        );
    }

    public function queuePrestasiSync(Prestasi $record): string
    {
        if (! $record->hasUploadableFiles()) {
            $record->markGoogleDriveStatus(Prestasi::GDRIVE_STATUS_SKIPPED, 0, 'Tidak ada file lokal untuk diunggah.');

            return Prestasi::GDRIVE_STATUS_SKIPPED;
        }

        $settings = $this->settings();

        if (! $settings->enabled || ! $settings->autoSyncPrestasi) {
            $record->markGoogleDriveStatus(Prestasi::GDRIVE_STATUS_INACTIVE, 0, 'Sinkronisasi otomatis Google Drive untuk prestasi sedang nonaktif.');

            return Prestasi::GDRIVE_STATUS_INACTIVE;
        }

        if (! $settings->isComplete()) {
            $record->markGoogleDriveStatus(Prestasi::GDRIVE_STATUS_CONFIG_INCOMPLETE, 0, 'Konfigurasi Google Drive belum lengkap.');

            return Prestasi::GDRIVE_STATUS_CONFIG_INCOMPLETE;
        }

        $record->markGoogleDriveStatus(Prestasi::GDRIVE_STATUS_QUEUED, 0, 'Menunggu antrean upload Google Drive.');
        SyncPrestasiToGoogleDrive::dispatch($record->getKey());

        return Prestasi::GDRIVE_STATUS_QUEUED;
    }

    public function queueProfilSekolahSync(ProfilSekolah $record): string
    {
        return $this->queueSingleFileSync(
            $record,
            $this->settings()->autoSyncIdentitasSekolah,
            'Sinkronisasi otomatis Google Drive untuk identitas sekolah sedang nonaktif.',
            fn (ProfilSekolah $queuedRecord): mixed => SyncProfilSekolahToGoogleDrive::dispatch($queuedRecord->getKey()),
        );
    }

    public function uploadKomiteDocumentNow(KomiteDocument $record): string
    {
        return $this->syncKomiteDocument($record, true, true);
    }

    public function uploadBerkasSiswaNow(BerkasSiswa $record): string
    {
        return $this->syncBerkasSiswa($record, true, true);
    }

    public function uploadBerkasGuruNow(BerkasGuru $record): string
    {
        return $this->syncBerkasGuru($record, true, true);
    }

    public function uploadPrestasiNow(Prestasi $record): string
    {
        return $this->syncPrestasi($record, true, true);
    }

    public function uploadProfilSekolahNow(ProfilSekolah $record): string
    {
        return $this->syncProfilSekolah($record, true, true);
    }
    public function syncKomiteDocument(KomiteDocument $record, bool $force = false, bool $repairMode = false): string
    {
        $settings = $this->settings();
        $uploads = $record->googleDriveUploadQueue();

        if (! $settings->enabled) {
            $record->markGoogleDriveStatus(KomiteDocument::GDRIVE_STATUS_INACTIVE, 0, 'Sinkronisasi Google Drive sedang nonaktif.');

            return KomiteDocument::GDRIVE_STATUS_INACTIVE;
        }

        if (! $force && ! $settings->autoSyncKomiteDocuments) {
            $record->markGoogleDriveStatus(KomiteDocument::GDRIVE_STATUS_INACTIVE, 0, 'Sinkronisasi otomatis Google Drive sedang nonaktif.');

            return KomiteDocument::GDRIVE_STATUS_INACTIVE;
        }

        if (! $settings->isComplete()) {
            $record->markGoogleDriveStatus(KomiteDocument::GDRIVE_STATUS_CONFIG_INCOMPLETE, 0, 'Konfigurasi Google Drive belum lengkap.');

            return KomiteDocument::GDRIVE_STATUS_CONFIG_INCOMPLETE;
        }

        if ($uploads === []) {
            $record->markGoogleDriveStatus(KomiteDocument::GDRIVE_STATUS_SKIPPED, 0, 'Tidak ada file lokal untuk diunggah.');

            return KomiteDocument::GDRIVE_STATUS_SKIPPED;
        }

        $record->markGoogleDriveStatus(KomiteDocument::GDRIVE_STATUS_UPLOADING, 0, 'Menyiapkan koneksi Google Drive...');

        try {
            $token = $this->fetchAccessToken($settings);
            $documentFolder = $this->ensureDocumentFolder($token, $record, $settings, $repairMode);
            $documentationPayload = [];
            $existingDocumentationPayload = collect($record->gdrive_documentation_payload ?? [])
                ->filter(fn (mixed $item): bool => is_array($item) && filled($item['name'] ?? null))
                ->mapWithKeys(fn (array $item): array => [(string) $item['name'] => $item]);
            $total = count($uploads);
            $mainFileId = null;
            $syncModes = [];

            foreach ($uploads as $index => $upload) {
                $record->markGoogleDriveStatus(
                    KomiteDocument::GDRIVE_STATUS_UPLOADING,
                    (int) round(($index / $total) * 100),
                    'Mengunggah '.$upload['label'].' ke Google Drive...'
                );

                $uploaded = $this->upsertFile(
                    token: $token,
                    settings: $settings,
                    parentId: $documentFolder['id'],
                    localPath: $upload['absolute_path'],
                    uploadName: $upload['name'],
                    mimeType: $upload['mime_type'],
                    existingFileId: match ($upload['kind']) {
                        'file' => $record->gdrive_file_id,
                        'documentation' => data_get($existingDocumentationPayload->get($upload['name']), 'id'),
                        default => null,
                    },
                );

                if ($upload['kind'] === 'file') {
                    $mainFileId = (string) ($uploaded['id'] ?? '');
                }

                $syncModes[] = (string) ($uploaded['sync_mode'] ?? KomiteDocument::GDRIVE_SYNC_MODE_CREATED);

                if ($upload['kind'] === 'documentation') {
                    $documentationPayload[] = [
                        'name' => $upload['name'],
                        'id' => $uploaded['id'] ?? null,
                        'url' => $this->fileUrl((string) ($uploaded['id'] ?? '')),
                    ];
                }

                $record->markGoogleDriveStatus(
                    KomiteDocument::GDRIVE_STATUS_UPLOADING,
                    (int) round((($index + 1) / $total) * 100),
                    'Upload '.($index + 1).' dari '.$total.' file selesai.'
                );
            }

            $overallSyncMode = $this->determineOverallSyncMode($syncModes);

            $record->markGoogleDriveSynced([
                'gdrive_folder_id' => $documentFolder['id'] ?? null,
                'gdrive_folder_url' => $documentFolder['webViewLink'] ?? $this->folderUrl((string) ($documentFolder['id'] ?? '')),
                'gdrive_file_id' => $mainFileId,
                'gdrive_file_url' => $this->fileUrl((string) $mainFileId),
                'gdrive_last_sync_mode' => $overallSyncMode,
                'gdrive_upload_message' => $this->syncModeSuccessMessage($overallSyncMode),
                'gdrive_documentation_payload' => $documentationPayload !== [] ? $documentationPayload : null,
            ]);

            return KomiteDocument::GDRIVE_STATUS_SYNCED;
        } catch (Throwable $exception) {
            $record->markGoogleDriveStatus(
                KomiteDocument::GDRIVE_STATUS_FAILED,
                (int) ($record->gdrive_upload_progress ?? 0),
                $this->formatExceptionMessage($exception),
            );

            return KomiteDocument::GDRIVE_STATUS_FAILED;
        }
    }


    public function syncPrestasi(Prestasi $record, bool $force = false, bool $repairMode = false): string
    {
        $settings = $this->settings();
        $uploads = $record->googleDriveUploadQueue();

        if (! $settings->enabled) {
            $record->markGoogleDriveStatus(Prestasi::GDRIVE_STATUS_INACTIVE, 0, 'Sinkronisasi Google Drive sedang nonaktif.');

            return Prestasi::GDRIVE_STATUS_INACTIVE;
        }

        if (! $force && ! $settings->autoSyncPrestasi) {
            $record->markGoogleDriveStatus(Prestasi::GDRIVE_STATUS_INACTIVE, 0, 'Sinkronisasi otomatis Google Drive untuk prestasi sedang nonaktif.');

            return Prestasi::GDRIVE_STATUS_INACTIVE;
        }

        if (! $settings->isComplete()) {
            $record->markGoogleDriveStatus(Prestasi::GDRIVE_STATUS_CONFIG_INCOMPLETE, 0, 'Konfigurasi Google Drive belum lengkap.');

            return Prestasi::GDRIVE_STATUS_CONFIG_INCOMPLETE;
        }

        if ($uploads === []) {
            $record->markGoogleDriveStatus(Prestasi::GDRIVE_STATUS_SKIPPED, 0, 'Tidak ada file lokal untuk diunggah.');

            return Prestasi::GDRIVE_STATUS_SKIPPED;
        }

        $record->markGoogleDriveStatus(Prestasi::GDRIVE_STATUS_UPLOADING, 0, 'Menyiapkan koneksi Google Drive...');

        try {
            $token = $this->fetchAccessToken($settings);
            $prestasiFolder = $this->ensurePrestasiFolder($token, $record, $settings, $repairMode);
            $assetsPayload = [];
            $existingPayload = collect($record->gdrive_assets_payload ?? [])
                ->filter(fn (mixed $item): bool => is_array($item) && filled($item['name'] ?? null))
                ->mapWithKeys(fn (array $item): array => [(string) $item['name'] => $item]);
            $total = count($uploads);
            $mainFileId = null;
            $syncModes = [];

            foreach ($uploads as $index => $upload) {
                $record->markGoogleDriveStatus(
                    Prestasi::GDRIVE_STATUS_UPLOADING,
                    (int) round(($index / $total) * 100),
                    'Mengunggah '.$upload['label'].' ke Google Drive...'
                );

                $uploaded = $this->upsertFile(
                    token: $token,
                    settings: $settings,
                    parentId: $prestasiFolder['id'],
                    localPath: $upload['absolute_path'],
                    uploadName: $upload['name'],
                    mimeType: $upload['mime_type'],
                    existingFileId: data_get($existingPayload->get($upload['name']), 'id'),
                );

                $mainFileId ??= (string) ($uploaded['id'] ?? '');
                $syncModes[] = (string) ($uploaded['sync_mode'] ?? Prestasi::GDRIVE_SYNC_MODE_CREATED);
                $assetsPayload[] = [
                    'kind' => $upload['kind'],
                    'name' => $upload['name'],
                    'id' => $uploaded['id'] ?? null,
                    'url' => $this->fileUrl((string) ($uploaded['id'] ?? '')),
                ];

                $record->markGoogleDriveStatus(
                    Prestasi::GDRIVE_STATUS_UPLOADING,
                    (int) round((($index + 1) / $total) * 100),
                    'Upload '.($index + 1).' dari '.$total.' file selesai.'
                );
            }

            $overallSyncMode = $this->determineOverallSyncMode($syncModes);

            $record->markGoogleDriveSynced([
                'gdrive_folder_id' => $prestasiFolder['id'] ?? null,
                'gdrive_folder_url' => $prestasiFolder['webViewLink'] ?? $this->folderUrl((string) ($prestasiFolder['id'] ?? '')),
                'gdrive_file_id' => $mainFileId,
                'gdrive_file_url' => $this->fileUrl((string) $mainFileId),
                'gdrive_last_sync_mode' => $overallSyncMode,
                'gdrive_assets_payload' => $assetsPayload !== [] ? $assetsPayload : null,
            ]);

            return Prestasi::GDRIVE_STATUS_SYNCED;
        } catch (Throwable $exception) {
            $record->markGoogleDriveStatus(
                Prestasi::GDRIVE_STATUS_FAILED,
                (int) ($record->gdrive_upload_progress ?? 0),
                $this->formatExceptionMessage($exception),
            );

            return Prestasi::GDRIVE_STATUS_FAILED;
        }
    }    public function syncBerkasSiswa(BerkasSiswa $record, bool $force = false, bool $repairMode = false): string
    {
        return $this->syncSingleFileRecord(
            record: $record,
            force: $force,
            autoSyncEnabled: $this->settings()->autoSyncBerkasSiswa,
            inactiveAutoSyncMessage: 'Sinkronisasi otomatis Google Drive untuk berkas siswa sedang nonaktif.',
            ensureFolder: fn (string $token, GoogleDriveSettings $settings): array => $this->ensureStudentFileFolder($token, $record, $settings, $repairMode),
        );
    }

    public function syncBerkasGuru(BerkasGuru $record, bool $force = false, bool $repairMode = false): string
    {
        return $this->syncSingleFileRecord(
            record: $record,
            force: $force,
            autoSyncEnabled: $this->settings()->autoSyncBerkasGuru,
            inactiveAutoSyncMessage: 'Sinkronisasi otomatis Google Drive untuk berkas guru sedang nonaktif.',
            ensureFolder: fn (string $token, GoogleDriveSettings $settings): array => $this->ensureTeacherFileFolder($token, $record, $settings, $repairMode),
        );
    }

    public function syncProfilSekolah(ProfilSekolah $record, bool $force = false, bool $repairMode = false): string
    {
        return $this->syncSingleFileRecord(
            record: $record,
            force: $force,
            autoSyncEnabled: $this->settings()->autoSyncIdentitasSekolah,
            inactiveAutoSyncMessage: 'Sinkronisasi otomatis Google Drive untuk identitas sekolah sedang nonaktif.',
            ensureFolder: fn (string $token, GoogleDriveSettings $settings): array => $this->ensureSchoolIdentityFolder($token, $record, $settings, $repairMode),
        );
    }

    private function queueSingleFileSync(BerkasSiswa|BerkasGuru|ProfilSekolah $record, bool $autoSyncEnabled, string $inactiveMessage, callable $dispatchJob): string
    {
        if (! $record->hasUploadableFiles()) {
            $record->markGoogleDriveStatus($record::GDRIVE_STATUS_SKIPPED, 0, 'Tidak ada file lokal untuk diunggah.');

            return $record::GDRIVE_STATUS_SKIPPED;
        }

        $settings = $this->settings();

        if (! $settings->enabled) {
            $record->markGoogleDriveStatus($record::GDRIVE_STATUS_INACTIVE, 0, 'Sinkronisasi Google Drive sedang nonaktif.');

            return $record::GDRIVE_STATUS_INACTIVE;
        }

        if (! $autoSyncEnabled) {
            $record->markGoogleDriveStatus($record::GDRIVE_STATUS_INACTIVE, 0, $inactiveMessage);

            return $record::GDRIVE_STATUS_INACTIVE;
        }

        if (! $settings->isComplete()) {
            $record->markGoogleDriveStatus($record::GDRIVE_STATUS_CONFIG_INCOMPLETE, 0, 'Konfigurasi Google Drive belum lengkap.');

            return $record::GDRIVE_STATUS_CONFIG_INCOMPLETE;
        }

        $record->markGoogleDriveStatus($record::GDRIVE_STATUS_QUEUED, 0, 'Menunggu antrean upload Google Drive.');
        $dispatchJob($record);

        return $record::GDRIVE_STATUS_QUEUED;
    }

    private function syncSingleFileRecord(
        BerkasSiswa|BerkasGuru|ProfilSekolah $record,
        bool $force,
        bool $autoSyncEnabled,
        string $inactiveAutoSyncMessage,
        callable $ensureFolder,
    ): string
    {
        $settings = $this->settings();
        $upload = $record->googleDriveUploadFile();

        if (! $settings->enabled) {
            $record->markGoogleDriveStatus($record::GDRIVE_STATUS_INACTIVE, 0, 'Sinkronisasi Google Drive sedang nonaktif.');

            return $record::GDRIVE_STATUS_INACTIVE;
        }

        if (! $force && ! $autoSyncEnabled) {
            $record->markGoogleDriveStatus($record::GDRIVE_STATUS_INACTIVE, 0, $inactiveAutoSyncMessage);

            return $record::GDRIVE_STATUS_INACTIVE;
        }

        if (! $settings->isComplete()) {
            $record->markGoogleDriveStatus($record::GDRIVE_STATUS_CONFIG_INCOMPLETE, 0, 'Konfigurasi Google Drive belum lengkap.');

            return $record::GDRIVE_STATUS_CONFIG_INCOMPLETE;
        }

        if (! is_array($upload)) {
            $record->markGoogleDriveStatus($record::GDRIVE_STATUS_SKIPPED, 0, 'Tidak ada file lokal untuk diunggah.');

            return $record::GDRIVE_STATUS_SKIPPED;
        }

        $record->markGoogleDriveStatus($record::GDRIVE_STATUS_UPLOADING, 0, 'Menyiapkan koneksi Google Drive...');

        try {
            $token = $this->fetchAccessToken($settings);
            $folder = $ensureFolder($token, $settings);

            $record->markGoogleDriveStatus($record::GDRIVE_STATUS_UPLOADING, 35, 'Mengunggah file ke Google Drive...');

            $uploaded = $this->upsertFile(
                token: $token,
                settings: $settings,
                parentId: (string) ($folder['id'] ?? ''),
                localPath: $upload['absolute_path'],
                uploadName: $upload['name'],
                mimeType: $upload['mime_type'],
                existingFileId: $record->gdrive_file_id,
            );

            $syncMode = (string) ($uploaded['sync_mode'] ?? $record::GDRIVE_SYNC_MODE_CREATED);

            $record->markGoogleDriveSynced([
                'gdrive_folder_id' => $folder['id'] ?? null,
                'gdrive_folder_url' => $folder['webViewLink'] ?? $this->folderUrl((string) ($folder['id'] ?? '')),
                'gdrive_file_id' => $uploaded['id'] ?? null,
                'gdrive_file_url' => $this->fileUrl((string) ($uploaded['id'] ?? '')),
                'gdrive_last_sync_mode' => $syncMode,
            ]);

            return $record::GDRIVE_STATUS_SYNCED;
        } catch (Throwable $exception) {
            $record->markGoogleDriveStatus(
                $record::GDRIVE_STATUS_FAILED,
                (int) ($record->gdrive_upload_progress ?? 0),
                $this->formatExceptionMessage($exception),
            );

            return $record::GDRIVE_STATUS_FAILED;
        }
    }
    /**
     * @return array{id: string, webViewLink: string}
     */
    private function ensureDocumentFolder(string $token, KomiteDocument $record, GoogleDriveSettings $settings, bool $repairMode = false): array
    {
        if (! filled($settings->rootFolderId)) {
            throw new RuntimeException('Folder ID Tujuan belum diisi.');
        }

        $yearFolder = $this->ensureFolder($token, 'Arsip '.$record->arsip_tahun, (string) $settings->rootFolderId, $settings);
        $typeFolder = $this->ensureFolder($token, KomiteDocument::typeLabel($record->jenis_dokumen), $yearFolder['id'], $settings);

        return $this->ensureFolder(
            $token,
            'Dokumen-'.$record->getKey(),
            $typeFolder['id'],
            $settings,
            $repairMode ? null : $record->gdrive_folder_id,
        );
    }

    /**
     * @return array{id: string, webViewLink: string}
     */
    private function ensureStudentFileFolder(string $token, BerkasSiswa $record, GoogleDriveSettings $settings, bool $repairMode = false): array
    {
        if (! filled($settings->rootFolderId)) {
            throw new RuntimeException('Folder ID Tujuan belum diisi.');
        }

        $record->loadMissing(['siswa:id,nama,rombel_saat_ini', 'jenisBerkas:id,nama_berkas']);

        $moduleFolder = $this->ensureFolder($token, 'Berkas Siswa', (string) $settings->rootFolderId, $settings);

        return $this->ensureFolder(
            $token,
            ManagedDocumentNaming::ownerFolderName('Siswa', $record->siswa_id, $record->siswa?->nama),
            $moduleFolder['id'],
            $settings,
        );
    }

    /**
     * @return array{id: string, webViewLink: string}
     */
    private function ensureTeacherFileFolder(string $token, BerkasGuru $record, GoogleDriveSettings $settings, bool $repairMode = false): array
    {
        if (! filled($settings->rootFolderId)) {
            throw new RuntimeException('Folder ID Tujuan belum diisi.');
        }

        $record->loadMissing(['guru:id,nama', 'jenisBerkas:id,nama_berkas,google_drive_folder_name']);

        $moduleFolder = $this->ensureFolder($token, 'Berkas Guru', (string) $settings->rootFolderId, $settings);

        return $this->ensureFolder(
            $token,
            ManagedDocumentNaming::ownerFolderName('Guru', $record->guru_id, $record->guru?->nama),
            $moduleFolder['id'],
            $settings,
        );
    }

    /**
     */
    private function ensureSchoolIdentityFolder(string $token, ProfilSekolah $record, GoogleDriveSettings $settings, bool $repairMode = false): array
    {
        if (! filled($settings->rootFolderId)) {
            throw new RuntimeException('Folder ID Tujuan belum diisi.');
        }

        $moduleFolder = $this->ensureFolder($token, 'Identitas Sekolah', (string) $settings->rootFolderId, $settings);

        return $this->ensureFolder(
            $token,
            $this->buildRecordFolderName('Identitas', $record->getKey(), $record->nama_sekolah ?: $record->title),
            $moduleFolder['id'],
            $settings,
            $repairMode ? null : $record->gdrive_folder_id,
        );
    }

    /**
     */
    private function ensurePrestasiFolder(string $token, Prestasi $record, GoogleDriveSettings $settings, bool $repairMode = false): array
    {
        if (! filled($settings->rootFolderId)) {
            throw new RuntimeException('Folder ID Tujuan belum diisi.');
        }

        $record->loadMissing(['siswa:id,nama,rombel_saat_ini']);

        $moduleFolder = $this->ensureFolder($token, 'Prestasi Siswa', (string) $settings->rootFolderId, $settings);
        $studentFolder = $this->ensureFolder(
            $token,
            $this->buildScopedFolderName('Siswa', $record->siswa_id, $record->siswa?->nama),
            $moduleFolder['id'],
            $settings,
        );

        return $this->ensureFolder(
            $token,
            $this->buildRecordFolderName('Prestasi', $record->getKey(), $record->nama_lomba),
            $studentFolder['id'],
            $settings,
            $repairMode ? null : $record->gdrive_folder_id,
        );
    }    private function buildScopedFolderName(string $prefix, mixed $id, ?string $label): string
    {
        $normalizedId = filled($id) ? trim((string) $id) : 'tanpa-id';
        $slug = (string) Str::of(Str::ascii((string) $label))->slug('-')->limit(60, '');

        return $slug !== ''
            ? $prefix.'-'.$normalizedId.'-'.$slug
            : $prefix.'-'.$normalizedId;
    }

    private function buildRecordFolderName(string $prefix, mixed $recordId, ?string $label): string
    {
        $normalizedId = filled($recordId) ? trim((string) $recordId) : 'baru';
        $slug = (string) Str::of(Str::ascii((string) $label))->slug('-')->limit(60, '');

        return $slug !== ''
            ? $prefix.'-'.$normalizedId.'-'.$slug
            : $prefix.'-'.$normalizedId;
    }
    /**
     * @return array{id: string, webViewLink: string}
     */
    private function ensureFolder(string $token, string $name, string $parentId, GoogleDriveSettings $settings, ?string $existingFolderId = null): array
    {
        if (! filled($parentId)) {
            throw new RuntimeException('Folder parent Google Drive tidak ditemukan. Cek Folder ID Tujuan dan Shared Drive ID.');
        }

        if (filled($existingFolderId)) {
            try {
                $existing = $this->fetchFileMetadata($token, (string) $existingFolderId);

                return [
                    'id' => (string) ($existing['id'] ?? $existingFolderId),
                    'webViewLink' => (string) ($existing['webViewLink'] ?? $this->folderUrl((string) $existingFolderId)),
                ];
            } catch (Throwable) {
                // lanjut buat/cari ulang
            }
        }

        $found = $this->searchSingleFileByName($token, $parentId, $name, $settings, true);

        if ($found !== null) {
            return [
                'id' => (string) ($found['id'] ?? ''),
                'webViewLink' => (string) ($found['webViewLink'] ?? $this->folderUrl((string) ($found['id'] ?? ''))),
            ];
        }

        $created = $this->createMultipartUpload(
            token: $token,
            metadata: ['name' => $name, 'mimeType' => 'application/vnd.google-apps.folder', 'parents' => [$parentId]],
            body: '',
            mimeType: 'application/octet-stream',
        );

        if (! filled($created['id'] ?? null)) {
            throw new RuntimeException('Folder Google Drive gagal dibuat. Cek akses tulis ke folder tujuan dan kecocokan Shared Drive ID.');
        }

        return [
            'id' => (string) ($created['id'] ?? ''),
            'webViewLink' => (string) ($created['webViewLink'] ?? $this->folderUrl((string) ($created['id'] ?? ''))),
        ];
    }

    private function upsertFile(
        string $token,
        GoogleDriveSettings $settings,
        string $parentId,
        string $localPath,
        string $uploadName,
        string $mimeType,
        ?string $existingFileId = null,
    ): array
    {
        if (! filled($parentId)) {
            throw new RuntimeException('Folder tujuan upload Google Drive tidak ditemukan. Cek Folder ID Tujuan dan Shared Drive ID.');
        }

        $contents = @file_get_contents($localPath);

        if ($contents === false) {
            throw new RuntimeException('File lokal tidak ditemukan saat akan diunggah.');
        }

        $resolved = $this->resolveExistingRemoteFileId(
            token: $token,
            settings: $settings,
            parentId: $parentId,
            uploadName: $uploadName,
            existingFileId: $existingFileId,
        );

        if (filled($resolved['file_id'] ?? null)) {
            $uploaded = $this->updateMultipartUpload(
                token: $token,
                fileId: (string) $resolved['file_id'],
                metadata: ['name' => $uploadName],
                body: $contents,
                mimeType: $mimeType,
            );

            $uploaded['sync_mode'] = $resolved['sync_mode'] ?? KomiteDocument::GDRIVE_SYNC_MODE_REPLACED;

            return $uploaded;
        }

        $uploaded = $this->createMultipartUpload(
            token: $token,
            metadata: ['name' => $uploadName, 'parents' => [$parentId]],
            body: $contents,
            mimeType: $mimeType,
        );

        $uploaded['sync_mode'] = $resolved['sync_mode'] ?? KomiteDocument::GDRIVE_SYNC_MODE_CREATED;

        return $uploaded;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function searchSingleFileByName(string $token, string $parentId, string $name, GoogleDriveSettings $settings, bool $folderOnly): ?array
    {
        $query = sprintf(
            "name = '%s' and '%s' in parents and trashed = false%s",
            $this->escapeDriveQueryValue($name),
            $this->escapeDriveQueryValue($parentId),
            $folderOnly ? " and mimeType = 'application/vnd.google-apps.folder'" : ''
        );

        $response = $this->authorizedRequest($token)
            ->get(self::FILES_ENDPOINT, array_filter([
                'q' => $query,
                'fields' => 'files(id,name,webViewLink)',
                'supportsAllDrives' => 'true',
                'includeItemsFromAllDrives' => 'true',
                'pageSize' => 1,
                'corpora' => filled($settings->sharedDriveId) ? 'drive' : null,
                'driveId' => $settings->sharedDriveId,
            ]))
            ->throw()
            ->json();

        $file = Arr::first($response['files'] ?? []);

        return is_array($file) ? $file : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveExistingRemoteFileId(
        string $token,
        GoogleDriveSettings $settings,
        string $parentId,
        string $uploadName,
        ?string $existingFileId = null,
    ): array
    {
        $missingExistingReference = false;

        if (filled($existingFileId)) {
            try {
                $existing = $this->fetchFileMetadata($token, (string) $existingFileId);
                $parents = collect(Arr::wrap($existing['parents'] ?? []))
                    ->filter(fn (mixed $parent): bool => filled($parent))
                    ->map(fn (mixed $parent): string => (string) $parent)
                    ->all();

                if ($parents === [] || in_array($parentId, $parents, true)) {
                    return [
                        'file_id' => (string) ($existing['id'] ?? $existingFileId),
                        'sync_mode' => KomiteDocument::GDRIVE_SYNC_MODE_REPLACED,
                    ];
                }
            } catch (Throwable) {
                $missingExistingReference = true;
            }
        }

        $found = $this->searchSingleFileByName($token, $parentId, $uploadName, $settings, false);

        if (! is_array($found) || ! filled($found['id'] ?? null)) {
            return [
                'file_id' => null,
                'sync_mode' => $missingExistingReference
                    ? KomiteDocument::GDRIVE_SYNC_MODE_RESTORED
                    : KomiteDocument::GDRIVE_SYNC_MODE_CREATED,
            ];
        }

        return [
            'file_id' => (string) $found['id'],
            'sync_mode' => KomiteDocument::GDRIVE_SYNC_MODE_REPLACED,
        ];
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array<string, mixed>
     */
    private function updateMultipartUpload(string $token, string $fileId, array $metadata, string $body, string $mimeType): array
    {
        $boundary = 'gdrive-update-'.Str::random(20);
        $payload = "--{$boundary}\r\n";
        $payload .= "Content-Type: application/json; charset=UTF-8\r\n\r\n";
        $payload .= json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\r\n";
        $payload .= "--{$boundary}\r\n";
        $payload .= "Content-Type: {$mimeType}\r\n\r\n";
        $payload .= $body."\r\n";
        $payload .= "--{$boundary}--";

        return $this->authorizedRequest($token)
            ->withBody($payload, "multipart/related; boundary={$boundary}")
            ->patch(self::UPLOAD_ENDPOINT.'/'.rawurlencode($fileId).'?'.http_build_query([
                'uploadType' => 'multipart',
                'supportsAllDrives' => 'true',
                'fields' => 'id,name,webViewLink,webContentLink',
            ]))
            ->throw()
            ->json();
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array<string, mixed>
     */
    private function createMultipartUpload(string $token, array $metadata, string $body, string $mimeType): array
    {
        $boundary = 'gdrive-'.Str::random(20);
        $payload = "--{$boundary}\r\n";
        $payload .= "Content-Type: application/json; charset=UTF-8\r\n\r\n";
        $payload .= json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\r\n";
        $payload .= "--{$boundary}\r\n";
        $payload .= "Content-Type: {$mimeType}\r\n\r\n";
        $payload .= $body."\r\n";
        $payload .= "--{$boundary}--";

        return $this->authorizedRequest($token)
            ->withBody($payload, "multipart/related; boundary={$boundary}")
            ->post(self::UPLOAD_ENDPOINT.'?'.http_build_query([
                'uploadType' => 'multipart',
                'supportsAllDrives' => 'true',
                'fields' => 'id,name,webViewLink,webContentLink',
            ]))
            ->throw()
            ->json();
    }

    /**
     * @return array<string, mixed>
     */
    private function fetchFileMetadata(string $token, string $fileId): array
    {
        return $this->authorizedRequest($token)
            ->get(self::FILES_ENDPOINT.'/'.rawurlencode($fileId), [
                'supportsAllDrives' => 'true',
                'fields' => 'id,name,webViewLink,parents',
            ])
            ->throw()
            ->json();
    }

    private function fetchAccessToken(GoogleDriveSettings $settings): string
    {
        $credentials = $settings->credentials();

        if (! is_array($credentials)) {
            throw new RuntimeException('JSON service account Google Drive tidak valid.');
        }

        $clientEmail = trim((string) ($credentials['client_email'] ?? ''));
        $privateKey = (string) ($credentials['private_key'] ?? '');

        if ($clientEmail === '' || trim($privateKey) === '') {
            throw new RuntimeException('JSON service account belum memuat client_email atau private_key.');
        }

        $now = now()->timestamp;
        $jwt = $this->makeSignedJwt([
            'iss' => $clientEmail,
            'scope' => 'https://www.googleapis.com/auth/drive',
            'aud' => self::TOKEN_ENDPOINT,
            'iat' => $now,
            'exp' => $now + 3600,
        ], $privateKey);

        $response = $this->http
            ->asForm()
            ->post(self::TOKEN_ENDPOINT, [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $jwt,
            ])
            ->throw()
            ->json();

        $token = $response['access_token'] ?? null;

        if (! is_string($token) || trim($token) === '') {
            throw new RuntimeException('Google OAuth tidak mengembalikan access token.');
        }

        return $token;
    }

    /**
     * @param  array<string, mixed>  $claims
     */
    private function makeSignedJwt(array $claims, string $privateKey): string
    {
        $header = $this->base64UrlEncode(json_encode(['alg' => 'RS256', 'typ' => 'JWT']) ?: '{}');
        $payload = $this->base64UrlEncode(json_encode($claims, JSON_UNESCAPED_SLASHES) ?: '{}');
        $input = $header.'.'.$payload;
        $signature = '';

        if (! openssl_sign($input, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('Private key Google Drive tidak dapat dipakai untuk menandatangani token.');
        }

        return $input.'.'.$this->base64UrlEncode($signature);
    }

    private function authorizedRequest(string $token): PendingRequest
    {
        return $this->http
            ->withToken($token)
            ->withHeaders(['Accept' => 'application/json']);
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function fileUrl(string $fileId): ?string
    {
        $fileId = trim($fileId);

        return $fileId !== '' ? 'https://drive.google.com/file/d/'.$fileId.'/view' : null;
    }

    private function folderUrl(string $folderId): ?string
    {
        $folderId = trim($folderId);

        return $folderId !== '' ? 'https://drive.google.com/drive/folders/'.$folderId : null;
    }

    /**
     * @param  array<int, string>  $syncModes
     */
    private function determineOverallSyncMode(array $syncModes): string
    {
        if (in_array(KomiteDocument::GDRIVE_SYNC_MODE_RESTORED, $syncModes, true)) {
            return KomiteDocument::GDRIVE_SYNC_MODE_RESTORED;
        }

        if (in_array(KomiteDocument::GDRIVE_SYNC_MODE_REPLACED, $syncModes, true)) {
            return KomiteDocument::GDRIVE_SYNC_MODE_REPLACED;
        }

        return KomiteDocument::GDRIVE_SYNC_MODE_CREATED;
    }

    private function syncModeSuccessMessage(string $syncMode): string
    {
        return match ($syncMode) {
            KomiteDocument::GDRIVE_SYNC_MODE_RESTORED => 'Mirror Google Drive berhasil dipulihkan dari file lokal.',
            KomiteDocument::GDRIVE_SYNC_MODE_REPLACED => 'Mirror Google Drive berhasil diperbarui dari file lokal.',
            default => 'Semua file berhasil tersimpan di Google Drive.',
        };
    }

    private function escapeDriveQueryValue(string $value): string
    {
        return str_replace(['\\', '\''], ['\\\\', '\\\''], $value);
    }

    private function formatExceptionMessage(Throwable $exception): string
    {
        return 'Upload Google Drive gagal: '.$this->humanizeGoogleDriveException($exception);
    }

    private function formatConnectionExceptionMessage(Throwable $exception): string
    {
        return 'Koneksi Google Drive gagal: '.$this->humanizeGoogleDriveException($exception);
    }

    private function humanizeGoogleDriveException(Throwable $exception): string
    {
        $message = trim($exception->getMessage());

        if ($exception instanceof RequestException) {
            $apiMessage = data_get($exception->response?->json(), 'error.message');

            if (is_string($apiMessage) && trim($apiMessage) !== '') {
                $message = trim($apiMessage);
            }
        }

        if (str_contains($message, 'Shared drive not found:')) {
            return 'Shared Drive ID tidak ditemukan. Isi field Shared Drive ID dengan ID drive, bukan Folder ID Tujuan.';
        }

        if ($message === 'File not found: .') {
            return 'Folder tujuan Google Drive tidak ditemukan. Biasanya Folder ID Tujuan kosong, salah, atau tidak berada di Shared Drive yang dipilih.';
        }

        if (preg_match('/^File not found: ([^.]+)\.$/', $message, $matches) === 1) {
            return 'Referensi file atau folder Google Drive lama tidak ditemukan: '.$matches[1].'. Gunakan Upload Sekarang untuk membuat ulang mirror dari file lokal.';
        }

        if (str_contains($message, 'Service Accounts do not have storage quota')) {
            return 'Service account tidak bisa upload ke My Drive biasa. Gunakan Shared Drive dan isi Shared Drive ID yang benar.';
        }

        if (str_contains($message, 'Insufficient permissions for the specified parent')) {
            return 'Service account belum punya izin tulis ke folder induk. Tambahkan sebagai Editor atau Content manager.';
        }

        return $message;
    }
}











