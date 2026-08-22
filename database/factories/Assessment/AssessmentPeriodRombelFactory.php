<?php

namespace Database\Factories\Assessment;

use App\Models\Assessment\AssessmentPeriod;
use App\Models\Assessment\AssessmentPeriodRombel;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AssessmentPeriodRombel> */
class AssessmentPeriodRombelFactory extends Factory
{
    protected $model = AssessmentPeriodRombel::class;

    public function definition(): array
    {
        return [
            'assessment_period_id' => AssessmentPeriod::factory(),
            'source_rombel_id' => fake()->unique()->numberBetween(1, 100000),
            'rombel_name_snapshot' => fake()->randomElement(['X 1', 'XI 1', 'XII 1']),
            'grade_level' => fake()->randomElement(['X', 'XI', 'XII']),
            'is_active' => true,
        ];
    }
}
