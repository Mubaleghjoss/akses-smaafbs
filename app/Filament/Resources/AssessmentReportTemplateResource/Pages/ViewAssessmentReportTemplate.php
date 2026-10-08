<?php

namespace App\Filament\Resources\AssessmentReportTemplateResource\Pages;

use App\Actions\Assessment\SetPrimaryReportTemplateAction;
use App\Filament\Resources\AssessmentReportTemplateResource;
use App\Models\Assessment\ReportTemplate;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Gate;

class ViewAssessmentReportTemplate extends ViewRecord
{
    protected static string $resource = AssessmentReportTemplateResource::class;

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

    protected function getHeaderActions(): array
    {
        return [
            Action::make('preview')
                ->label('Pratinjau Template')
                ->icon('heroicon-o-eye')
                ->color('info')
                ->url(fn (): ?string => $this->templatePreviewUrl())
                ->visible(fn (): bool => $this->templatePreviewUrl() !== null)
                ->openUrlInNewTab()
                ->tooltip('Membuka data contoh tanpa memerlukan periode yang diterbitkan.'),
            EditAction::make()
                ->label('Edit')
                ->visible(fn (): bool => AssessmentReportTemplateResource::canEdit($this->record)),
            DeleteAction::make()
                ->label('Hapus')
                ->visible(fn (): bool => AssessmentReportTemplateResource::canDelete($this->record))
                ->databaseTransaction()
                ->before(function (DeleteAction $action): void {
                    $template = ReportTemplate::query()
                        ->whereKey($this->record->getKey())
                        ->lockForUpdate()
                        ->firstOrFail();

                    abort_unless(AssessmentReportTemplateResource::canDelete($template), 403);

                    if ($message = AssessmentReportTemplateResource::deletionBlockedMessage($template)) {
                        Notification::make()
                            ->title('Template tidak dapat dihapus')
                            ->body($message)
                            ->warning()
                            ->persistent()
                            ->send();

                        $action->halt();
                    }
                }),
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
