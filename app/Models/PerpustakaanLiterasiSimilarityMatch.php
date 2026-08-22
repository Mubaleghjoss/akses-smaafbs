<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PerpustakaanLiterasiSimilarityMatch extends Model
{
    use SoftDeletes;

    public const REVIEW_SUSPECTED = 'suspected';

    public const REVIEW_CLEARED = 'cleared';

    public const REVIEW_CONFIRMED = 'confirmed';

    protected $table = 'perpustakaan_literasi_similarity_matches';

    protected $guarded = [];

    protected $casts = [
        'similarity_score' => 'float',
        'later_submitted_at' => 'datetime',
        'matched_submitted_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public static function reviewStatusOptions(): array
    {
        return [
            self::REVIEW_SUSPECTED => 'Belum ditinjau',
            self::REVIEW_CLEARED => 'Aman, bukan plagiasi',
            self::REVIEW_CONFIRMED => 'Konfirmasi plagiasi',
        ];
    }

    public static function reviewStatusLabel(?string $status): string
    {
        return static::reviewStatusOptions()[$status ?: self::REVIEW_SUSPECTED] ?? 'Belum ditinjau';
    }

    public static function reviewStatusColor(?string $status): string
    {
        return match ($status ?: self::REVIEW_SUSPECTED) {
            self::REVIEW_CONFIRMED => 'danger',
            self::REVIEW_CLEARED => 'success',
            default => 'warning',
        };
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(PerpustakaanLiterasiMaterial::class, 'material_id')->withTrashed();
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(PerpustakaanLiterasiQuestion::class, 'question_id')->withTrashed();
    }

    public function laterResponse(): BelongsTo
    {
        return $this->belongsTo(PerpustakaanLiterasiResponse::class, 'later_response_id')->withTrashed();
    }

    public function matchedResponse(): BelongsTo
    {
        return $this->belongsTo(PerpustakaanLiterasiResponse::class, 'matched_response_id')->withTrashed();
    }

    public function laterAnswer(): BelongsTo
    {
        return $this->belongsTo(PerpustakaanLiterasiAnswer::class, 'later_answer_id')->withTrashed();
    }

    public function matchedAnswer(): BelongsTo
    {
        return $this->belongsTo(PerpustakaanLiterasiAnswer::class, 'matched_answer_id')->withTrashed();
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
