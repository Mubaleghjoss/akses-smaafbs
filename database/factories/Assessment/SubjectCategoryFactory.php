<?php

namespace Database\Factories\Assessment;

use App\Models\Assessment\SubjectCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SubjectCategory> */
class SubjectCategoryFactory extends Factory
{
    protected $model = SubjectCategory::class;

    public function definition(): array
    {
        $type = fake()->randomElement([SubjectCategory::TYPE_WAJIB, SubjectCategory::TYPE_PILIHAN]);

        return [
            'code' => strtoupper(fake()->unique()->lexify('KAT-????')),
            'name' => $type === SubjectCategory::TYPE_WAJIB ? 'Mapel Wajib' : 'Mapel Pilihan',
            'type' => $type,
            'sort_order' => fake()->numberBetween(1, 100),
            'description' => null,
            'is_active' => true,
        ];
    }
}
