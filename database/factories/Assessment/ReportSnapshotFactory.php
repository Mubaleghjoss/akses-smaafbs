<?php

namespace Database\Factories\Assessment;

use App\Enums\Assessment\ReportGenerationStatus;
use App\Models\Assessment\AssessmentPeriod;
use App\Models\Assessment\AssessmentPeriodStudent;
use App\Models\Assessment\ReportSnapshot;
use App\Models\Assessment\ReportTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ReportSnapshot> */
class ReportSnapshotFactory extends Factory
{
    protected $model = ReportSnapshot::class;

    public function definition(): array
    {
        return [
            'assessment_period_id' => AssessmentPeriod::factory(),
            'assessment_period_student_id' => AssessmentPeriodStudent::factory(),
            'assessment_report_template_id' => ReportTemplate::factory(),
            'revision' => 1,
            'template_version' => 1,
            'snapshot_data' => ['student' => ['name' => fake()->name()]],
            'generation_status' => ReportGenerationStatus::PENDING,
            'pdf_path' => null,
            'checksum' => null,
            'error_message' => null,
            'generated_at' => null,
            'generated_by' => null,
        ];
    }
}
