<?php

namespace App\Models\Assessment;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subject extends Model
{
    use HasFactory;

    protected $table = 'assessment_subjects';

    protected $fillable = ['code', 'name', 'description', 'is_active', 'sort_order'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function teachingAssignments(): HasMany
    {
        return $this->hasMany(TeachingAssignment::class, 'assessment_subject_id');
    }

    public function periodAssignments(): HasMany
    {
        return $this->hasMany(AssessmentPeriodAssignment::class, 'assessment_subject_id');
    }

    public function schemes(): HasMany
    {
        return $this->hasMany(AssessmentScheme::class, 'assessment_subject_id');
    }
}
