<?php

namespace App\Models;

use App\Support\Admin\Dashboard\DashboardCacheSupport;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProkerIndikator extends Model
{
    protected $table = 'proker_indikators';

    protected $guarded = [];

    protected $casts = [
        'urutan' => 'integer',
        'bobot' => 'integer',
        'is_checked' => 'boolean',
        'checked_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $indikator): void {
            if ($indikator->is_checked && ! $indikator->checked_at) {
                $indikator->checked_at = now();
            }

            if (! $indikator->is_checked) {
                $indikator->checked_at = null;
            }
        });

        $syncOwner = function (self $indikator): void {
            $indikator->proker?->syncProgressFromIndicators();
            DashboardCacheSupport::forgetModule('proker');
        };

        static::saved($syncOwner);
        static::deleted($syncOwner);
    }

    public function proker(): BelongsTo
    {
        return $this->belongsTo(Proker::class, 'proker_id');
    }
}
