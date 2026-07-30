<?php

namespace App\Filament\Resources\GuruTendikResource\Pages;

use App\Filament\Resources\GuruTendikResource;
use App\Models\Assessment\TeachingAssignment;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema as DatabaseSchema;

class EditGuruTendik extends EditRecord
{
    protected static string $resource = GuruTendikResource::class;

    protected function afterSave(): void
    {
        GuruTendikResource::flushTugasTambahanGoogleDriveCaches($this->record);
        $this->record->refresh();
        $this->fillForm();

        $this->dispatch('refresh-page');
        $this->dispatch('refresh-tugas-tambahan-relation-manager');
        $this->dispatch('refresh-sidebar');
        $this->dispatch('refresh-topbar');

        GuruTendikResource::notifyTugasTambahanGoogleDriveSummary($this->record);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('assessmentAssignments')
                ->label('Atur Mapel & Walas')
                ->icon('heroicon-o-academic-cap')
                ->color('success')
                ->url(GuruTendikResource::getUrl('edit', [
                    'record' => $this->record,
                    'relation' => '0',
                ]))
                ->authorize(fn (): bool => DatabaseSchema::hasTable('assessment_teaching_assignments')
                    && Gate::allows('viewAny', TeachingAssignment::class))
                ->visible(fn (): bool => DatabaseSchema::hasTable('assessment_teaching_assignments')
                    && Gate::allows('viewAny', TeachingAssignment::class)),
            Actions\DeleteAction::make()
                ->visible(fn (): bool => GuruTendikResource::canDelete($this->record)),
        ];
    }
}
