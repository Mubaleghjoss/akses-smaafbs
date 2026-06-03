<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
        $path = PerpustakaanLiterasiMaterial::normalizeImagePath($this->image_path, 'literasi/questions');

        if ($path === null) {
            return null;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://') || str_starts_with($path, '/storage/')) {
            return $path;
        }

        if (str_starts_with($path, 'storage/')) {
            return asset($path);
        }

        return asset('storage/'.$path);
    }
}
