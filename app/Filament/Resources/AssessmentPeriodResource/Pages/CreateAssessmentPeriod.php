<?php

namespace App\Filament\Resources\AssessmentPeriodResource\Pages;

use App\Enums\Assessment\AssessmentPeriodStatus;
use App\Filament\Resources\AssessmentPeriodResource;
use App\Models\Assessment\Semester;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Validation\ValidationException;

class CreateAssessmentPeriod extends CreateRecord
{
    protected static string $resource = AssessmentPeriodResource::class;

    protected ?bool $hasDatabaseTransactions = true;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $semesterMatchesYear = Semester::query()
            ->whereKey((int) ($data['assessment_semester_id'] ?? 0))
            ->where('assessment_academic_year_id', (int) ($data['assessment_academic_year_id'] ?? 0))
            ->exists();

        if (! $semesterMatchesYear) {
            throw ValidationException::withMessages([
                'assessment_semester_id' => 'Semester tidak termasuk dalam tahun pelajaran yang dipilih.',
            ]);
        }

        $data['status'] = AssessmentPeriodStatus::DRAFT->value;
        $data['created_by'] = auth()->id();

        return $data;
    }
}
