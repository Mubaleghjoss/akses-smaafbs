<?php

namespace App\Filament\Pages\Assessment;

use App\Enums\Assessment\AssessmentType;

class AstsSubmissionStatus extends AssessmentSubmissionStatusPage
{
    protected static AssessmentType $assessmentType = AssessmentType::ASTS;

    protected static ?string $navigationLabel = 'Status Pengumpulan';

    protected static ?string $slug = 'penilaian/asts/status-pengumpulan';

    protected static ?int $navigationSort = 2;
}
