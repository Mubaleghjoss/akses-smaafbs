<?php

namespace App\Filament\Pages\Assessment;

use App\Enums\Assessment\AssessmentType;

class AsatHub extends AssessmentTypeHubPage
{
    protected static AssessmentType $assessmentType = AssessmentType::ASAT;

    protected static ?string $navigationLabel = 'ASAT';

    protected static ?string $slug = 'penilaian/asat';

    protected static ?int $navigationSort = 4;
}
