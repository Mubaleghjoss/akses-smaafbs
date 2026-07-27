<?php

namespace App\Models;

use App\Support\Media\PublicImageOptimizer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

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
        static::saving(function (self $question): void {
            if (! $question->isDirty('image_path') || blank($question->image_path)) {
                return;
            }

            try {
                $question->image_path = app(PublicImageOptimizer::class)
                    ->optimizeUploadedPath((string) $question->image_path, 'question');
            } catch (RuntimeException $exception) {
                throw ValidationException::withMessages([
                    'image_path' => 'Gambar pertanyaan gagal dioptimalkan: '.$exception->getMessage(),
                ]);
            }
        });

        static::deleting(function (self $question): void {
            if ($question->isForceDeleting()) {
                app(PublicImageOptimizer::class)->removeAll($question->image_path);

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

        return app(PublicImageOptimizer::class)->url($path);
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
