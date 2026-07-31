<?php

namespace App\Models\Assessment;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HomeroomReport extends Model
{
    use HasFactory;

    protected $table = 'assessment_homeroom_reports';

    protected $fillable = [
        'assessment_period_id',
        'assessment_period_student_id',
        'sick_days',
        'permission_days',
        'absent_days',
        'spiritual_predicate',
        'spiritual_description',
        'social_predicate',
        'social_description',
        'extracurricular_data',
        'achievement_data',
        'homeroom_note',
        'promotion_status',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'sick_days' => 'integer',
            'permission_days' => 'integer',
            'absent_days' => 'integer',
            'extracurricular_data' => 'array',
            'achievement_data' => 'array',
            'updated_by' => 'integer',
        ];
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(AssessmentPeriod::class, 'assessment_period_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(AssessmentPeriodStudent::class, 'assessment_period_student_id');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
