<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class PerpustakaanLiterasiQuestion extends Model
{
    protected $table = 'perpustakaan_literasi_questions';

    protected $guarded = [];

    protected $casts = [
        'is_required' => 'boolean',
        'min_characters' => 'integer',
        'max_characters' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function material(): BelongsTo
    {
        return $this->belongsTo(PerpustakaanLiterasiMaterial::class, 'material_id');
    }

    public function answers(): HasMany
    {
        return $this->hasMany(PerpustakaanLiterasiAnswer::class, 'question_id');
    }

    public function imageUrl(): ?string
    {
        $path = trim((string) $this->image_path);

        return $path !== '' ? Storage::disk('public')->url($path) : null;
    }
}
