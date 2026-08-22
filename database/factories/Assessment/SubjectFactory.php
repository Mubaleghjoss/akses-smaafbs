<?php

namespace Database\Factories\Assessment;

use App\Models\Assessment\Subject;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Subject> */
class SubjectFactory extends Factory
{
    protected $model = Subject::class;

    public function definition(): array
    {
        return [
            'code' => fake()->unique()->bothify('MP-####'),
            'name' => fake()->unique()->words(2, true),
            'description' => null,
            'is_active' => true,
            'sort_order' => fake()->numberBetween(0, 100),
        ];
    }
}
