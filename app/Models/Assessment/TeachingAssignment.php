<?php

namespace App\Models\Assessment;

use App\Models\GuruTendik;
use App\Models\Rombel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TeachingAssignment extends Model
{
    use HasFactory;

    protected $table = 'assessment_teaching_assignments';

    protected $fillable = [
        'assessment_semester_id',
        'assessment_subject_id',
        'teacher_id',
        'rombel_id',
        'teacher_name_snapshot',
        'subject_name_snapshot',
        'rombel_name_snapshot',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'teacher_id' => 'integer',
            'rombel_id' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class, 'assessment_semester_id');
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class, 'assessment_subject_id');
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(GuruTendik::class, 'teacher_id');
    }

    public function rombel(): BelongsTo
    {
        return $this->belongsTo(Rombel::class, 'rombel_id');
    }

    public function periodAssignments(): HasMany
    {
        return $this->hasMany(AssessmentPeriodAssignment::class, 'source_teaching_assignment_id');
    }
}
