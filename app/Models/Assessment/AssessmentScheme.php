<?php

namespace App\Models\Assessment;

use App\Models\Rombel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AssessmentScheme extends Model
{
    use HasFactory;

    protected $table = 'assessment_schemes';

    protected $fillable = [
        'assessment_period_id',
        'assessment_subject_id',
        'source_rombel_id',
        'assessment_period_rombel_id',
        'name',
        'rounding_precision',
        'minimum_score',
        'maximum_score',
        'settings',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'rounding_precision' => 'integer',
            'source_rombel_id' => 'integer',
            'minimum_score' => 'decimal:4',
            'maximum_score' => 'decimal:4',
            'settings' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(AssessmentPeriod::class, 'assessment_period_id');
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class, 'assessment_subject_id');
    }

    public function periodRombel(): BelongsTo
    {
        return $this->belongsTo(AssessmentPeriodRombel::class, 'assessment_period_rombel_id');
    }

    public function sourceRombel(): BelongsTo
    {
        return $this->belongsTo(Rombel::class, 'source_rombel_id');
    }

    public function components(): HasMany
    {
        return $this->hasMany(AssessmentComponent::class, 'assessment_scheme_id')
            ->orderBy('sort_order');
    }
}
