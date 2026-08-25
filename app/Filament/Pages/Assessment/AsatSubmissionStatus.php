<?php

namespace App\Filament\Pages\Assessment;

use App\Enums\Assessment\AssessmentType;

class AsatSubmissionStatus extends AssessmentSubmissionStatusPage
{
    protected static AssessmentType $assessmentType = AssessmentType::ASAT;

    protected static ?string $navigationLabel = 'Status Pengumpulan';

    protected static ?string $slug = 'penilaian/asat/status-pengumpulan';

    protected static ?int $navigationSort = 2;
}
