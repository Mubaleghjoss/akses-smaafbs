<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class SarprasActivity extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'tanggal_pengerjaan' => 'date',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function fotoSebelumUrl(): ?string
    {
        return filled($this->foto_sebelum) ? Storage::disk('public')->url($this->foto_sebelum) : null;
    }

    public function fotoSesudahUrl(): ?string
    {
        return filled($this->foto_sesudah) ? Storage::disk('public')->url($this->foto_sesudah) : null;
    }
}
