<?php

namespace App\Filament\Resources\GuruTendikResource\Pages;

use App\Filament\Resources\GuruTendikResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

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
            Actions\DeleteAction::make()
                ->visible(fn (): bool => GuruTendikResource::canDelete($this->record)),
        ];
    }
}


