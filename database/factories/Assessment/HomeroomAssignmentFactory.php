<?php

namespace Database\Factories\Assessment;

use App\Models\Assessment\HomeroomAssignment;
use App\Models\Assessment\Semester;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<HomeroomAssignment> */
class HomeroomAssignmentFactory extends Factory
{
    protected $model = HomeroomAssignment::class;

    public function definition(): array
    {
        return [
            'assessment_semester_id' => Semester::factory(),
            'teacher_id' => fake()->numberBetween(1, 100000),
            'rombel_id' => fake()->unique()->numberBetween(1, 100000),
            'teacher_name_snapshot' => fake()->name(),
            'rombel_name_snapshot' => fake()->randomElement(['X 1', 'XI 1', 'XII 1']),
            'is_active' => true,
        ];
    }
}
