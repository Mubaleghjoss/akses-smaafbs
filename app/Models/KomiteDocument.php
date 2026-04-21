<?php

namespace App\Models;

use App\Models\Concerns\HasGoogleDriveSyncState;
use App\Support\Admin\Dashboard\DashboardCacheSupport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class KomiteDocument extends Model
{
    use HasGoogleDriveSyncState;

    public const TYPE_DECREE = 'sk';

    public const TYPE_MEETING_NOTES = 'notulen_rapat';

    public const TYPE_MEETING_SUMMARY = 'catatan_rapat';

    public const TYPE_EVENT_DOCUMENTATION = 'dokumentasi_acara';

    public const TYPE_OTHER = 'lainnya';

    public const TYPE_OPTIONS = [
        self::TYPE_DECREE => 'SK Komite',
        self::TYPE_MEETING_NOTES => 'Notulen Rapat',
        self::TYPE_MEETING_SUMMARY => 'Catatan Hasil Rapat',
        self::TYPE_EVENT_DOCUMENTATION => 'Dokumentasi Acara',
        self::TYPE_OTHER => 'Dokumen Lainnya',
    ];

    protected $table = 'komite_documents';

    protected $guarded = [];

    protected $casts = [
        'arsip_tahun' => 'integer',
        'tanggal_dokumen' => 'date',
        'dokumentasi' => 'array',
        'gdrive_upload_progress' => 'integer',
        'gdrive_documentation_payload' => 'array',
        'gdrive_uploaded_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        $invalidateDashboardCaches = static function (self $record): void {
            DashboardCacheSupport::forgetModule('google_drive_monitor');
        };

        static::saved($invalidateDashboardCaches);
        static::deleted($invalidateDashboardCaches);
    }

    public static function typeOptions(): array
    {
        return self::TYPE_OPTIONS;
    }

    public static function typeLabel(?string $type): string
    {
        return self::TYPE_OPTIONS[(string) $type] ?? ($type ?: '-');
    }

    public static function arsipTahunOptions(): array
    {
        return static::query()
            ->select('arsip_tahun')
            ->distinct()
            ->orderByDesc('arsip_tahun')
            ->pluck('arsip_tahun', 'arsip_tahun')
            ->toArray();
    }

    public function scopeForYear(Builder $query, ?int $year): Builder
    {
        return filled($year) ? $query->where('arsip_tahun', $year) : $query;
    }

    public function resolvedFileUrl(): ?string
    {
        $path = trim((string) $this->file_path);

        return $path !== '' ? Storage::disk('public')->url($path) : null;
    }

    public function hasUploadableFiles(): bool
    {
        return filled($this->file_path) || count($this->documentationFiles()) > 0;
    }

    /**
     * @return array<int, string>
     */
    public function documentationFiles(): array
    {
        return collect($this->dokumentasi ?? [])
            ->filter(fn (mixed $path): bool => filled($path))
            ->map(fn (mixed $path): string => trim((string) $path))
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{kind: string, label: string, name: string, absolute_path: string, mime_type: string}>
     */
    public function googleDriveUploadQueue(): array
    {
        $items = [];

        if (filled($this->file_path)) {
            $items[] = [
                'kind' => 'file',
                'label' => 'file utama',
                'name' => $this->buildGoogleDriveFileName((string) $this->file_path, false, 0),
                'absolute_path' => Storage::disk('public')->path((string) $this->file_path),
                'mime_type' => Storage::disk('public')->mimeType((string) $this->file_path) ?: 'application/octet-stream',
            ];
        }

        foreach ($this->documentationFiles() as $index => $path) {
            $items[] = [
                'kind' => 'documentation',
                'label' => 'dokumentasi '.($index + 1),
                'name' => $this->buildGoogleDriveFileName($path, true, $index + 1),
                'absolute_path' => Storage::disk('public')->path($path),
                'mime_type' => Storage::disk('public')->mimeType($path) ?: 'application/octet-stream',
            ];
        }

        return $items;
    }

    private function buildGoogleDriveFileName(string $path, bool $documentation, int $index): string
    {
        $extension = pathinfo($path, PATHINFO_EXTENSION);
        $base = $documentation
            ? 'dokumentasi-'.str_pad((string) $index, 2, '0', STR_PAD_LEFT)
            : Str::slug($this->judul ?: 'dokumen-komite');

        return $extension !== ''
            ? $base.'.'.Str::lower($extension)
            : $base;
    }
}
