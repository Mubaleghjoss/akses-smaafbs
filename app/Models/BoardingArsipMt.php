<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBoardingStudent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BoardingArsipMt extends Model
{
    use BelongsToBoardingStudent;

    public const STATUS_OPTIONS = [
        'berangkat_tes' => 'Berangkat Tes',
        'gagal_tes' => 'Gagal Tes',
        'lulus_tes_mt_lanjut_sekolah' => 'Lulus Tes MT dan Lanjut Sekolah',
        'lulus_tes_mt_lulus_sekolah' => 'Lulus Tes MT dan Lulus Sekolah',
    ];

    protected $table = 'boarding_arsip_mts';

    protected $guarded = [];

    protected $casts = [
        'foto_angkatan' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::created(function (self $record): void {
            $record->logStatusHistory(null, $record->status_arsip ?: 'berangkat_tes');
        });

        static::updated(function (self $record): void {
            if ($record->wasChanged('status_arsip')) {
                $record->logStatusHistory(
                    $record->getOriginal('status_arsip'),
                    $record->status_arsip,
                );
            }
        });
    }

    public static function statusOptions(): array
    {
        return self::STATUS_OPTIONS;
    }

    public static function statusLabel(?string $status): string
    {
        return self::STATUS_OPTIONS[(string) $status] ?? ($status ?: '-');
    }

    public static function tahunLulusOptions(mixed $user): array
    {
        return static::query()
            ->visibleToUser($user)
            ->whereNotNull('tahun_lulus')
            ->select('tahun_lulus')
            ->distinct()
            ->orderByDesc('tahun_lulus')
            ->pluck('tahun_lulus', 'tahun_lulus')
            ->toArray();
    }

    public function siswa(): BelongsTo
    {
        return $this->belongsTo(DataSiswa::class, 'siswa_id');
    }

    public function histories(): HasMany
    {
        return $this->hasMany(BoardingArsipMtHistory::class, 'boarding_arsip_mt_id')->latest('created_at');
    }

    public function logStatusHistory(?string $statusLama, ?string $statusBaru): void
    {
        $statusBaru = $statusBaru ?: 'berangkat_tes';

        $this->histories()->create([
            'status_lama' => $statusLama,
            'status_baru' => $statusBaru,
            'judul_ringkas' => $this->siswa?->nama ?: 'Perubahan status arsip boarding',
            'user_id' => auth()->id(),
            'user_name' => auth()->user()?->name,
            'created_at' => now(),
        ]);
    }
}
