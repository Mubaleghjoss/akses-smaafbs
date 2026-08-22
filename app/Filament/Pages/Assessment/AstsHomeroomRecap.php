<?php

namespace App\Filament\Pages\Assessment;

use App\Enums\Assessment\AssessmentType;

class AstsHomeroomRecap extends AssessmentHomeroomRecapPage
{
    protected static AssessmentType $assessmentType = AssessmentType::ASTS;

    protected static ?string $navigationLabel = 'Rekap Wali Kelas';

    protected static ?string $slug = 'penilaian/asts/rekap-wali-kelas';

    protected static ?int $navigationSort = 3;
}
