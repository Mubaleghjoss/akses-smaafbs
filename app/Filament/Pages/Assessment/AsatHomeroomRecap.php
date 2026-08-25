<?php

namespace App\Filament\Pages\Assessment;

use App\Enums\Assessment\AssessmentType;

class AsatHomeroomRecap extends AssessmentHomeroomRecapPage
{
    protected static AssessmentType $assessmentType = AssessmentType::ASAT;

    protected static ?string $navigationLabel = 'Rekap Wali Kelas';

    protected static ?string $slug = 'penilaian/asat/rekap-wali-kelas';

    protected static ?int $navigationSort = 3;
}
