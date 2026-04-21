<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SarprasRoomInventoryItem extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'urutan' => 'integer',
            'tanggal' => 'date',
            'jumlah' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function inventory(): BelongsTo
    {
        return $this->belongsTo(SarprasRoomInventory::class, 'sarpras_room_inventory_id');
    }
}
