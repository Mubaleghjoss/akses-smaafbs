<?php

namespace App\Models\Assessment;

use App\Enums\Assessment\ReportGenerationStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

class ReportSnapshot extends Model
{
    use HasFactory;

    protected $table = 'assessment_report_snapshots';

    protected $fillable = [
        'assessment_period_id',
        'assessment_period_student_id',
        'assessment_report_template_id',
        'assessment_report_generation_run_id',
        'revision',
        'template_version',
        'snapshot_data',
        'snapshot_checksum',
        'generation_status',
        'delivery_mode',
        'pdf_path',
        'checksum',
        'error_message',
        'generated_at',
        'generated_by',
    ];

    protected function casts(): array
    {
        return [
            'revision' => 'integer',
            'template_version' => 'integer',
            'snapshot_data' => 'array',
            'generation_status' => ReportGenerationStatus::class,
            'generated_at' => 'datetime',
            'generated_by' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (self $snapshot): void {
            $immutableColumns = [
                'assessment_period_id',
                'assessment_period_student_id',
                'assessment_report_template_id',
                'assessment_report_generation_run_id',
                'revision',
                'template_version',
                'snapshot_data',
                'snapshot_checksum',
                'generated_by',
            ];

            if ($snapshot->isDirty($immutableColumns)) {
                throw new LogicException(
                    'Isi snapshot rapor bersifat immutable; buat revisi baru untuk melakukan perubahan.',
                );
            }
        });
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(AssessmentPeriod::class, 'assessment_period_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(AssessmentPeriodStudent::class, 'assessment_period_student_id');
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

    public function shareLinks(): HasMany
    {
        return $this->hasMany(ReportShareLink::class, 'assessment_report_snapshot_id');
    }
}
