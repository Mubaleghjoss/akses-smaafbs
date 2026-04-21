<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SarprasRoomInventory extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'tanggal_pendataan' => 'date',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(SarprasRoomInventoryItem::class)
            ->orderBy('urutan')
            ->orderBy('id');
    }

    public static function gedungOptions(): array
    {
        return static::query()
            ->whereNotNull('nama_gedung')
            ->select('nama_gedung')
            ->distinct()
            ->orderBy('nama_gedung')
            ->pluck('nama_gedung', 'nama_gedung')
            ->toArray();
    }
}
