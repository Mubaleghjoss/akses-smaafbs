<?php

namespace App\Filament\Pages\Assessment;

use App\Enums\Assessment\AssessmentType;

class AsasReports extends AssessmentReportsPage
{
    protected static AssessmentType $assessmentType = AssessmentType::ASAS;

    protected static ?string $navigationLabel = 'Cetak Rapor Semester';

    protected static ?string $slug = 'penilaian/asas/cetak-rapor';

    protected static ?int $navigationSort = 4;
}
