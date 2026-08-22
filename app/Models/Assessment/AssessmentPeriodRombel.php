<?php

namespace App\Models\Assessment;

use App\Models\Rombel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class AssessmentPeriodRombel extends Model
{
    use HasFactory;

    protected $table = 'assessment_period_rombels';

    protected $fillable = [
        'assessment_period_id',
        'source_rombel_id',
        'rombel_name_snapshot',
        'grade_level',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'source_rombel_id' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(AssessmentPeriod::class, 'assessment_period_id');
    }

    public function sourceRombel(): BelongsTo
    {
        return $this->belongsTo(Rombel::class, 'source_rombel_id');
    }

    public function students(): HasMany
    {
        return $this->hasMany(AssessmentPeriodStudent::class, 'assessment_period_rombel_id');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(AssessmentPeriodAssignment::class, 'assessment_period_rombel_id');
    }

    public function homeroom(): HasOne
    {
        return $this->hasOne(AssessmentPeriodHomeroom::class, 'assessment_period_rombel_id');
    }

    public function classReportArtifacts(): HasMany
    {
        return $this->hasMany(ClassReportArtifact::class, 'assessment_period_rombel_id');
    }
}
