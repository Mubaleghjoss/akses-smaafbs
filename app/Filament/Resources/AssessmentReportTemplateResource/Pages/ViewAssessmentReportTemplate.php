<?php

namespace App\Filament\Resources\AssessmentReportTemplateResource\Pages;

use App\Actions\Assessment\SetPrimaryReportTemplateAction;
use App\Filament\Pages\Assessment\AsasReports;
use App\Filament\Pages\Assessment\AstsReports;
use App\Filament\Resources\AssessmentReportTemplateResource;
use App\Models\Assessment\ReportTemplate;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Gate;

class ViewAssessmentReportTemplate extends ViewRecord
{
    protected static string $resource = AssessmentReportTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('preview')
                ->label('Pratinjau dengan Data Periode')
                ->icon('heroicon-o-eye')
                ->color('gray')
                ->url(fn (): string => ($this->record->type->value === 'asas'
                    ? AsasReports::getUrl(['template' => $this->record->getKey()])
                    : AstsReports::getUrl(['template' => $this->record->getKey()]))),
            EditAction::make()
                ->visible(fn (): bool => AssessmentReportTemplateResource::canEdit($this->record)),
            Action::make('set_primary')
                ->label('Jadikan Template Utama')
                ->icon('heroicon-o-check-badge')
                ->color('success')
                ->visible(fn (): bool => ! $this->record->is_active
                    && AssessmentReportTemplateResource::canManageAssessment())
                ->authorize(fn (): bool => Gate::allows('update', $this->record))
                ->requiresConfirmation()
                ->action(function (): void {
                    $this->record = app(SetPrimaryReportTemplateAction::class)
                        ->execute(auth()->user(), $this->record);
                    Notification::make()
                        ->title('Template utama diperbarui')
                        ->success()
                        ->send();
                }),
        ];
    }
}
