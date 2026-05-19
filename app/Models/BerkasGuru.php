<?php

namespace App\Models;

use App\Models\Concerns\HasGoogleDriveSyncState;
use App\Support\Admin\Dashboard\DashboardCacheSupport;
use App\Support\Documents\ManagedDocumentNaming;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Storage;

class BerkasGuru extends Model
{
    use HasGoogleDriveSyncState;

    protected $table = 'berkas_guru';

    const CREATED_AT = 'uploaded_at';

    const UPDATED_AT = null;

    protected $guarded = [];

    protected $casts = [
        'uploaded_at' => 'datetime',
        'deleted_at' => 'datetime',
        'has_deleted' => 'boolean',
        'gdrive_upload_progress' => 'integer',
        'gdrive_uploaded_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        $invalidateDashboardCaches = static function (self $record): void {
            DashboardCacheSupport::forgetModule('google_drive_monitor');
            DashboardCacheSupport::forgetModule('guru_tendik');
        };

        static::saved($invalidateDashboardCaches);
        static::deleted($invalidateDashboardCaches);
    }

    public function scopeVisibleToUser(Builder $query, mixed $user): Builder
    {
        if (! $user instanceof User || $user->hasRole('admin')) {
            return $query;
        }

        if ($user->isGuru()) {
            if (! $user->guru_tendik_id) {
                return $query->whereRaw('1 = 0');
            }

            return $query->where('guru_id', $user->guru_tendik_id);
        }

        return $query;
    }

    public function guru(): BelongsTo
    {
        return $this->belongsTo(GuruTendik::class, 'guru_id');
    }

    public function jenisBerkas(): BelongsTo
    {
        return $this->belongsTo(JenisBerkas::class, 'jenis_berkas_id');
    }

    public function tugasTambahanHistory(): HasOne
    {
        return $this->hasOne(GuruTendikTugasTambahan::class, 'berkas_guru_id');
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
            'label' => 'file berkas guru',
            'name' => $this->buildGoogleDriveFileName($path),
            'absolute_path' => Storage::disk('public')->path($path),
            'mime_type' => Storage::disk('public')->mimeType($path) ?: 'application/octet-stream',
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
        $teacherName = $this->relationLoaded('guru') ? $this->guru?->nama : $this->guru()->value('nama');
        $documentType = $this->relationLoaded('jenisBerkas')
            ? $this->jenisBerkas?->nama_berkas
            : $this->jenisBerkas()->value('nama_berkas');
        $taskLabel = $this->tugasTambahanLabel();

        if (filled($taskLabel)) {
            return [
                'Tugas Tambahan',
                $taskLabel,
                $teacherName,
            ];
        }

        return [
            $documentType,
            $teacherName,
        ];
    }

    public function tugasTambahanLabel(): ?string
    {
        if ($this->relationLoaded('tugasTambahanHistory')) {
            return $this->tugasTambahanHistory?->tugas_tambahan;
        }

        return $this->tugasTambahanHistory()->value('tugas_tambahan');
    }

    public function fileExtension(): string
    {
        return strtolower(pathinfo((string) $this->file_path, PATHINFO_EXTENSION));
    }

    public function isPdf(): bool
    {
        return $this->fileExtension() === 'pdf';
    }

    public function isImage(): bool
    {
        return in_array($this->fileExtension(), ['jpg', 'jpeg', 'png', 'webp'], true);
    }

    public function isManagedTugasTambahanSk(): bool
    {
        return $this->relationLoaded('tugasTambahanHistory')
            ? $this->tugasTambahanHistory !== null
            : $this->tugasTambahanHistory()->exists();
    }

    private function buildGoogleDriveFileName(string $path): string
    {
        $this->loadMissing([
            'guru:id,nama',
            'jenisBerkas:id,nama_berkas',
            'tugasTambahanHistory:id,berkas_guru_id,tugas_tambahan',
        ]);

        return ManagedDocumentNaming::fileNameFromParts(
            $this->documentNameParts(),
            ManagedDocumentNaming::extensionFromPath($path),
        );
    }
}
