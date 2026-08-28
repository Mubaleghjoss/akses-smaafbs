<?php

namespace App\Filament\Resources\BkKasusResource\Pages;

use App\Filament\Resources\BkKasusResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewBkKasus extends ViewRecord
{
    protected static string $resource = BkKasusResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
            Actions\Action::make('kembali')
                ->label('Kembali ke daftar')
                ->color('gray')
                ->url(BkKasusResource::getUrl()),
        ];
    }
}
