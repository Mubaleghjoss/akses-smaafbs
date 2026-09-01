<?php

namespace App\Filament\Pages\Assessment;

use App\Enums\Assessment\AssessmentType;

class AsasHub extends AssessmentTypeHubPage
{
    protected static AssessmentType $assessmentType = AssessmentType::ASAS;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-book-open';

    protected static ?string $navigationLabel = 'ASAS';

    protected static ?string $slug = 'penilaian/asas';

    protected static ?int $navigationSort = 11;
}
