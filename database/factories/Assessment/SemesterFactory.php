<?php

namespace Database\Factories\Assessment;

use App\Models\Assessment\AcademicYear;
use App\Models\Assessment\Semester;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Semester> */
class SemesterFactory extends Factory
{
    protected $model = Semester::class;

    public function definition(): array
    {
        $code = fake()->randomElement(['ganjil', 'genap']).'-'.fake()->unique()->numberBetween(100, 999999);

        return [
            'assessment_academic_year_id' => AcademicYear::factory(),
            'code' => $code,
            'name' => str($code)->before('-')->title()->toString(),
            'starts_on' => now()->startOfYear(),
            'ends_on' => now()->endOfYear(),
            'is_active' => true,
        ];
    }
}
