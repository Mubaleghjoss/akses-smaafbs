<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PerpustakaanLiterasiAnswer extends Model
{
    use SoftDeletes;

    protected $table = 'perpustakaan_literasi_answers';

    protected $guarded = [];

    protected $casts = [
        'answer_payload' => 'array',
        'character_count' => 'integer',
        'score_earned' => 'integer',
        'score_possible' => 'integer',
        'is_correct' => 'boolean',
        'graded_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::deleting(function (self $answer): void {
            if ($answer->isForceDeleting()) {
                return;
            }

            PerpustakaanLiterasiSimilarityMatch::query()
                ->where(function ($query) use ($answer): void {
                    $query
                        ->where('later_answer_id', $answer->getKey())
                        ->orWhere('matched_answer_id', $answer->getKey());
                })
                ->get()
                ->each
                ->delete();
        });

        static::restoring(function (self $answer): void {
            PerpustakaanLiterasiSimilarityMatch::withTrashed()
                ->where(function ($query) use ($answer): void {
                    $query
                        ->where('later_answer_id', $answer->getKey())
                        ->orWhere('matched_answer_id', $answer->getKey());
                })
                ->get()
                ->each
                ->restore();
        });
    }

    public function response(): BelongsTo
    {
        return $this->belongsTo(PerpustakaanLiterasiResponse::class, 'response_id')->withTrashed();
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(PerpustakaanLiterasiQuestion::class, 'question_id')->withTrashed();
    }

    public function gradedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'graded_by');
    }
}
