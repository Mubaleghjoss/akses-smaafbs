<?php

namespace Database\Factories\Assessment;

use App\Models\Assessment\ReportShareLink;
use App\Models\Assessment\ReportSnapshot;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<ReportShareLink> */
class ReportShareLinkFactory extends Factory
{
    protected $model = ReportShareLink::class;

    public function definition(): array
    {
        return [
            'assessment_report_snapshot_id' => ReportSnapshot::factory(),
            'token_hash' => hash('sha256', Str::random(64)),
            'expires_at' => now()->addDay(),
            'revoked_at' => null,
            'created_by' => null,
            'last_accessed_at' => null,
            'download_count' => 0,
        ];
    }
}
