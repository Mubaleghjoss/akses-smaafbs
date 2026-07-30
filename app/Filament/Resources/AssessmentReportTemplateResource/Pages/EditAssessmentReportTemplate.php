<?php

namespace App\Filament\Resources\AssessmentReportTemplateResource\Pages;

use App\Filament\Resources\AssessmentReportTemplateResource;
use App\Models\Assessment\ReportTemplate;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditAssessmentReportTemplate extends EditRecord
{
    protected static string $resource = AssessmentReportTemplateResource::class;

    protected ?bool $hasDatabaseTransactions = true;

    protected function beforeValidate(): void
    {
        $template = ReportTemplate::query()
            ->whereKey($this->record->getKey())
            ->lockForUpdate()
            ->firstOrFail();

        abort_unless(AssessmentReportTemplateResource::canEdit($template), 403);
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return AssessmentReportTemplateResource::validateTemplateData($data);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->visible(fn (): bool => AssessmentReportTemplateResource::canDelete($this->record))
                ->databaseTransaction()
                ->before(function (): void {
                    $template = ReportTemplate::query()
                        ->whereKey($this->record->getKey())
                        ->lockForUpdate()
                        ->firstOrFail();

                    abort_unless(AssessmentReportTemplateResource::canDelete($template), 403);
                }),
        ];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $freshRecord = $record->fresh();
        abort_unless($freshRecord && AssessmentReportTemplateResource::canEdit($freshRecord), 403);

        return parent::handleRecordUpdate($record, $data);
    }
}
