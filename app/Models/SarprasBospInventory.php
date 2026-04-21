<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SarprasBospInventory extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'quality' => 'integer',
            'bulan_beli' => 'integer',
            'tahun_beli' => 'integer',
            'tanggal_datang' => 'date',
            'total_harga' => 'decimal:2',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public static function tahunBeliOptions(): array
    {
        return static::query()
            ->whereNotNull('tahun_beli')
            ->select('tahun_beli')
            ->distinct()
            ->orderByDesc('tahun_beli')
            ->pluck('tahun_beli', 'tahun_beli')
            ->toArray();
    }
}
