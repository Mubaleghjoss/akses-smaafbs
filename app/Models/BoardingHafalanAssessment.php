<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BoardingHafalanAssessment extends Model
{
    protected $table = 'boarding_hafalan_assessments';

    protected $guarded = [];

    protected $casts = [
        'assessed_at' => 'date',
        'score' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saved(function (self $assessment): void {
            $assessment->pencapaian?->syncFromHafalanAssessments();
        });

        static::deleted(function (self $assessment): void {
            $assessment->pencapaian?->syncFromHafalanAssessments();
        });
    }

    public function point(): BelongsTo
    {
        return $this->belongsTo(BoardingHafalanPoint::class, 'boarding_hafalan_point_id');
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
