<?php

namespace App\Models\Assessment;

use App\Enums\Assessment\AssignmentStatus;
use App\Models\GuruTendik;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AssessmentPeriodAssignment extends Model
{
    use HasFactory;

    protected $table = 'assessment_period_assignments';

    protected $fillable = [
        'assessment_period_id',
        'source_teaching_assignment_id',
        'assessment_period_rombel_id',
        'teacher_id',
        'assessment_subject_id',
        'teacher_name_snapshot',
        'subject_name_snapshot',
        'rombel_name_snapshot',
        'status',
        'lock_version',
        'submitted_at',
        'submitted_by',
        'verified_at',
        'verified_by',
        'returned_at',
        'returned_by',
        'returned_reason',
        'locked_at',
        'locked_by',
    ];

    protected function casts(): array
    {
        return [
            'teacher_id' => 'integer',
            'status' => AssignmentStatus::class,
            'lock_version' => 'integer',
            'submitted_at' => 'datetime',
            'submitted_by' => 'integer',
            'verified_at' => 'datetime',
            'verified_by' => 'integer',
            'returned_at' => 'datetime',
            'returned_by' => 'integer',
            'locked_at' => 'datetime',
            'locked_by' => 'integer',
        ];
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(AssessmentPeriod::class, 'assessment_period_id');
    }

    public function sourceTeachingAssignment(): BelongsTo
    {
        return $this->belongsTo(TeachingAssignment::class, 'source_teaching_assignment_id');
    }

    public function periodRombel(): BelongsTo
    {
        return $this->belongsTo(AssessmentPeriodRombel::class, 'assessment_period_rombel_id');
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(GuruTendik::class, 'teacher_id');
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class, 'assessment_subject_id');
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function returner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'returned_by');
    }

    public function locker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'locked_by');
    }

    public function scores(): HasMany
    {
        return $this->hasMany(AssessmentScore::class, 'assessment_period_assignment_id');
    }

    public function results(): HasMany
    {
        return $this->hasMany(StudentSubjectResult::class, 'assessment_period_assignment_id');
    }
}
