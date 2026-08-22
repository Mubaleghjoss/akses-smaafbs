<?php

namespace App\Filament\Pages\Assessment;

use App\Enums\Assessment\AssessmentType;

class AstsReports extends AssessmentReportsPage
{
    protected static AssessmentType $assessmentType = AssessmentType::ASTS;

    protected static ?string $navigationLabel = 'Cetak Rapor ASTS';

    protected static ?string $slug = 'penilaian/asts/cetak-rapor';

    protected static ?int $navigationSort = 4;
}
