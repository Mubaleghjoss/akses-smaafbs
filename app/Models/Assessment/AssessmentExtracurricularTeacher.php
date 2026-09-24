<?php

namespace App\Models\Assessment;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssessmentExtracurricularTeacher extends Model
{
    protected $fillable = ['assessment_extracurricular_id', 'teacher_id'];

    public function extracurricular(): BelongsTo
    {
        return $this->belongsTo(AssessmentExtracurricular::class, 'assessment_extracurricular_id');
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }
}
