<?php

namespace App\Filament\Pages\Assessment;

use App\Enums\Assessment\AssessmentType;

class AsasInputScores extends AssessmentScoreEntryPage
{
    protected static AssessmentType $assessmentType = AssessmentType::ASAS;

    protected static ?string $navigationLabel = 'Input Nilai Saya';

    protected static ?string $slug = 'penilaian/asas/input-nilai';

    protected static ?int $navigationSort = 1;
}
