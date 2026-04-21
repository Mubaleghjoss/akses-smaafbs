<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BoardingPencapaianUpdate extends Model
{
    protected $table = 'boarding_pencapaian_updates';

    protected $guarded = [];

    protected $casts = [
        'tanggal_update' => 'date',
        'jumlah_tambahan' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        $syncOwner = function (self $update): void {
            $update->pencapaian?->syncFromProgressData();
        };

        static::saved($syncOwner);
        static::deleted($syncOwner);
    }

    public function pencapaian(): BelongsTo
    {
        return $this->belongsTo(BoardingPencapaian::class, 'boarding_pencapaian_id');
    }
}
