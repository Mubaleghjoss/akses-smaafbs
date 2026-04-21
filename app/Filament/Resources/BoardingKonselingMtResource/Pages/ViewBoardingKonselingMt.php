<?php

namespace App\Filament\Resources\BoardingKonselingMtResource\Pages;

use App\Filament\Resources\BoardingKonselingMtResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewBoardingKonselingMt extends ViewRecord
{
    protected static string $resource = BoardingKonselingMtResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('kembali')
                ->label('Kembali ke daftar')
                ->url(BoardingKonselingMtResource::getUrl()),
        ];
    }
}
