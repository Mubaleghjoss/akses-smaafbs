<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class BkKasus extends Model
{
    protected $table = 'bk_kasus';

    protected $guarded = [];

    protected $casts = [
        'tanggal_kasus' => 'date',
        'tanggal_tindak_lanjut' => 'date',
        'created_by' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public const STATUS_BELUM = 'belum';

    public const STATUS_PROSES = 'proses';

    public const STATUS_SELESAI = 'selesai';

    protected static function booted(): void
    {
        static::creating(function (self $record): void {
            if (! filled($record->created_by) && auth()->check()) {
                $record->created_by = auth()->id();
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public static function statusOptions(): array
    {
        return [
            self::STATUS_BELUM => 'Belum Ditindak',
            self::STATUS_PROSES => 'Sedang Diproses',
            self::STATUS_SELESAI => 'Selesai',
        ];
    }

    public static function statusLabel(?string $status): string
    {
        return self::statusOptions()[$status] ?? '-';
    }

    public static function statusColor(?string $status): string
    {
        return match ($status) {
            self::STATUS_SELESAI => 'success',
            self::STATUS_PROSES => 'warning',
            default => 'danger',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function kategoriOptions(): array
    {
        return [
            'kedisiplinan' => 'Kedisiplinan',
            'kehadiran' => 'Kehadiran',
            'akademik' => 'Akademik',
            'adab' => 'Adab / Akhlak',
            'kebersihan' => 'Kebersihan & Ketertiban',
            'perangkat' => 'Penggunaan Perangkat',
            'lainnya' => 'Lainnya',
        ];
    }

    public static function kategoriLabel(?string $kategori): string
    {
        return self::kategoriOptions()[$kategori] ?? ($kategori ?: '-');
    }

    /**
     * @return array<string, string>
     */
    public static function tingkatOptions(): array
    {
        return [
            'ringan' => 'Ringan',
            'sedang' => 'Sedang',
            'berat' => 'Berat',
        ];
    }

    public static function tingkatLabel(?string $tingkat): string
    {
        return self::tingkatOptions()[$tingkat] ?? '-';
    }

    public static function tingkatColor(?string $tingkat): string
    {
        return match ($tingkat) {
            'berat' => 'danger',
            'sedang' => 'warning',
            'ringan' => 'info',
            default => 'gray',
        };
    }

    public function siswa(): BelongsToMany
    {
        return $this->belongsToMany(DataSiswa::class, 'bk_kasus_siswa', 'bk_kasus_id', 'siswa_id')
            ->withPivot(['rombel_snapshot'])
            ->withTimestamps();
    }

    public function petugas(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Daftar kelas unik yang terlibat pada kasus ini, memakai snapshot rombel
     * saat kasus dicatat dan jatuh kembali ke rombel siswa saat ini.
     *
     * @return array<int, string>
     */
    public function kelasTerlibat(): array
    {
        return $this->siswa
            ->map(fn (DataSiswa $siswa): string => trim((string) (
                $siswa->pivot?->rombel_snapshot ?: $siswa->rombel_saat_ini
            )))
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->all();
    }
}
