<?php

namespace App\Models\Assessment;

use App\Enums\Assessment\ScoreSource;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssessmentScore extends Model
{
    use HasFactory;

    protected $table = 'assessment_scores';

    protected $fillable = [
        'assessment_period_assignment_id',
        'assessment_period_student_id',
        'assessment_component_id',
        'score',
        'notes',
        'source',
        'source_result_id',
        'source_score_snapshot',
        'entered_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'score' => 'decimal:4',
            'source' => ScoreSource::class,
            'source_score_snapshot' => 'decimal:4',
            'entered_by' => 'integer',
            'updated_by' => 'integer',
        ];
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(AssessmentPeriodAssignment::class, 'assessment_period_assignment_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(AssessmentPeriodStudent::class, 'assessment_period_student_id');
    }

    public function component(): BelongsTo
    {
        return $this->belongsTo(AssessmentComponent::class, 'assessment_component_id');
    }

    public function sourceResult(): BelongsTo
    {
        return $this->belongsTo(StudentSubjectResult::class, 'source_result_id');
    }

    public function enteredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'entered_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
