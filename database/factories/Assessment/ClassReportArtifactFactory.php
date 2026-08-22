<?php

namespace Database\Factories\Assessment;

use App\Enums\Assessment\ReportGenerationStatus;
use App\Models\Assessment\AssessmentPeriod;
use App\Models\Assessment\AssessmentPeriodRombel;
use App\Models\Assessment\ClassReportArtifact;
use App\Models\Assessment\ReportTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ClassReportArtifact> */
class ClassReportArtifactFactory extends Factory
{
    protected $model = ClassReportArtifact::class;

    public function definition(): array
    {
        return [
            'assessment_period_id' => AssessmentPeriod::factory(),
            'assessment_period_rombel_id' => AssessmentPeriodRombel::factory(),
            'assessment_report_template_id' => ReportTemplate::factory(),
            'revision' => 1,
            'generation_status' => ReportGenerationStatus::PENDING,
            'pdf_path' => null,
            'checksum' => null,
            'error_message' => null,
            'queued_at' => now(),
            'started_at' => null,
            'generated_at' => null,
            'generated_by' => null,
        ];
    }
}
