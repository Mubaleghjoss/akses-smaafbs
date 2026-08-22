<?php

namespace App\Filament\Pages\Assessment;

use App\Enums\Assessment\AssessmentType;

class AsasHomeroomRecap extends AssessmentHomeroomRecapPage
{
    protected static AssessmentType $assessmentType = AssessmentType::ASAS;

    protected static ?string $navigationLabel = 'Rekap Wali Kelas';

    protected static ?string $slug = 'penilaian/asas/rekap-wali-kelas';

    protected static ?int $navigationSort = 3;
}
