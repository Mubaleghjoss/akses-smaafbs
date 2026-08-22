<?php

namespace Database\Factories\Assessment;

use App\Models\Assessment\AssessmentPeriod;
use App\Models\Assessment\AssessmentPeriodRombel;
use App\Models\Assessment\AssessmentPeriodStudent;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AssessmentPeriodStudent> */
class AssessmentPeriodStudentFactory extends Factory
{
    protected $model = AssessmentPeriodStudent::class;

    public function definition(): array
    {
        return [
            'assessment_period_id' => AssessmentPeriod::factory(),
            'assessment_period_rombel_id' => AssessmentPeriodRombel::factory(),
            'student_id' => fake()->unique()->numberBetween(1, 1000000),
            'nis_snapshot' => fake()->unique()->numerify('########'),
            'nisn_snapshot' => fake()->unique()->numerify('##########'),
            'student_name_snapshot' => fake()->name(),
            'gender_snapshot' => fake()->randomElement(['L', 'P']),
            'rombel_name_snapshot' => fake()->randomElement(['X 1', 'XI 1', 'XII 1']),
            'is_active' => true,
        ];
    }
}
