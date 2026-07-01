<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class PerpustakaanLiterasiQuestion extends Model
{
    use SoftDeletes;

    protected $table = 'perpustakaan_literasi_questions';

    protected $guarded = [];

    protected $casts = [
        'is_required' => 'boolean',
        'plagiarism_detection_enabled' => 'boolean',
        'min_characters' => 'integer',
        'max_characters' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::deleting(function (self $question): void {
            if ($question->isForceDeleting()) {
                return;
            }

            $question->answers()->get()->each->delete();
            PerpustakaanLiterasiSimilarityMatch::query()
                ->where('question_id', $question->getKey())
                ->get()
                ->each
                ->delete();
        });

        static::restoring(function (self $question): void {
            $question->answers()->withTrashed()->get()->each->restore();
            PerpustakaanLiterasiSimilarityMatch::withTrashed()
                ->where('question_id', $question->getKey())
                ->get()
                ->each
                ->restore();
        });
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(PerpustakaanLiterasiMaterial::class, 'material_id')->withTrashed();
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

    public function plagiarismDetectionEnabled(): bool
    {
        if (! array_key_exists('plagiarism_detection_enabled', $this->attributes)) {
            return true;
        }

        return (bool) $this->attributes['plagiarism_detection_enabled'];
    }

    public function hasAnswerKey(): bool
    {
        return filled($this->answerKey());
    }

    public function shouldAutoGradeByAnswerKey(): bool
    {
        return $this->hasAnswerKey();
    }

    public function matchesAnswerKey(string $answerText): bool
    {
        $answerKey = $this->answerKey();

        if ($answerKey === null) {
            return false;
        }

        return static::normalizeAnswerForComparison($answerText) === static::normalizeAnswerForComparison($answerKey);
    }

    public function answerKey(): ?string
    {
        $answerKey = trim((string) ($this->answer_key ?? ''));

        return $answerKey !== '' ? $answerKey : null;
    }

    public static function normalizeAnswerForComparison(string $value): string
    {
        return Str::of(strip_tags($value))
            ->lower()
            ->squish()
            ->toString();
    }
}
