<?php

namespace App\Filament\Resources\AssessmentSchemeResource\Pages;

use App\Filament\Resources\AssessmentSchemeResource;
use App\Enums\Assessment\AssessmentPeriodStatus;
use App\Models\Assessment\AssessmentPeriod;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Validation\ValidationException;

class CreateAssessmentScheme extends CreateRecord
{
    protected static string $resource = AssessmentSchemeResource::class;

    protected ?bool $hasDatabaseTransactions = true;

    protected function beforeValidate(): void
    {
        $periodId = (int) data_get($this->form->getRawState(), 'assessment_period_id');
        $period = AssessmentPeriod::query()
            ->whereKey($periodId)
            ->lockForUpdate()
            ->first();

        if (! $period || $period->status !== AssessmentPeriodStatus::DRAFT) {
            throw ValidationException::withMessages([
                'data.assessment_period_id' => 'Skema hanya dapat dibuat pada periode berstatus Draf.',
            ]);
        }
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return AssessmentSchemeResource::validateSchemeData($data);
    }
}
