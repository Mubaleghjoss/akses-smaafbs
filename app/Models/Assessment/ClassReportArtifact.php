<?php

namespace App\Models\Assessment;

use App\Enums\Assessment\ReportGenerationStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class ClassReportArtifact extends Model
{
    use HasFactory;

    protected $table = 'assessment_class_report_artifacts';

    protected $fillable = [
        'assessment_period_id',
        'assessment_period_rombel_id',
        'assessment_report_template_id',
        'assessment_report_generation_run_id',
        'revision',
        'generation_status',
        'pdf_path',
        'checksum',
        'error_message',
        'queued_at',
        'started_at',
        'generated_at',
        'generated_by',
    ];

    protected function casts(): array
    {
        return [
            'revision' => 'integer',
            'generation_status' => ReportGenerationStatus::class,
            'queued_at' => 'datetime',
            'started_at' => 'datetime',
            'generated_at' => 'datetime',
            'generated_by' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (self $artifact): void {
            if ($artifact->isDirty([
                'assessment_period_id',
                'assessment_period_rombel_id',
                'assessment_report_template_id',
                'assessment_report_generation_run_id',
                'revision',
                'generated_by',
            ])) {
                throw new LogicException(
                    'Identitas PDF kelas bersifat immutable; buat revisi baru untuk melakukan perubahan.',
                );
            }
        });
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(AssessmentPeriod::class, 'assessment_period_id');
    }

    public function periodRombel(): BelongsTo
    {
        return $this->belongsTo(AssessmentPeriodRombel::class, 'assessment_period_rombel_id');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(ReportTemplate::class, 'assessment_report_template_id');
    }

    public function generationRun(): BelongsTo
    {
        return $this->belongsTo(ReportGenerationRun::class, 'assessment_report_generation_run_id');
    }

    public function generator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }
}
