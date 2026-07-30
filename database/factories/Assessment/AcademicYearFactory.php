<?php

namespace Database\Factories\Assessment;

use App\Models\Assessment\AcademicYear;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AcademicYear> */
class AcademicYearFactory extends Factory
{
    protected $model = AcademicYear::class;

    public function definition(): array
    {
        $start = fake()->dateTimeBetween('-2 years', '+1 year');
        $endYear = ((int) $start->format('Y')) + 1;
        $code = $start->format('Y').$endYear;

        return [
            'code' => fake()->unique()->bothify($code.'-##'),
            'name' => $start->format('Y').'/'.$endYear,
            'starts_on' => $start->format('Y').'-07-01',
            'ends_on' => $endYear.'-06-30',
            'is_active' => true,
        ];
    }
}
