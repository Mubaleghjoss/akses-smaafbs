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
            'type' => AssessmentType::class,
            'version' => 'integer',
            'settings' => 'array',
            'is_active' => 'boolean',
            'effective_from' => 'date',
        ];
    }

    public function snapshots(): HasMany
    {
        return $this->hasMany(ReportSnapshot::class, 'assessment_report_template_id');
    }

    public function classArtifacts(): HasMany
    {
        return $this->hasMany(ClassReportArtifact::class, 'assessment_report_template_id');
    }
}
