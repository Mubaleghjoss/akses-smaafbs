<?php

namespace App\Models;

use App\Support\Admin\Dashboard\DashboardCacheSupport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class DataSiswa extends Model
{
    public const STATUS_OPTIONS = [
        'aktif' => 'Aktif',
        'alumni' => 'Alumni',
        'pindah' => 'Pindah / Mutasi',
        'keluar' => 'Keluar',
    ];

    public const NON_ACTIVE_STATUSES = [
        'alumni',
        'pindah',
        'keluar',
    ];

    public const NON_ACTIVE_CATEGORY_OPTIONS = [
        'lulus' => 'Lulus / Alumni',
        'mutasi' => 'Mutasi',
        'mengundurkan_diri' => 'Mengundurkan Diri',
        'wafat' => 'Wafat',
        'lainnya' => 'Lainnya',
    ];

    protected $table = 'data_siswa';

    protected $guarded = [];

    protected $casts = [
        'tanggal_lahir' => 'date',
        'tanggal_non_aktif' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $record): void {
            foreach (['kepribadian', 'gaya_belajar', 'profiling', 'mbti'] as $attribute) {
                $value = trim((string) ($record->{$attribute} ?? ''));
                $record->{$attribute} = $value !== '' ? Str::upper($value) : null;
            }
        });

        $invalidateDashboardCaches = static function (self $record): void {
            DashboardCacheSupport::forgetModule('data_siswa');
            DashboardCacheSupport::forgetModule('prestasi');
            DashboardCacheSupport::forgetModule('uks');
        };

        static::saved($invalidateDashboardCaches);
        static::deleted($invalidateDashboardCaches);
    }

    public static function statusOptions(): array
    {
        return self::STATUS_OPTIONS;
    }

    public static function nonActiveStatuses(): array
    {
        return self::NON_ACTIVE_STATUSES;
    }

    public static function nonActiveCategoryOptions(): array
    {
        return self::NON_ACTIVE_CATEGORY_OPTIONS;
    }

    public static function isNonActiveStatus(?string $status): bool
    {
        return in_array(strtolower((string) $status), self::NON_ACTIVE_STATUSES, true);
    }

    public static function statusLabel(?string $status): string
    {
        $normalized = strtolower((string) $status);

        return self::STATUS_OPTIONS[$normalized] ?? ($status ?: '-');
    }

    public static function resolveNonActiveCategory(?string $status, ?string $category): ?string
    {
        $normalizedCategory = strtolower(trim((string) $category));

        if ($normalizedCategory !== '' && array_key_exists($normalizedCategory, self::NON_ACTIVE_CATEGORY_OPTIONS)) {
            return $normalizedCategory;
        }

        return match (strtolower((string) $status)) {
            'alumni' => 'lulus',
            'pindah' => 'mutasi',
            'keluar' => 'lainnya',
            default => null,
        };
    }

    public static function nonActiveCategoryLabel(?string $status, ?string $category): string
    {
        $resolvedCategory = self::resolveNonActiveCategory($status, $category);

        return $resolvedCategory !== null
            ? (self::NON_ACTIVE_CATEGORY_OPTIONS[$resolvedCategory] ?? $resolvedCategory)
            : '-';
    }

    public function scopeVisibleToUser(Builder $query, mixed $user): Builder
    {
        if (! $user instanceof User) {
            return $query;
        }

        return $user->applyBoardingStudentScope($query);
    }

    public static function applyVisibleScope(Builder $query, mixed $user): Builder
    {
        return $query->visibleToUser($user);
    }

    public function boardingRapots(): HasMany
    {
        return $this->hasMany(BoardingRapot::class, 'siswa_id');
    }

    public function boardingPencapaian(): HasOne
    {
        return $this->hasOne(BoardingPencapaian::class, 'siswa_id');
    }

    public function boardingArsipMt(): HasOne
    {
        return $this->hasOne(BoardingArsipMt::class, 'siswa_id');
    }

    public function boardingKonselingMts(): HasMany
    {
        return $this->hasMany(BoardingKonselingMt::class, 'siswa_id');
    }

    public function boardingPerizinanSiswas(): HasMany
    {
        return $this->hasMany(BoardingPerizinanSiswa::class, 'siswa_id');
    }

    public function catatanBks(): HasMany
    {
        return $this->hasMany(CatatanBk::class, 'siswa_id');
    }

    public function boardingKeuanganSiswa(): HasOne
    {
        return $this->hasOne(BoardingKeuanganSiswa::class, 'siswa_id');
    }

    public function prestasis(): HasMany
    {
        return $this->hasMany(Prestasi::class, 'siswa_id');
    }
}
