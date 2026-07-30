<?php

namespace App\Models\Assessment;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StudentSubjectResult extends Model
{
    use HasFactory;

    protected $table = 'assessment_student_subject_results';

    protected $fillable = [
        'assessment_period_id',
        'assessment_period_student_id',
        'assessment_period_assignment_id',
        'final_score',
        'predicate',
        'description',
        'calculation_detail',
        'formula_version',
        'calculated_at',
    ];

    protected function casts(): array
    {
        return [
            'final_score' => 'decimal:4',
            'calculation_detail' => 'array',
            'calculated_at' => 'datetime',
        ];
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(AssessmentPeriod::class, 'assessment_period_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(AssessmentPeriodStudent::class, 'assessment_period_student_id');
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(AssessmentPeriodAssignment::class, 'assessment_period_assignment_id');
    }

    public function sourcedScores(): HasMany
    {
        return $this->hasMany(AssessmentScore::class, 'source_result_id');
    }
}
