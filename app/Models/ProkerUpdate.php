<?php

namespace App\Models;

use App\Support\Admin\Dashboard\DashboardCacheSupport;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProkerUpdate extends Model
{
    protected $table = 'proker_updates';

    protected $guarded = [];

    protected $casts = [
        'tanggal_update' => 'date',
        'progress_persen' => 'integer',
        'dokumentasi' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        $syncOwner = function (self $update): void {
            $update->proker?->syncFromLatestUpdate();
            DashboardCacheSupport::forgetModule('proker');
        };

        static::saved($syncOwner);
        static::deleted($syncOwner);
    }

    public function proker(): BelongsTo
    {
        return $this->belongsTo(Proker::class, 'proker_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
