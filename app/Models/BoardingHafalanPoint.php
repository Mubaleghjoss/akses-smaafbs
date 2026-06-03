<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BoardingHafalanPoint extends Model
{
    public const SCOPE_OPTIONS = [
        'boarding' => 'Materi Boarding',
        'mt' => 'Materi MT',
    ];

    public const MATERI_OPTIONS = [
        'materi_quran_bacaan' => "1. Materi Qur'an Bacaan",
        'materi_tambahan_makna_quran' => "2. Materi Qur'an Makna",
        'materi_tambahan_makna_hadits' => '3. Materi Hadits Makna',
        'materi_pengetesan_makna' => '4. Materi Pengetesan Makna',
        'pegon_bacaan' => '5. Materi Hafalan - 1. Hafalan Kelas Pegon Bacaan',
        'lambatan' => '5. Materi Hafalan - 2. Hafalan Kelas Lambatan',
        'cepatan' => '5. Materi Hafalan - 3. Hafalan Kelas Cepatan',
        'materi_tambahan_hafalan' => '5. Materi Hafalan - 4. Hafalan Materi Tambahan',
    ];

    public const MT_MATERI_OPTIONS = [
        'mt_makna_hadits' => 'Materi Makna Hadits',
        'mt_tambahan' => 'Materi Tambahan',
        'mt_hafalan' => 'Materi Hafalan',
        'mt_catatan_saran' => 'Catatan dan Saran',
    ];

    public const JENIS_OPTIONS = [
        'surat' => 'Hafalan Surat',
        'doa' => 'Hafalan Doa',
        'dalil' => 'Hafalan Dalil',
        'bacaan_quran' => "Bacaan Qur'an",
        'makna_quran' => "Makna Qur'an",
        'makna_hadits' => 'Makna Hadits',
        'pengetesan_makna' => 'Pengetesan Makna',
        'mt_makna_hadits' => 'MT Makna Hadits',
        'mt_praktek' => 'MT Tugas Praktek',
        'mt_hafalan' => 'MT Hafalan',
        'mt_catatan_saran' => 'MT Catatan dan Saran',
    ];

    public const HAFALAN_JENIS = [
        'surat',
        'doa',
        'dalil',
    ];

    public const MATERI_TAMBAHAN_HAFALAN_KEY = 'materi_tambahan_hafalan';

    public const MATERI_TAMBAHAN_MAKNA_QURAN_KEY = 'materi_tambahan_makna_quran';

    public const MATERI_TAMBAHAN_MAKNA_HADITS_KEY = 'materi_tambahan_makna_hadits';

    public const MATERI_PENGETESAN_MAKNA_KEY = 'materi_pengetesan_makna';

    protected $table = 'boarding_hafalan_points';

    protected $guarded = [];

    protected $casts = [
        'is_active' => 'boolean',
        'materi_scope' => 'string',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        // Set actor tracking when auth is available, but don't fail when not.
        static::creating(function (self $model): void {
            if (blank($model->created_by) && auth()->id()) {
                $model->created_by = auth()->id();
            }

            if (blank($model->updated_by) && auth()->id()) {
                $model->updated_by = auth()->id();
            }

            if ((int) ($model->urutan ?? 0) <= 0) {
                $model->urutan = static::nextUrutanFor($model);
            }
        });

        static::saving(function (self $model): void {
            if (auth()->id()) {
                $model->updated_by = auth()->id();
            }
        });
    }

    public function assessments(): HasMany
    {
        return $this->hasMany(BoardingHafalanAssessment::class, 'boarding_hafalan_point_id');
    }

    public static function materiKeyOptions(): array
    {
        $databaseOptions = static::query()
            ->whereNotNull('materi_key')
            ->select('materi_key')
            ->distinct()
            ->orderBy('materi_key')
            ->pluck('materi_key', 'materi_key')
            ->map(fn (string $materiKey): string => static::materiLabel($materiKey))
            ->toArray();

        return static::allMateriOptions() + $databaseOptions;
    }

    public static function scopeOptions(): array
    {
        return self::SCOPE_OPTIONS;
    }

    public static function scopeLabel(?string $scope): string
    {
        return self::SCOPE_OPTIONS[$scope ?? 'boarding'] ?? 'Materi Boarding';
    }

    public static function allMateriOptions(): array
    {
        return self::MATERI_OPTIONS + self::MT_MATERI_OPTIONS;
    }

    public static function materiOptionsForScope(?string $scope): array
    {
        return $scope === 'mt'
            ? self::MT_MATERI_OPTIONS
            : self::MATERI_OPTIONS;
    }

    public static function jenisOptions(): array
    {
        return self::JENIS_OPTIONS;
    }

    public static function materiLabel(?string $materiKey): string
    {
        return static::allMateriOptions()[$materiKey ?? '']
            ?? match ($materiKey) {
                'seleksi_saringan', 'materi_tambahan' => self::MATERI_OPTIONS[self::MATERI_TAMBAHAN_HAFALAN_KEY],
                default => ucfirst(str_replace('_', ' ', (string) $materiKey)),
            };
    }

    public static function materiOrderSql(string $column = 'materi_key'): string
    {
        $cases = [];

        foreach (array_keys(static::allMateriOptions()) as $index => $materiKey) {
            $cases[] = "WHEN {$column} = '".str_replace("'", "''", $materiKey)."' THEN ".(($index + 1) * 10);
        }

        $materiTambahanOrder = array_search(self::MATERI_TAMBAHAN_HAFALAN_KEY, array_keys(static::allMateriOptions()), true);

        foreach (['materi_tambahan', 'seleksi_saringan'] as $materiKey) {
            if ($materiTambahanOrder === false) {
                continue;
            }

            $cases[] = "WHEN {$column} = '{$materiKey}' THEN ".(($materiTambahanOrder + 1) * 10);
        }

        return 'CASE '.implode(' ', $cases).' ELSE 999 END';
    }

    public static function jenisLabel(?string $jenis): string
    {
        return self::JENIS_OPTIONS[$jenis ?? ''] ?? (filled($jenis) ? ucfirst(str_replace('_', ' ', (string) $jenis)) : '-');
    }

    public static function hafalanJenis(): array
    {
        return self::HAFALAN_JENIS;
    }

    public static function nextUrutanFor(self $model): int
    {
        $max = static::query()
            ->where('materi_scope', $model->materi_scope ?: 'boarding')
            ->where('materi_key', $model->materi_key ?: '')
            ->max('urutan');

        return ((int) $max) + 1;
    }

    public static function materiTambahanKeyForJenis(?string $jenis): string
    {
        return match ($jenis) {
            'makna_quran' => self::MATERI_TAMBAHAN_MAKNA_QURAN_KEY,
            'makna_hadits' => self::MATERI_TAMBAHAN_MAKNA_HADITS_KEY,
            default => self::MATERI_TAMBAHAN_HAFALAN_KEY,
        };
    }
}
