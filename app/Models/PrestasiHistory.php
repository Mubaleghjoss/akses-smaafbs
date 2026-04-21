<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrestasiHistory extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'prestasi_histories';

    protected $guarded = [];

    protected $casts = [
        'snapshot' => 'array',
        'user_id' => 'integer',
        'created_at' => 'datetime',
    ];

    public function prestasi(): BelongsTo
    {
        return $this->belongsTo(Prestasi::class, 'prestasi_id');
    }
}
