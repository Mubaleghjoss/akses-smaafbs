<?php

namespace App\Models\Assessment;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class AssessmentExtracurricularParticipant extends Model
{
    protected $fillable = ['assessment_extracurricular_id', 'assessment_period_student_id', 'assessment_period_rombel_id', 'source'];

    public function extracurricular(): BelongsTo
    {
        return $this->belongsTo(AssessmentExtracurricular::class, 'assessment_extracurricular_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(AssessmentPeriodStudent::class, 'assessment_period_student_id');
    }

    public function periodRombel(): BelongsTo
    {
        return $this->belongsTo(AssessmentPeriodRombel::class, 'assessment_period_rombel_id');
    }

    public function score(): HasOne
    {
        return $this->hasOne(AssessmentExtracurricularScore::class, 'assessment_extracurricular_participant_id');
    }
}
