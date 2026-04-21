<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PerpustakaanBuku extends Model
{
    protected $table = 'perpustakaan_buku';

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'upload_date' => 'datetime',
        'download_count' => 'integer',
        'file_size' => 'integer',
    ];

    public function kategori(): BelongsTo
    {
        return $this->belongsTo(PerpustakaanKategori::class, 'kategori_id');
    }

    public function lemari(): BelongsTo
    {
        return $this->belongsTo(PerpustakaanLemari::class, 'lemari_id');
    }
}
