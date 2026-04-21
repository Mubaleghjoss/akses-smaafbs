<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BoardingArsipMtHistory extends Model
{
    protected $table = 'boarding_arsip_mt_histories';

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'user_id' => 'integer',
        'created_at' => 'datetime',
    ];

    public function arsip(): BelongsTo
    {
        return $this->belongsTo(BoardingArsipMt::class, 'boarding_arsip_mt_id');
    }
}
