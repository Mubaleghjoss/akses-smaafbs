<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SurveiQuestion extends Model
{
    public const TYPE_SHORT_TEXT = 'short_text';

    public const TYPE_LONG_TEXT = 'long_text';

    public const TYPE_SINGLE_CHOICE = 'single_choice';

    public const TYPE_RATING = 'rating_1_5';

    protected $table = 'survei_questions';

    protected $guarded = [];

    protected $casts = [
        'is_required' => 'boolean',
        'options' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public static function typeOptions(): array
    {
        return [
            self::TYPE_SHORT_TEXT => 'Jawaban singkat',
            self::TYPE_LONG_TEXT => 'Paragraf',
            self::TYPE_SINGLE_CHOICE => 'Pilih satu',
            self::TYPE_RATING => 'Skala 1-5',
        ];
    }

    public static function typeLabel(?string $type): string
    {
        return self::typeOptions()[$type] ?? ($type ?: '-');
    }

    public function survei(): BelongsTo
    {
        return $this->belongsTo(Survei::class, 'survei_id');
    }

    public function normalizedOptions(): array
    {
        return collect($this->options ?? [])
            ->map(function (mixed $option): ?string {
                if (is_array($option)) {
                    return filled($option['label'] ?? null) ? trim((string) $option['label']) : null;
                }

                return filled($option) ? trim((string) $option) : null;
            })
            ->filter()
            ->values()
            ->all();
    }
}
