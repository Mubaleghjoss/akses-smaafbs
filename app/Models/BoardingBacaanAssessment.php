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

    public const CLASS_OPTIONS = [
        'A' => 'Kelas A',
        'B' => 'Kelas B',
        'C' => 'Kelas C',
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

    public static function classOptions(): array
    {
        return self::CLASS_OPTIONS;
    }

    public static function classLabel(?string $class): string
    {
        return self::classOptions()[$class ?? ''] ?? 'Kelas A / B / C';
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
