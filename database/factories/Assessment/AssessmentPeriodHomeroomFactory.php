<?php

namespace Database\Factories\Assessment;

use App\Models\Assessment\AssessmentPeriod;
use App\Models\Assessment\AssessmentPeriodHomeroom;
use App\Models\Assessment\AssessmentPeriodRombel;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AssessmentPeriodHomeroom> */
class AssessmentPeriodHomeroomFactory extends Factory
{
    protected $model = AssessmentPeriodHomeroom::class;

    public function definition(): array
    {
        return [
            'assessment_period_id' => AssessmentPeriod::factory(),
            'source_homeroom_assignment_id' => null,
            'assessment_period_rombel_id' => AssessmentPeriodRombel::factory(),
            'teacher_id' => fake()->numberBetween(1, 100000),
            'teacher_name_snapshot' => fake()->name(),
            'rombel_name_snapshot' => fake()->randomElement(['X 1', 'XI 1', 'XII 1']),
        ];
    }
}
