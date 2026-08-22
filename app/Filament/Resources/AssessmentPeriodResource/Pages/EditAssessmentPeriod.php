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

        if ($period->status !== AssessmentPeriodStatus::DRAFT) {
            throw ValidationException::withMessages([
                'data.status' => 'Periode hanya dapat diubah ketika masih berstatus Draf.',
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
