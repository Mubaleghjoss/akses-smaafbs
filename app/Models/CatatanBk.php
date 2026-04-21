<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CatatanBk extends Model
{
    protected $table = 'catatan_bks';

    protected $guarded = [];

    protected $casts = [
        'siswa_id' => 'integer',
        'created_by' => 'integer',
        'tanggal_konseling' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $record): void {
            if (! filled($record->created_by) && auth()->check()) {
                $record->created_by = auth()->id();
            }
        });
    }

    public function siswa(): BelongsTo
    {
        return $this->belongsTo(DataSiswa::class, 'siswa_id');
    }

    public function petugas(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
