<?php

namespace Database\Factories\Assessment;

use App\Models\Assessment\AuditLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AuditLog> */
class AuditLogFactory extends Factory
{
    protected $model = AuditLog::class;

    public function definition(): array
    {
        return [
            'assessment_period_id' => null,
            'actor_id' => null,
            'event' => 'created',
            'subject_type' => 'assessment',
            'subject_id' => fake()->numberBetween(1, 100000),
            'old_values' => null,
            'new_values' => [],
            'reason' => null,
            'ip_address' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
        ];
    }
}
