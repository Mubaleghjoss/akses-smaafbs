<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class PerpustakaanLiterasiResponse extends Model
{
    use SoftDeletes;

    public const AI_STATUS_NOT_CHECKED = 'not_checked';

    protected $table = 'perpustakaan_literasi_responses';

    protected $guarded = [];

    protected $casts = [
        'submitted_at' => 'datetime',
        'last_edited_at' => 'datetime',
        'ai_score' => 'float',
        'ai_metadata' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $response): void {
            if (blank($response->edit_code)) {
                $response->edit_code = static::generateEditCode();
            }

            $response->ai_detection_status ??= self::AI_STATUS_NOT_CHECKED;
        });

        static::deleting(function (self $response): void {
            if ($response->isForceDeleting()) {
                return;
            }

            $response->answers()->get()->each->delete();
            $response->laterSimilarityMatches()->get()->each->delete();
            $response->matchedSimilarityMatches()->get()->each->delete();
        });

        static::restoring(function (self $response): void {
            $response->answers()->withTrashed()->get()->each->restore();
            $response->laterSimilarityMatches()->withTrashed()->get()->each->restore();
            $response->matchedSimilarityMatches()->withTrashed()->get()->each->restore();
        });
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(PerpustakaanLiterasiMaterial::class, 'material_id')->withTrashed();
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(DataSiswa::class, 'data_siswa_id');
    }

    public function answers(): HasMany
    {
        return $this->hasMany(PerpustakaanLiterasiAnswer::class, 'response_id');
    }

    public function laterSimilarityMatches(): HasMany
    {
        return $this->hasMany(PerpustakaanLiterasiSimilarityMatch::class, 'later_response_id');
    }

    public function matchedSimilarityMatches(): HasMany
    {
        return $this->hasMany(PerpustakaanLiterasiSimilarityMatch::class, 'matched_response_id');
    }

    public function shortEditCode(): string
    {
        return Str::afterLast((string) $this->edit_code, '-');
    }

    public function editUrl(): string
    {
        return route('library.literacy.edit', $this->shortEditCode());
    }

    public static function generateEditCode(): string
    {
        do {
            $shortCode = Str::upper(Str::random(6));
            $code = 'LHP-'.now()->format('ymd').'-'.$shortCode;
        } while (
            static::query()->where('edit_code', $code)->exists()
            || static::query()->where('edit_code', 'like', '%-'.$shortCode)->exists()
        );

        return $code;
    }
}
