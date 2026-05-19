<?php

namespace App\Filament\Resources\SarprasBospInventoryResource\Pages;

use App\Filament\Resources\SarprasBospInventoryResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSarprasBospInventory extends EditRecord
{
    protected static string $resource = SarprasBospInventoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('downloadSticker')
                ->label('Download Stiker')
                ->icon('heroicon-o-qr-code')
                ->color('info')
                ->url(fn (): string => route('admin.sarpras-bosp-inventories.sticker', $this->record))
                ->openUrlInNewTab(),
            Actions\DeleteAction::make(),
        ];
    }
}
