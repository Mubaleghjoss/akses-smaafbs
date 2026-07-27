<?php

namespace App\Models;

use App\Support\Media\PublicImageOptimizer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class BeritaUpdate extends Model
{
    public const PHASES = [
        'persiapan' => 'Persiapan',
        'acara' => 'Acara',
        'selesai' => 'Selesai',
    ];

    protected $table = 'berita_updates';

    protected $guarded = [];

    protected $casts = [
        'tanggal_update' => 'datetime',
        'progress_percent' => 'integer',
        'documentation_media' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $update): void {
            if (! $update->isDirty('documentation_media')) {
                return;
            }

            try {
                $update->documentation_media = collect($update->documentation_media ?? [])
                    ->map(fn ($path) => app(PublicImageOptimizer::class)
                        ->optimizeUploadedPath((string) $path, 'content'))
                    ->filter()
                    ->values()
                    ->all();
            } catch (RuntimeException $exception) {
                throw ValidationException::withMessages([
                    'documentation_media' => 'Dokumentasi berita gagal dioptimalkan: '.$exception->getMessage(),
                ]);
            }
        });

        static::saved(function (self $update): void {
            $update->berita?->syncTrackerSnapshotFromUpdates();
        });

        static::deleted(function (self $update): void {
            $update->berita?->syncTrackerSnapshotFromUpdates();
        });
    }

    public function berita(): BelongsTo
    {
        return $this->belongsTo(Berita::class, 'berita_id');
    }

    public function getPhaseLabelAttribute(): string
    {
        return self::PHASES[$this->phase] ?? ucfirst((string) $this->phase);
    }
}
