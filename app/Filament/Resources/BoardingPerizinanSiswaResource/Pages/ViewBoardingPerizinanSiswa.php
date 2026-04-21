<?php

namespace App\Filament\Resources\BoardingPerizinanSiswaResource\Pages;

use App\Filament\Resources\BoardingPerizinanSiswaResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewBoardingPerizinanSiswa extends ViewRecord
{
    protected static string $resource = BoardingPerizinanSiswaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('kembali')
                ->label('Kembali ke daftar')
                ->url(BoardingPerizinanSiswaResource::getUrl()),
        ];
    }
}
