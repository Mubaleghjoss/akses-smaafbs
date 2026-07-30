<?php

namespace Database\Factories\Assessment;

use App\Enums\Assessment\AssessmentType;
use App\Models\Assessment\ReportTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ReportTemplate> */
class ReportTemplateFactory extends Factory
{
    protected $model = ReportTemplate::class;

    public function definition(): array
    {
        $type = fake()->randomElement(AssessmentType::cases());

        return [
            'code' => fake()->unique()->bothify('RAPOR-####'),
            'type' => $type,
            'name' => 'Rapor '.$type->label(),
            'version' => 1,
            'view_path' => 'assessment.reports.'.strtolower($type->value),
            'settings' => [],
            'is_active' => true,
            'effective_from' => now()->toDateString(),
        ];
    }
}
