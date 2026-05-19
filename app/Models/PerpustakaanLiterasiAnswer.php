<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PerpustakaanLiterasiAnswer extends Model
{
    protected $table = 'perpustakaan_literasi_answers';

    protected $guarded = [];

    protected $casts = [
        'character_count' => 'integer',
        'is_correct' => 'boolean',
        'graded_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function response(): BelongsTo
    {
        return $this->belongsTo(PerpustakaanLiterasiResponse::class, 'response_id');
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(PerpustakaanLiterasiQuestion::class, 'question_id');
    }

    public function gradedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'graded_by');
    }
}
