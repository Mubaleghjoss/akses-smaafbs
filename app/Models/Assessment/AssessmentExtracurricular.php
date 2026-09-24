<?php

namespace App\Models\Assessment;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AssessmentExtracurricular extends Model
{
    protected $fillable = ['assessment_period_id', 'name', 'code', 'description', 'is_active', 'created_by'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(AssessmentPeriod::class, 'assessment_period_id');
    }

    public function teachers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'assessment_extracurricular_teachers', 'assessment_extracurricular_id', 'teacher_id')->withTimestamps();
    }

    public function participants(): HasMany
    {
        return $this->hasMany(AssessmentExtracurricularParticipant::class, 'assessment_extracurricular_id');
    }
}
