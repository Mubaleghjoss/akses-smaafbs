<?php

namespace App\Models;

use App\Models\Concerns\HasGoogleDriveSyncState;
use App\Support\Admin\Dashboard\DashboardCacheSupport;
use App\Support\Documents\ManagedDocumentNaming;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class BerkasSiswa extends Model
{
    use HasGoogleDriveSyncState;

    protected $table = 'berkas_siswa';

    const CREATED_AT = 'uploaded_at';

    const UPDATED_AT = 'updated_at';

    protected $guarded = [];

    protected $casts = [
        'uploaded_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
        'has_deleted' => 'boolean',
        'file_size' => 'integer',
        'gdrive_upload_progress' => 'integer',
        'gdrive_uploaded_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        $invalidateDashboardCaches = static function (self $record): void {
            DashboardCacheSupport::forgetModule('google_drive_monitor');
        };

        static::saved($invalidateDashboardCaches);
        static::deleted($invalidateDashboardCaches);
    }

    public function siswa(): BelongsTo
    {
        return $this->belongsTo(DataSiswa::class, 'siswa_id');
    }

    public function jenisBerkas(): BelongsTo
    {
        return $this->belongsTo(JenisBerkas::class, 'jenis_berkas_id');
    }

    public function resolvedFilePath(): ?string
    {
        $path = trim((string) $this->file_path);

        if ($path === '') {
            return null;
        }

        if (str_starts_with($path, 'assets/')) {
            return preg_replace('#^assets/uploads/#', '', $path) ?: $path;
        }

        return $path;
    }

    public function resolvedFileUrl(): ?string
    {
        $path = $this->resolvedFilePath();

        return $path ? Storage::disk('public')->url($path) : null;
    }

    public function hasUploadableFiles(): bool
    {
        return filled($this->resolvedFilePath());
    }

    /**
     * @return array{label: string, name: string, absolute_path: string, mime_type: string}|null
     */
    public function googleDriveUploadFile(): ?array
    {
        $path = $this->resolvedFilePath();

        if (! $path) {
            return null;
        }

        return [
            'label' => 'file berkas siswa',
            'name' => $this->buildGoogleDriveFileName($path),
            'absolute_path' => Storage::disk('public')->path($path),
            'mime_type' => Storage::disk('public')->mimeType($path) ?: ((string) $this->mime_type ?: 'application/octet-stream'),
        ];
    }

    public function displayFileName(): string
    {
        return ManagedDocumentNaming::fileNameFromParts(
            $this->documentNameParts(),
            ManagedDocumentNaming::extensionFromPath((string) $this->file_path),
        );
    }

    /**
     * @return array<int, ?string>
     */
    public function documentNameParts(): array
    {
        $student = $this->relationLoaded('siswa')
            ? $this->siswa
            : $this->siswa()->first(['id', 'nama', 'rombel_saat_ini']);
        $documentType = $this->relationLoaded('jenisBerkas')
            ? $this->jenisBerkas?->nama_berkas
            : $this->jenisBerkas()->value('nama_berkas');

        return [
            $documentType,
            $student?->nama,
            $student?->rombel_saat_ini,
        ];
    }

    private function buildGoogleDriveFileName(string $path): string
    {
        $this->loadMissing(['siswa:id,nama,rombel_saat_ini', 'jenisBerkas:id,nama_berkas']);

        return ManagedDocumentNaming::fileNameFromParts(
            $this->documentNameParts(),
            ManagedDocumentNaming::extensionFromPath($path),
        );
    }
}
