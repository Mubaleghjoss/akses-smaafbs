<?php

namespace App\Filament\Resources\AssessmentPeriodResource\Pages;

use App\Enums\Assessment\AssessmentPeriodStatus;
use App\Filament\Resources\AssessmentPeriodResource;
use App\Models\Assessment\AssessmentPeriod;
use App\Models\Assessment\Semester;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class EditAssessmentPeriod extends EditRecord
{
    protected static string $resource = AssessmentPeriodResource::class;

    protected ?bool $hasDatabaseTransactions = true;

    protected function beforeValidate(): void
    {
        $period = AssessmentPeriod::query()
            ->whereKey($this->record->getKey())
            ->lockForUpdate()
            ->firstOrFail();

        if ($period->status !== AssessmentPeriodStatus::DRAFT
            && ! AssessmentPeriodResource::canEditInputDeadline($period)) {
            throw ValidationException::withMessages([
                'data.entry_end_at' => 'Batas input hanya dapat diubah saat periode berstatus Dibuka.',
            ]);
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->visible(fn (): bool => AssessmentPeriodResource::canDelete($this->record))
                ->databaseTransaction()
                ->before(function (): void {
                    $period = AssessmentPeriod::query()
                        ->whereKey($this->record->getKey())
                        ->lockForUpdate()
                        ->firstOrFail();

                    abort_unless(
                        $period->status === AssessmentPeriodStatus::DRAFT
                            && AssessmentPeriodResource::canDelete($period),
                        403,
                    );
                }),
        ];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        abort_unless(AssessmentPeriodResource::canEdit($record), 403);

        if ($record->status !== AssessmentPeriodStatus::DRAFT) {
            abort_unless(AssessmentPeriodResource::canEditInputDeadline($record), 403);

            // A period snapshot is immutable after opening; only its score-entry deadline may move.
            return parent::handleRecordUpdate($record, [
                'entry_end_at' => $data['entry_end_at'] ?? $record->entry_end_at,
            ]);
        }

        unset($data['status'], $data['created_by']);

        $academicYearId = (int) ($data['assessment_academic_year_id'] ?? $record->assessment_academic_year_id);
        $semesterId = (int) ($data['assessment_semester_id'] ?? $record->assessment_semester_id);
        $semesterMatchesYear = Semester::query()
            ->whereKey($semesterId)
            ->where('assessment_academic_year_id', $academicYearId)
            ->exists();

        if (! $semesterMatchesYear) {
            throw ValidationException::withMessages([
                'assessment_semester_id' => 'Semester tidak termasuk dalam tahun pelajaran yang dipilih.',
            ]);
        }

        return parent::handleRecordUpdate($record, $data);
    }
}
