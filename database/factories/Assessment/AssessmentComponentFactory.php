<?php

namespace Database\Factories\Assessment;

use App\Enums\Assessment\ScoreSource;
use App\Models\Assessment\AssessmentComponent;
use App\Models\Assessment\AssessmentScheme;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AssessmentComponent> */
class AssessmentComponentFactory extends Factory
{
    protected $model = AssessmentComponent::class;

    public function definition(): array
    {
        return [
            'assessment_scheme_id' => AssessmentScheme::factory(),
            'code' => fake()->unique()->bothify('K-####'),
            'name' => fake()->words(2, true),
            'domain' => null,
            'weight' => 100,
            'maximum_score' => 100,
            'is_required' => true,
            'sort_order' => 0,
            'score_source' => ScoreSource::MANUAL,
            'settings' => [],
        ];
    }
}
