<?php

namespace App\Models\Assessment;

use App\Models\GuruTendik;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssessmentPeriodHomeroom extends Model
{
    use HasFactory;

    protected $table = 'assessment_period_homerooms';

    protected $fillable = [
        'assessment_period_id',
        'source_homeroom_assignment_id',
        'assessment_period_rombel_id',
        'teacher_id',
        'teacher_name_snapshot',
        'rombel_name_snapshot',
    ];

    protected function casts(): array
    {
        return ['teacher_id' => 'integer'];
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(AssessmentPeriod::class, 'assessment_period_id');
    }

    public function sourceHomeroomAssignment(): BelongsTo
    {
        return $this->belongsTo(HomeroomAssignment::class, 'source_homeroom_assignment_id');
    }

    public function periodRombel(): BelongsTo
    {
        return $this->belongsTo(AssessmentPeriodRombel::class, 'assessment_period_rombel_id');
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(GuruTendik::class, 'teacher_id');
    }
}
