<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BoardingPencapaianDetail extends Model
{
    protected $table = 'boarding_pencapaian_details';

    protected $guarded = [];

    protected $casts = [
        'target_nilai' => 'integer',
        'capaian_nilai' => 'integer',
        'urutan' => 'integer',
        'tuntas_at' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        $syncOwner = function (self $detail): void {
            $detail->pencapaian?->syncFromProgressData();
        };

        static::saved($syncOwner);
        static::deleted($syncOwner);
    }

    public function pencapaian(): BelongsTo
    {
        return $this->belongsTo(BoardingPencapaian::class, 'boarding_pencapaian_id');
    }
}
