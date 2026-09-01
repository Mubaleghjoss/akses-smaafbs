<?php

namespace App\Filament\Pages\Assessment;

use App\Enums\Assessment\AssessmentType;

class AsatHub extends AssessmentTypeHubPage
{
    protected static AssessmentType $assessmentType = AssessmentType::ASAT;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-academic-cap';

    protected static ?string $navigationLabel = 'ASAT';

    protected static ?string $slug = 'penilaian/asat';

    protected static ?int $navigationSort = 12;
}
