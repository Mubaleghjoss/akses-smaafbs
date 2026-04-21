<?php

namespace App\Models;

use App\Support\Admin\Dashboard\DashboardCacheSupport;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProkerBidang extends Model
{
    protected $table = 'proker_bidangs';

    protected $guarded = [];

    protected $casts = [
        'is_active' => 'boolean',
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

    public function prokers(): HasMany
    {
        return $this->hasMany(Proker::class, 'bidang_id');
    }
}
