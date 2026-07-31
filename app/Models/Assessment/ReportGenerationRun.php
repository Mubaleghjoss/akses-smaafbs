<?php

namespace App\Models\Assessment;

use App\Enums\Assessment\ReportRunStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReportGenerationRun extends Model
{
    protected $table = 'assessment_report_generation_runs';

    protected $fillable = [
        'assessment_period_id',
        'assessment_report_template_id',
        'revision',
        'status',
        'total_students',
        'completed_students',
        'total_classes',
        'completed_classes',
        'requested_by',
        'started_at',
        'completed_at',
        'cancel_requested_at',
        'cancelled_at',
        'cancelled_by',
        'cancellation_reason',
    ];

    protected function casts(): array
    {
        return [
            'revision' => 'integer',
            'status' => ReportRunStatus::class,
            'total_students' => 'integer',
            'completed_students' => 'integer',
            'total_classes' => 'integer',
            'completed_classes' => 'integer',
            'requested_by' => 'integer',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancel_requested_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'cancelled_by' => 'integer',
        ];
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(AssessmentPeriod::class, 'assessment_period_id');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(ReportTemplate::class, 'assessment_report_template_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function snapshots(): HasMany
    {
        return $this->hasMany(ReportSnapshot::class, 'assessment_report_generation_run_id');
    }

    public function classArtifacts(): HasMany
    {
        return $this->hasMany(ClassReportArtifact::class, 'assessment_report_generation_run_id');
    }
}
