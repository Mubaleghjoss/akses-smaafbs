<?php

namespace App\Models;

use App\Support\Admin\Dashboard\DashboardCacheSupport;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class Proker extends Model
{
    protected $table = 'prokers';

    protected $guarded = [];

    protected $casts = [
        'periode_tahun' => 'integer',
        'nomor_urut' => 'integer',
        'progress_persen' => 'integer',
        'target_mulai' => 'date',
        'target_selesai' => 'date',
        'jadwal_bulanan' => 'array',
        'last_monitored_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        $invalidateDashboardCaches = static function (self $record): void {
            DashboardCacheSupport::forgetModule('proker');
        };

        static::saved($invalidateDashboardCaches);
        static::deleted($invalidateDashboardCaches);
    }

    public function bidang(): BelongsTo
    {
        return $this->belongsTo(ProkerBidang::class, 'bidang_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function indikators(): HasMany
    {
        return $this->hasMany(ProkerIndikator::class, 'proker_id')->orderBy('urutan');
    }

    public function updates(): HasMany
    {
        return $this->hasMany(ProkerUpdate::class, 'proker_id')->latest('tanggal_update');
    }

    public function syncProgressFromIndicators(): void
    {
        $total = $this->indikators()->count();

        if ($total === 0) {
            return;
        }

        $checked = $this->indikators()->where('is_checked', true)->count();
        $progress = (int) round(($checked / $total) * 100);

        $this->updateQuietly([
            'progress_persen' => max(0, min(100, $progress)),
            'last_monitored_at' => now(),
        ]);
    }

    public function recordMonitoringUpdate(array $attributes, bool $markAllIndicators = false): ProkerUpdate
    {
        return DB::transaction(function () use ($attributes, $markAllIndicators): ProkerUpdate {
            if ($markAllIndicators) {
                $checkedAt = filled($attributes['tanggal_update'] ?? null)
                    ? Carbon::parse((string) $attributes['tanggal_update'])->endOfDay()
                    : now();

                $this->indikators()
                    ->where('is_checked', false)
                    ->get()
                    ->each(function (ProkerIndikator $indikator) use ($checkedAt): void {
                        $indikator->update([
                            'is_checked' => true,
                            'checked_at' => $checkedAt,
                        ]);
                    });

                $attributes['progress_persen'] ??= 100;
            }

            /** @var ProkerUpdate $update */
            $update = $this->updates()->create([
                'tanggal_update' => $attributes['tanggal_update'],
                'status_snapshot' => $attributes['status_snapshot'],
                'progress_persen' => $attributes['progress_persen'] ?? null,
                'ringkasan' => $attributes['ringkasan'] ?? null,
                'evaluasi' => $attributes['evaluasi'] ?? null,
                'tindak_lanjut' => $attributes['tindak_lanjut'] ?? null,
                'dokumentasi' => $attributes['dokumentasi'] ?? null,
                'created_by' => $attributes['created_by'] ?? null,
            ]);

            $this->syncFromLatestUpdate();

            return $update;
        });
    }

    public function syncFromLatestUpdate(): void
    {
        $latestUpdate = $this->updates()
            ->orderByDesc('tanggal_update')
            ->orderByDesc('id')
            ->first();

        if (! $latestUpdate) {
            $this->updateQuietly([
                'last_monitored_at' => null,
            ]);

            return;
        }

        $attributes = [
            'status' => $latestUpdate->status_snapshot ?: $this->status,
            'progress_persen' => $latestUpdate->progress_persen ?? $this->progress_persen,
            'last_monitored_at' => now(),
        ];

        if (filled($latestUpdate->evaluasi)) {
            $attributes['evaluasi_akhir'] = $latestUpdate->evaluasi;
        }

        if (filled($latestUpdate->tindak_lanjut)) {
            $attributes['tindak_lanjut_umum'] = $latestUpdate->tindak_lanjut;
        }

        $this->updateQuietly($attributes);
    }

    public static function pointDariOptions(): array
    {
        return static::query()
            ->whereNotNull('point_dari')
            ->where('point_dari', '!=', '')
            ->orderBy('point_dari')
            ->distinct()
            ->pluck('point_dari', 'point_dari')
            ->toArray();
    }

    public static function periodeTahunOptions(): array
    {
        return static::query()
            ->select('periode_tahun')
            ->distinct()
            ->orderByDesc('periode_tahun')
            ->pluck('periode_tahun', 'periode_tahun')
            ->toArray();
    }
}
