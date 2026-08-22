<?php

namespace Database\Factories\Assessment;

use App\Models\Assessment\AssessmentPeriod;
use App\Models\Assessment\AssessmentPeriodStudent;
use App\Models\Assessment\HomeroomReport;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<HomeroomReport> */
class HomeroomReportFactory extends Factory
{
    protected $model = HomeroomReport::class;

    public function definition(): array
    {
        return [
            'assessment_period_id' => AssessmentPeriod::factory(),
            'assessment_period_student_id' => AssessmentPeriodStudent::factory(),
            'sick_days' => 0,
            'permission_days' => 0,
            'absent_days' => 0,
            'extracurricular_data' => [],
            'achievement_data' => [],
            'homeroom_note' => null,
            'promotion_status' => null,
            'updated_by' => null,
        ];
    }
}
