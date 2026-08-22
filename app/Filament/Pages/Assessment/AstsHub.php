<?php

namespace App\Filament\Pages\Assessment;

use App\Enums\Assessment\AssessmentType;

class AstsHub extends AssessmentTypeHubPage
{
    protected static AssessmentType $assessmentType = AssessmentType::ASTS;

    protected static ?string $navigationLabel = 'ASTS';

    protected static ?string $slug = 'penilaian/asts';

    protected static ?int $navigationSort = 2;
}
