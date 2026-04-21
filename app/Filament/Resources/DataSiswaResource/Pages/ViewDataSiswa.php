<?php

namespace App\Filament\Resources\DataSiswaResource\Pages;

use App\Filament\Resources\DataSiswaResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewDataSiswa extends ViewRecord
{
    protected static string $resource = DataSiswaResource::class;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        $this->record
            ->loadCount([
                'prestasis',
                'boardingRapots',
                'boardingKonselingMts',
                'boardingPerizinanSiswas',
            ])
            ->loadExists([
                'boardingPencapaian',
                'boardingArsipMt',
                'boardingKeuanganSiswa',
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
