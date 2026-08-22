<?php

namespace App\Models;

use App\Support\Admin\Dashboard\DashboardCacheSupport;
use App\Support\DataSiswa\DataSiswaSupport;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class Rombel extends Model
{
    protected $guarded = [];

    protected $casts = [
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $record): void {
            $record->nama = static::normalizeName($record->nama);
            $record->angkatan = filled($record->angkatan)
                ? static::normalizeName($record->angkatan)
                : DataSiswaSupport::extractAngkatan($record->nama);
        });

        $invalidateCaches = static function (): void {
            DataSiswaSupport::flushCachedOptions();
            DashboardCacheSupport::forgetModule('data_siswa');
        };

        static::saved(function (self $record) use ($invalidateCaches): void {
            $previousName = static::normalizeName($record->getOriginal('nama'));

            if ($previousName !== '' && $previousName !== $record->nama) {
                DataSiswa::query()
                    ->where('rombel_saat_ini', $previousName)
                    ->update(['rombel_saat_ini' => $record->nama]);
            }

            if ($record->wasChanged('is_active') && ! $record->is_active) {
                $record->markActiveStudentsAsNonActive();
            }

            $invalidateCaches();
        });
        static::deleted($invalidateCaches);
    }

    public static function normalizeName(?string $name): string
    {
        return Str::of((string) $name)->squish()->toString();
    }

    public static function tableAvailable(): bool
    {
        return Schema::hasTable('rombels');
    }

    public static function ensureFromName(?string $name): ?self
    {
        $normalized = static::normalizeName($name);

        if ($normalized === '' || ! static::tableAvailable()) {
            return null;
        }

        return static::query()->firstOrCreate(
            ['nama' => $normalized],
            [
                'angkatan' => DataSiswaSupport::extractAngkatan($normalized),
                'is_active' => true,
            ],
        );
    }

    public static function deleteIfEmpty(?string $name): void
    {
        $normalized = static::normalizeName($name);

        if ($normalized === '' || ! static::tableAvailable()) {
            return;
        }

        $record = static::query()->where('nama', $normalized)->first();

        if (! $record || $record->students()->exists()) {
            return;
        }

        $record->delete();
    }

    /**
     * @return array<string, mixed>
     */
    public static function nonActiveStudentAttributes(?string $rombelName): array
    {
        $name = Str::upper(static::normalizeName($rombelName));
        [$status, $category] = match (true) {
            Str::contains($name, 'ALUMNI') => ['alumni', 'lulus'],
            Str::contains($name, 'MUTASI') => ['pindah', 'mutasi'],
            Str::contains($name, 'MENGUNDURKAN') => ['keluar', 'mengundurkan_diri'],
            Str::contains($name, 'WAFAT') => ['keluar', 'wafat'],
            default => ['keluar', 'lainnya'],
        };

        $attributes = ['status' => $status];

        if (Schema::hasColumn('data_siswa', 'kategori_non_aktif')) {
            $attributes['kategori_non_aktif'] = $category;
        }

        if (Schema::hasColumn('data_siswa', 'alasan_non_aktif')) {
            $attributes['alasan_non_aktif'] = 'Otomatis nonaktif karena rombel '.static::normalizeName($rombelName).' dinonaktifkan.';
        }

        if (Schema::hasColumn('data_siswa', 'tanggal_non_aktif')) {
            $attributes['tanggal_non_aktif'] = now()->toDateString();
        }

        if (Schema::hasColumn('data_siswa', 'updated_at')) {
            $attributes['updated_at'] = now();
        }

        return $attributes;
    }

    public function markActiveStudentsAsNonActive(): int
    {
        if ($this->is_active || ! Schema::hasTable('data_siswa')) {
            return 0;
        }

        return $this->activeStudents()->update(
            static::nonActiveStudentAttributes($this->nama),
        );
    }

    public function students(): HasMany
    {
        return $this->hasMany(DataSiswa::class, 'rombel_saat_ini', 'nama');
    }

    public function activeStudents(): HasMany
    {
        return $this->students()->where('status', 'aktif');
    }
}
