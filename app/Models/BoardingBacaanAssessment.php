<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BoardingBacaanAssessment extends Model
{
    public const GRADE_OPTIONS = [
        'A' => 'A - Baik',
        'B' => 'B - Sedang',
        'C' => 'C - Cukup',
        'D' => 'D - Kurang',
    ];

    protected $table = 'boarding_bacaan_assessments';

    protected $guarded = [];

    protected $casts = [
        'boarding_pencapaian_id' => 'integer',
        'assessed_at' => 'date',
        'reviewer_user_id' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $assessment): void {
            if (filled($assessment->reviewer_user_id)) {
                $assessment->reviewer_name = null;

                return;
            }

            $assessment->reviewer_name = filled($assessment->reviewer_name)
                ? trim((string) $assessment->reviewer_name)
                : null;
        });

        static::saved(function (self $assessment): void {
            $assessment->pencapaian?->syncLatestBoardingModuleDate();
        });

        static::deleted(function (self $assessment): void {
            $assessment->pencapaian?->syncLatestBoardingModuleDate();
        });
    }

    public static function gradeOptions(): array
    {
        return self::GRADE_OPTIONS;
    }

    public static function gradeLabel(?string $grade): string
    {
        return self::gradeOptions()[$grade ?? ''] ?? ($grade ?: '-');
    }

    public function pencapaian(): BelongsTo
    {
        return $this->belongsTo(BoardingPencapaian::class, 'boarding_pencapaian_id');
    }

    public function reviewerUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_user_id');
    }
}
