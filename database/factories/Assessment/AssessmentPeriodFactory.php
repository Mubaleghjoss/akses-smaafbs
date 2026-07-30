<?php

namespace Database\Factories\Assessment;

use App\Enums\Assessment\AssessmentPeriodStatus;
use App\Enums\Assessment\AssessmentType;
use App\Models\Assessment\AcademicYear;
use App\Models\Assessment\AssessmentPeriod;
use App\Models\Assessment\Semester;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AssessmentPeriod> */
class AssessmentPeriodFactory extends Factory
{
    protected $model = AssessmentPeriod::class;

    public function definition(): array
    {
        $type = fake()->randomElement(AssessmentType::cases());

        return [
            'assessment_academic_year_id' => AcademicYear::factory(),
            'assessment_semester_id' => Semester::factory(),
            'code' => fake()->unique()->bothify(strtoupper($type->value).'-####-??'),
            'name' => $type->label().' '.fake()->year(),
            'type' => $type,
            'status' => AssessmentPeriodStatus::DRAFT,
            'entry_start_at' => null,
            'entry_end_at' => null,
            'report_date' => null,
            'settings' => [],
            'created_by' => null,
        ];
    }

    public function asts(): static
    {
        return $this->state(fn (): array => ['type' => AssessmentType::ASTS]);
    }

    public function asas(): static
    {
        return $this->state(fn (): array => ['type' => AssessmentType::ASAS]);
    }
}
