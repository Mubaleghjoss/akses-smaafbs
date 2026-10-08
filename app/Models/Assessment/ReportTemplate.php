<?php

namespace App\Models\Assessment;

use App\Enums\Assessment\AssessmentType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReportTemplate extends Model
{
    use HasFactory;

    protected $table = 'assessment_report_templates';

    protected $fillable = [
        'code',
        'type',
        'name',
        'version',
        'view_path',
        'settings',
        'is_active',
        'effective_from',
    ];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'settings' => 'array',
            'is_active' => 'boolean',
            'effective_from' => 'date',
        ];
    }

    /**
     * Legacy rows may contain a type outside the current enum. Keep those
     * records readable in the admin so they can be corrected instead of 500ing.
     */
    public function getTypeAttribute(mixed $value): AssessmentType|string|null
    {
        if ($value === null) {
            return null;
        }

        $type = strtolower(trim((string) $value));

        return AssessmentType::tryFrom($type) ?? $type;
    }

    public function setTypeAttribute(mixed $value): void
    {
        $this->attributes['type'] = $value instanceof AssessmentType
            ? $value->value
            : strtolower(trim((string) $value));
    }

    public function snapshots(): HasMany
    {
        return $this->hasMany(ReportSnapshot::class, 'assessment_report_template_id');
    }

    public function classArtifacts(): HasMany
    {
        return $this->hasMany(ClassReportArtifact::class, 'assessment_report_template_id');
    }

    public function generationRuns(): HasMany
    {
        return $this->hasMany(ReportGenerationRun::class, 'assessment_report_template_id');
    }
}
