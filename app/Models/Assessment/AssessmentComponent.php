<?php

namespace App\Models\Assessment;

use App\Enums\Assessment\ScoreSource;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AssessmentComponent extends Model
{
    use HasFactory;

    protected $table = 'assessment_components';

    protected $fillable = [
        'assessment_scheme_id',
        'code',
        'name',
        'domain',
        'weight',
        'maximum_score',
        'is_required',
        'sort_order',
        'score_source',
        'settings',
    ];

    protected function casts(): array
    {
        return [
            'weight' => 'decimal:4',
            'maximum_score' => 'decimal:4',
            'is_required' => 'boolean',
            'sort_order' => 'integer',
            'score_source' => ScoreSource::class,
            'settings' => 'array',
        ];
    }

    public function scheme(): BelongsTo
    {
        return $this->belongsTo(AssessmentScheme::class, 'assessment_scheme_id');
    }

    public function scores(): HasMany
    {
        return $this->hasMany(AssessmentScore::class, 'assessment_component_id');
    }
}
