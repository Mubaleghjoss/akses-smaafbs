<?php

namespace App\Filament\Pages\Assessment;

use App\Enums\Assessment\AssessmentType;

class AsasSubmissionStatus extends AssessmentSubmissionStatusPage
{
    protected static AssessmentType $assessmentType = AssessmentType::ASAS;

    protected static ?string $navigationLabel = 'Status Pengumpulan';

    protected static ?string $slug = 'penilaian/asas/status-pengumpulan';

    protected static ?int $navigationSort = 2;
}
