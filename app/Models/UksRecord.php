<?php

namespace App\Models;

use App\Support\Admin\Dashboard\DashboardCacheSupport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UksRecord extends Model
{
    public const ANTHROPOMETRY_CATEGORY = 'Antropometri';

    public const ANTHROPOMETRY_HANDLING = 'Update antropometri berkala';

    protected $table = 'uks_records';

    protected $guarded = [];

    protected $casts = [
        'tanggal_sakit' => 'date',
        'berat_badan' => 'decimal:2',
        'tinggi_badan' => 'decimal:2',
        'lingkar_kepala' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        $invalidateDashboardCaches = static function (self $record): void {
            DashboardCacheSupport::forgetModule('uks');
        };

        static::saved($invalidateDashboardCaches);
        static::deleted($invalidateDashboardCaches);
    }

    public function siswa(): BelongsTo
    {
        return $this->belongsTo(DataSiswa::class, 'siswa_id');
    }

    public function scopeWithoutAnthropometryCategory(Builder $query): Builder
    {
        return $query->where(function (Builder $builder): void {
            $builder
                ->whereNull('kategori')
                ->orWhere('kategori', '!=', self::ANTHROPOMETRY_CATEGORY);
        });
    }

    public static function kategoriOptions(): array
    {
        return static::query()
            ->whereNotNull('kategori')
            ->where('kategori', '!=', '')
            ->orderBy('kategori')
            ->pluck('kategori', 'kategori')
            ->all();
    }

    public static function kelasOptions(): array
    {
        return static::query()
            ->whereNotNull('kelas')
            ->where('kelas', '!=', '')
            ->orderBy('kelas')
            ->pluck('kelas', 'kelas')
            ->all();
    }
}
