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
            Actions\Action::make('preview_template')
                ->label('Pratinjau Template')
                ->icon('heroicon-o-eye')
                ->color('info')
                ->url(fn (): ?string => $this->templatePreviewUrl())
                ->visible(fn (): bool => $this->templatePreviewUrl() !== null)
                ->openUrlInNewTab()
                ->tooltip('Menampilkan pengaturan yang sudah disimpan dengan data contoh; simpan perubahan terlebih dahulu.'),
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

    protected function templatePreviewUrl(): ?string
    {
        try {
            if (! isset($this->record)) {
                return null;
            }

            $routeKey = $this->record->getRouteKey();

            if ($routeKey === null || $routeKey === '') {
                return null;
            }

            return url('/admin/penilaian/pengaturan/template-rapor/'.rawurlencode((string) $routeKey).'/preview');
        } catch (\Throwable) {
            return null;
        }
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $freshRecord = $record->fresh();
        abort_unless($freshRecord && AssessmentReportTemplateResource::canEdit($freshRecord), 403);

        return parent::handleRecordUpdate($record, $data);
    }
}
