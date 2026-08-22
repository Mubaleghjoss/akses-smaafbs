<?php

namespace App\Filament\Pages\Assessment;

use App\Enums\Assessment\AssessmentType;

class AstsInputScores extends AssessmentScoreEntryPage
{
    protected static AssessmentType $assessmentType = AssessmentType::ASTS;

    protected static ?string $navigationLabel = 'Input Nilai Saya';

    protected static ?string $slug = 'penilaian/asts/input-nilai';

    protected static ?int $navigationSort = 1;
}
