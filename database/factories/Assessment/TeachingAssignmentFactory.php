<?php

namespace Database\Factories\Assessment;

use App\Models\Assessment\Semester;
use App\Models\Assessment\Subject;
use App\Models\Assessment\TeachingAssignment;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<TeachingAssignment> */
class TeachingAssignmentFactory extends Factory
{
    protected $model = TeachingAssignment::class;

    public function definition(): array
    {
        return [
            'assessment_semester_id' => Semester::factory(),
            'assessment_subject_id' => Subject::factory(),
            'teacher_id' => fake()->numberBetween(1, 100000),
            'rombel_id' => fake()->numberBetween(1, 100000),
            'teacher_name_snapshot' => fake()->name(),
            'subject_name_snapshot' => fake()->words(2, true),
            'rombel_name_snapshot' => fake()->randomElement(['X 1', 'XI 1', 'XII 1']),
            'is_active' => true,
        ];
    }
}
