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

    public const TYPE_ESSAY = 'essay';

    public const TYPE_TRUE_FALSE = 'true_false';

    public const TYPE_MATCHING = 'matching';

    protected $table = 'perpustakaan_literasi_questions';

    protected $guarded = [];

    protected $casts = [
        'is_required' => 'boolean',
        'plagiarism_detection_enabled' => 'boolean',
        'speech_input_enabled' => 'boolean',
        'configuration' => 'array',
        'min_characters' => 'integer',
        'max_characters' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $question): void {
            $question->question_type = static::normalizeType($question->question_type);
            $question->configuration = $question->validatedConfiguration();

            if (! $question->isEssay()) {
                $question->speech_input_enabled = false;
                $question->plagiarism_detection_enabled = false;
                $question->answer_key = null;
            } else {
                $limits = static::adjustedCharacterLimits(
                    $question->answer_key,
                    $question->min_characters,
                    $question->max_characters,
                );
                $question->min_characters = $limits['min'];
                $question->max_characters = $limits['max'];
            }

            if ($question->exists && $question->isDirty('max_characters')) {
                $previousMax = (int) $question->getOriginal('max_characters');
                $newMax = max(1, (int) $question->max_characters);

                if ($newMax < $previousMax) {
                    $longestSavedAnswer = (int) $question->answers()
                        ->withTrashed()
                        ->max('character_count');

                    if ($longestSavedAnswer > $newMax) {
                        throw ValidationException::withMessages([
                            'max_characters' => sprintf(
                                'Batas tidak dapat diturunkan menjadi %s karakter karena ada jawaban tersimpan sepanjang %s karakter. Gunakan batas minimal %s atau lebih.',
                                number_format($newMax, 0, ',', '.'),
                                number_format($longestSavedAnswer, 0, ',', '.'),
                                number_format($longestSavedAnswer, 0, ',', '.'),
                            ),
                        ]);
                    }
                }
            }

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
        if (! $this->isEssay()) {
            return false;
        }

        if (! array_key_exists('plagiarism_detection_enabled', $this->attributes)) {
            return true;
        }

        return (bool) $this->attributes['plagiarism_detection_enabled'];
    }

    public function hasAnswerKey(): bool
    {
        return $this->isEssay() && filled($this->answerKey());
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

    public function minimumCharacters(): int
    {
        return static::adjustedCharacterLimits(
            $this->answer_key,
            $this->min_characters,
            $this->max_characters,
        )['min'];
    }

    public function maximumCharacters(): int
    {
        return static::adjustedCharacterLimits(
            $this->answer_key,
            $this->min_characters,
            $this->max_characters,
        )['max'];
    }

    /**
     * @return array{min:int,max:int,key_length:int,adjusted:bool}
     */
    public static function adjustedCharacterLimits(mixed $answerKey, mixed $minimum, mixed $maximum): array
    {
        $minimum = max(0, (int) ($minimum ?? 0));
        $maximum = max(1, (int) ($maximum ?: 1000));
        $key = trim((string) ($answerKey ?? ''));
        $keyLength = $key === '' ? 0 : mb_strlen($key);
        $originalMinimum = $minimum;
        $originalMaximum = $maximum;

        if ($keyLength > 0) {
            $minimum = min($minimum, $keyLength);
            $maximum = max($maximum, $keyLength);
        }

        $minimum = min($minimum, $maximum);

        return [
            'min' => $minimum,
            'max' => $maximum,
            'key_length' => $keyLength,
            'adjusted' => $minimum !== $originalMinimum || $maximum !== $originalMaximum,
        ];
    }

    public static function normalizeAnswerForComparison(string $value): string
    {
        return Str::of(strip_tags($value))
            ->lower()
            ->squish()
            ->toString();
    }

    public static function typeOptions(): array
    {
        return [
            self::TYPE_ESSAY => 'Esai / jawaban tertulis',
            self::TYPE_TRUE_FALSE => 'Tabel Benar / Salah',
            self::TYPE_MATCHING => 'Menjodohkan',
        ];
    }

    public static function typeLabel(?string $type): string
    {
        $type = static::normalizeType($type);

        return static::typeOptions()[$type];
    }

    public static function normalizeType(mixed $type): string
    {
        $type = trim((string) $type);

        return array_key_exists($type, static::typeOptions()) ? $type : self::TYPE_ESSAY;
    }

    public function isEssay(): bool
    {
        return static::normalizeType($this->question_type) === self::TYPE_ESSAY;
    }

    public function isTrueFalse(): bool
    {
        return static::normalizeType($this->question_type) === self::TYPE_TRUE_FALSE;
    }

    public function isMatching(): bool
    {
        return static::normalizeType($this->question_type) === self::TYPE_MATCHING;
    }

    public function objectiveItemCount(): int
    {
        return match (static::normalizeType($this->question_type)) {
            self::TYPE_TRUE_FALSE => count($this->trueFalseItems()),
            self::TYPE_MATCHING => count($this->matchingLeftItems()),
            default => 1,
        };
    }

    /**
     * @return array<int, array{id:string,statement:string,correct:bool}>
     */
    public function trueFalseItems(): array
    {
        return collect(data_get($this->configuration, 'items', []))
            ->filter(fn (mixed $item): bool => is_array($item))
            ->map(fn (array $item): array => [
                'id' => trim((string) ($item['id'] ?? '')),
                'statement' => trim((string) ($item['statement'] ?? '')),
                'correct' => filter_var($item['correct'] ?? false, FILTER_VALIDATE_BOOLEAN),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{id:string,label:string,correct_target_id:string}>
     */
    public function matchingLeftItems(): array
    {
        return collect(data_get($this->configuration, 'left', []))
            ->filter(fn (mixed $item): bool => is_array($item))
            ->map(fn (array $item): array => [
                'id' => trim((string) ($item['id'] ?? '')),
                'label' => trim((string) ($item['label'] ?? '')),
                'correct_target_id' => trim((string) ($item['correct_target_id'] ?? '')),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{id:string,label:string}>
     */
    public function matchingRightItems(): array
    {
        return collect(data_get($this->configuration, 'right', []))
            ->filter(fn (mixed $item): bool => is_array($item))
            ->map(fn (array $item): array => [
                'id' => trim((string) ($item['id'] ?? '')),
                'label' => trim((string) ($item['label'] ?? '')),
            ])
            ->values()
            ->all();
    }

    public function validatedConfiguration(): ?array
    {
        if ($this->isEssay()) {
            return null;
        }

        if ($this->isTrueFalse()) {
            $items = collect(data_get($this->configuration, 'items', []))
                ->filter(fn (mixed $item): bool => is_array($item))
                ->map(fn (array $item): array => [
                    'id' => trim((string) ($item['id'] ?? '')) ?: (string) Str::uuid(),
                    'statement' => trim((string) ($item['statement'] ?? '')),
                    'correct' => filter_var($item['correct'] ?? false, FILTER_VALIDATE_BOOLEAN),
                ])
                ->values();

            if ($items->count() < 2 || $items->contains(fn (array $item): bool => $item['statement'] === '')) {
                throw ValidationException::withMessages([
                    'configuration.items' => 'Soal Benar/Salah membutuhkan minimal dua pernyataan yang terisi.',
                ]);
            }

            if ($items->pluck('id')->duplicates()->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'configuration.items' => 'ID pernyataan Benar/Salah harus unik.',
                ]);
            }

            return ['version' => 1, 'items' => $items->all()];
        }

        $right = collect(data_get($this->configuration, 'right', []))
            ->filter(fn (mixed $item): bool => is_array($item))
            ->map(fn (array $item): array => [
                'id' => trim((string) ($item['id'] ?? '')) ?: (string) Str::uuid(),
                'label' => trim((string) ($item['label'] ?? '')),
            ])
            ->values();
        $rightIds = $right->pluck('id');
        $left = collect(data_get($this->configuration, 'left', []))
            ->filter(fn (mixed $item): bool => is_array($item))
            ->map(fn (array $item): array => [
                'id' => trim((string) ($item['id'] ?? '')) ?: (string) Str::uuid(),
                'label' => trim((string) ($item['label'] ?? '')),
                'correct_target_id' => trim((string) ($item['correct_target_id'] ?? '')),
            ])
            ->values();

        $invalid = $left->count() < 2
            || $right->count() < 2
            || $left->count() !== $right->count()
            || $left->contains(fn (array $item): bool => $item['label'] === '' || ! $rightIds->contains($item['correct_target_id']))
            || $right->contains(fn (array $item): bool => $item['label'] === '')
            || $left->pluck('id')->duplicates()->isNotEmpty()
            || $rightIds->duplicates()->isNotEmpty()
            || $left->pluck('correct_target_id')->duplicates()->isNotEmpty();

        if ($invalid) {
            throw ValidationException::withMessages([
                'configuration' => 'Soal Menjodohkan membutuhkan minimal dua pasangan lengkap, jumlah sisi kiri dan kanan yang sama, serta setiap tujuan hanya boleh menjadi satu kunci.',
            ]);
        }

        return [
            'version' => 1,
            'left' => $left->all(),
            'right' => $right->all(),
        ];
    }
}
