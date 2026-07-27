<?php

namespace App\Models;

use App\Models\Concerns\HasGoogleDriveSyncState;
use App\Support\Admin\Dashboard\DashboardCacheSupport;
use App\Support\Media\PublicImageOptimizer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class Prestasi extends Model
{
    use HasGoogleDriveSyncState;

    public const CATEGORY_AKADEMIK = 'akademik';

    public const CATEGORY_NON_AKADEMIK = 'non_akademik';

    protected $table = 'prestasis';

    protected $guarded = [];

    protected $casts = [
        'tanggal_prestasi' => 'date',
        'dokumentasi' => 'array',
        'sertifikat_files' => 'array',
        'created_by' => 'integer',
        'updated_by' => 'integer',
        'gdrive_upload_progress' => 'integer',
        'gdrive_assets_payload' => 'array',
        'gdrive_uploaded_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $prestasi): void {
            try {
                foreach (['dokumentasi', 'sertifikat_files'] as $attribute) {
                    if (! $prestasi->isDirty($attribute)) {
                        continue;
                    }

                    $prestasi->{$attribute} = collect($prestasi->{$attribute} ?? [])
                        ->map(fn ($path) => app(PublicImageOptimizer::class)
                            ->optimizeUploadedPath((string) $path, 'content'))
                        ->filter()
                        ->values()
                        ->all();
                }
            } catch (RuntimeException $exception) {
                throw ValidationException::withMessages([
                    'dokumentasi' => 'Lampiran gambar prestasi gagal dioptimalkan: '.$exception->getMessage(),
                ]);
            }
        });

        static::creating(function (self $prestasi): void {
            $prestasi->created_by ??= auth()->id();
            $prestasi->updated_by ??= auth()->id();
        });

        static::updating(function (self $prestasi): void {
            $prestasi->updated_by = auth()->id() ?: $prestasi->updated_by;
        });

        static::created(function (self $prestasi): void {
            $prestasi->logHistory('dibuat');
        });

        static::updated(function (self $prestasi): void {
            if ($prestasi->wasChanged()) {
                $prestasi->logHistory('diperbarui');
            }
        });

        $invalidateDashboardCaches = static function (self $prestasi): void {
            DashboardCacheSupport::forgetModule('prestasi');
            DashboardCacheSupport::forgetModule('google_drive_monitor');
        };

        static::saved($invalidateDashboardCaches);
        static::deleted($invalidateDashboardCaches);
    }

    public function scopeVisibleToUser(Builder $query, mixed $user): Builder
    {
        if (! $user instanceof User || $user->hasFullAdminAccess() || (! $user->isBoardingPamong() && ! $user->isGuru())) {
            return $query;
        }

        return $query->whereHas('siswa', function (Builder $studentQuery) use ($user): void {
            DataSiswa::applyVisibleScope($studentQuery, $user);
        });
    }

    public function siswa(): BelongsTo
    {
        return $this->belongsTo(DataSiswa::class, 'siswa_id');
    }

    public function histories(): HasMany
    {
        return $this->hasMany(PrestasiHistory::class, 'prestasi_id')->latest('created_at');
    }

    public static function kategoriOptions(): array
    {
        return [
            self::CATEGORY_AKADEMIK => 'Akademik',
            self::CATEGORY_NON_AKADEMIK => 'Non Akademik',
        ];
    }

    public static function kategoriLabel(?string $kategori): string
    {
        return self::kategoriOptions()[$kategori] ?? 'Belum dikategorikan';
    }

    public static function kategoriColor(?string $kategori): string
    {
        return match ($kategori) {
            self::CATEGORY_AKADEMIK => 'primary',
            self::CATEGORY_NON_AKADEMIK => 'success',
            default => 'gray',
        };
    }

    public function hasUploadableFiles(): bool
    {
        return count($this->certificateFiles()) > 0 || count($this->documentationFiles()) > 0;
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
     * @return array<int, string>
     */
    public function certificateFiles(): array
    {
        return collect($this->sertifikat_files ?? [])
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

        foreach ($this->certificateFiles() as $index => $path) {
            $items[] = [
                'kind' => 'certificate',
                'label' => 'sertifikat '.($index + 1),
                'name' => $this->buildGoogleDriveFileName($path, 'sertifikat', $index + 1),
                'absolute_path' => Storage::disk('public')->path($path),
                'mime_type' => Storage::disk('public')->mimeType($path) ?: 'application/octet-stream',
            ];
        }

        foreach ($this->documentationFiles() as $index => $path) {
            $items[] = [
                'kind' => 'documentation',
                'label' => 'dokumentasi '.($index + 1),
                'name' => $this->buildGoogleDriveFileName($path, 'dokumentasi', $index + 1),
                'absolute_path' => Storage::disk('public')->path($path),
                'mime_type' => Storage::disk('public')->mimeType($path) ?: 'application/octet-stream',
            ];
        }

        return $items;
    }

    public function logHistory(string $aksi): void
    {
        $this->histories()->create([
            'aksi' => $aksi,
            'judul_ringkas' => trim($this->nama_lomba.' - '.($this->juara ?: 'Prestasi diperbarui')),
            'snapshot' => [
                'nama_lomba' => $this->nama_lomba,
                'kategori' => $this->kategori,
                'tanggal_prestasi' => $this->tanggal_prestasi?->format('Y-m-d'),
                'penyelenggara' => $this->penyelenggara,
                'juara' => $this->juara,
                'hadiah' => $this->hadiah,
                'keterangan' => $this->keterangan,
                'dokumentasi_count' => count($this->dokumentasi ?? []),
                'sertifikat_count' => count($this->sertifikat_files ?? []),
            ],
            'user_id' => auth()->id(),
            'user_name' => auth()->user()?->name,
            'created_at' => now(),
        ]);
    }

    private function buildGoogleDriveFileName(string $path, string $prefix, int $index): string
    {
        $extension = pathinfo($path, PATHINFO_EXTENSION);
        $slug = Str::slug($this->nama_lomba ?: 'prestasi');
        $base = $slug !== ''
            ? $slug.'-'.$prefix.'-'.str_pad((string) $index, 2, '0', STR_PAD_LEFT)
            : $prefix.'-'.str_pad((string) $index, 2, '0', STR_PAD_LEFT);

        return $extension !== ''
            ? $base.'.'.Str::lower($extension)
            : $base;
    }
}
