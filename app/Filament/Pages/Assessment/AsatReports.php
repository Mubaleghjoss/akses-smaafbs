<?php

namespace App\Filament\Pages\Assessment;

use App\Enums\Assessment\AssessmentType;

class AsatReports extends AssessmentReportsPage
{
    protected static AssessmentType $assessmentType = AssessmentType::ASAT;

    protected static ?string $navigationLabel = 'Cetak Rapor ASAT';

    protected static ?string $slug = 'penilaian/asat/cetak-rapor';

    protected static ?int $navigationSort = 4;
}
