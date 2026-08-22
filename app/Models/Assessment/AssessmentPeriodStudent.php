<?php

namespace App\Models\Assessment;

use App\Models\DataSiswa;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class AssessmentPeriodStudent extends Model
{
    use HasFactory;

    protected $table = 'assessment_period_students';

    protected $fillable = [
        'assessment_period_id',
        'assessment_period_rombel_id',
        'student_id',
        'nis_snapshot',
        'nisn_snapshot',
        'student_name_snapshot',
        'gender_snapshot',
        'rombel_name_snapshot',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'student_id' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(AssessmentPeriod::class, 'assessment_period_id');
    }

    public function periodRombel(): BelongsTo
    {
        return $this->belongsTo(AssessmentPeriodRombel::class, 'assessment_period_rombel_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(DataSiswa::class, 'student_id');
    }

    public function scores(): HasMany
    {
        return $this->hasMany(AssessmentScore::class, 'assessment_period_student_id');
    }

    public function results(): HasMany
    {
        return $this->hasMany(StudentSubjectResult::class, 'assessment_period_student_id');
    }

    public function homeroomReport(): HasOne
    {
        return $this->hasOne(HomeroomReport::class, 'assessment_period_student_id');
    }

    public function reportSnapshots(): HasMany
    {
        return $this->hasMany(ReportSnapshot::class, 'assessment_period_student_id');
    }
}
