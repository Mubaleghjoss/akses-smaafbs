<?php

namespace App\Models\Assessment;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AcademicYear extends Model
{
    use HasFactory;

    protected $table = 'assessment_academic_years';

    protected $fillable = ['code', 'name', 'starts_on', 'ends_on', 'is_active'];

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function semesters(): HasMany
    {
        return $this->hasMany(Semester::class, 'assessment_academic_year_id');
    }

    public function periods(): HasMany
    {
        return $this->hasMany(AssessmentPeriod::class, 'assessment_academic_year_id');
    }
}
