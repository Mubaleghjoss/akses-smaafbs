<?php

namespace App\Models\Assessment;

use App\Enums\Assessment\AssessmentPeriodStatus;
use App\Enums\Assessment\AssessmentType;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AssessmentPeriod extends Model
{
    use HasFactory;

    protected $table = 'assessment_periods';

    protected $fillable = [
        'assessment_academic_year_id',
        'assessment_semester_id',
        'code',
        'name',
        'type',
        'status',
        'entry_start_at',
        'entry_end_at',
        'report_date',
        'settings',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'type' => AssessmentType::class,
            'status' => AssessmentPeriodStatus::class,
            'entry_start_at' => 'datetime',
            'entry_end_at' => 'datetime',
            'report_date' => 'date',
            'settings' => 'array',
            'created_by' => 'integer',
        ];
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class, 'assessment_academic_year_id');
    }

    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class, 'assessment_semester_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function periodRombels(): HasMany
    {
        return $this->hasMany(AssessmentPeriodRombel::class, 'assessment_period_id');
    }

    public function students(): HasMany
    {
        return $this->hasMany(AssessmentPeriodStudent::class, 'assessment_period_id');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(AssessmentPeriodAssignment::class, 'assessment_period_id');
    }

    public function homerooms(): HasMany
    {
        return $this->hasMany(AssessmentPeriodHomeroom::class, 'assessment_period_id');
    }

    public function schemes(): HasMany
    {
        return $this->hasMany(AssessmentScheme::class, 'assessment_period_id');
    }

    public function results(): HasMany
    {
        return $this->hasMany(StudentSubjectResult::class, 'assessment_period_id');
    }

    public function homeroomReports(): HasMany
    {
        return $this->hasMany(HomeroomReport::class, 'assessment_period_id');
    }

    public function reportSnapshots(): HasMany
    {
        return $this->hasMany(ReportSnapshot::class, 'assessment_period_id');
    }

    public function classReportArtifacts(): HasMany
    {
        return $this->hasMany(ClassReportArtifact::class, 'assessment_period_id');
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class, 'assessment_period_id');
    }
}
