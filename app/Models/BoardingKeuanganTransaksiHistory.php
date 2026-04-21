<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BoardingKeuanganTransaksiHistory extends Model
{
    protected $table = 'boarding_keuangan_transaksi_histories';

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'snapshot' => 'array',
        'user_id' => 'integer',
        'created_at' => 'datetime',
    ];

    public function transaksi(): BelongsTo
    {
        return $this->belongsTo(BoardingKeuanganTransaksi::class, 'boarding_keuangan_transaksi_id');
    }
}
