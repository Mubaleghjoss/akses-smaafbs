<?php

namespace App\Filament\Pages\Assessment;

use App\Enums\Assessment\AssessmentType;

class AsatInputScores extends AssessmentScoreEntryPage
{
    protected static AssessmentType $assessmentType = AssessmentType::ASAT;

    protected static ?string $navigationLabel = 'Input Nilai Saya';

    protected static ?string $slug = 'penilaian/asat/input-nilai';

    protected static ?int $navigationSort = 1;
}
