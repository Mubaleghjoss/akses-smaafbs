<?php

namespace App\Filament\Resources\CatatanBkResource\Pages;

use App\Filament\Resources\CatatanBkResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewCatatanBk extends ViewRecord
{
    protected static string $resource = CatatanBkResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('kembali')
                ->label('Kembali ke daftar')
                ->url(CatatanBkResource::getUrl()),
        ];
    }
}
