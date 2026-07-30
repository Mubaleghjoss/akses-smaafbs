<?php

namespace App\Filament\Resources\AssessmentSchemeResource\Pages;

use App\Enums\Assessment\AssessmentPeriodStatus;
use App\Filament\Resources\AssessmentSchemeResource;
use App\Models\Assessment\AssessmentPeriod;
use App\Models\Assessment\AssessmentScheme;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class EditAssessmentScheme extends EditRecord
{
    protected static string $resource = AssessmentSchemeResource::class;

    protected ?bool $hasDatabaseTransactions = true;

    protected function beforeValidate(): void
    {
        $newPeriodId = (int) data_get($this->form->getRawState(), 'assessment_period_id');
        $periodIds = collect([
            (int) $this->record->assessment_period_id,
            $newPeriodId,
        ])->filter()->unique()->sort()->values();
        $periods = AssessmentPeriod::query()
            ->whereIn('id', $periodIds)
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        foreach ($periodIds as $periodId) {
            if ($periods->get($periodId)?->status !== AssessmentPeriodStatus::DRAFT) {
                throw ValidationException::withMessages([
                    'data.assessment_period_id' => 'Skema hanya dapat diubah ketika periode lama dan periode tujuan masih berstatus Draf.',
                ]);
            }
        }

        $lockedScheme = AssessmentScheme::query()
            ->whereKey($this->record->getKey())
            ->lockForUpdate()
            ->firstOrFail();
        abort_unless(AssessmentSchemeResource::canEdit($lockedScheme), 403);
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return AssessmentSchemeResource::validateSchemeData(
            $data,
            (int) $this->record->getKey(),
        );
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $freshRecord = $record->fresh(['period']);
        abort_unless($freshRecord && AssessmentSchemeResource::canEdit($freshRecord), 403);

        return parent::handleRecordUpdate($record, $data);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->databaseTransaction()
                ->before(function (): void {
                    $period = AssessmentPeriod::query()
                        ->whereKey($this->record->assessment_period_id)
                        ->lockForUpdate()
                        ->firstOrFail();
                    $scheme = AssessmentScheme::query()
                        ->whereKey($this->record->getKey())
                        ->lockForUpdate()
                        ->firstOrFail();

                    abort_unless(
                        $period->status === AssessmentPeriodStatus::DRAFT
                            && AssessmentSchemeResource::canDelete($scheme),
                        403,
                    );
                }),
        ];
    }
}
