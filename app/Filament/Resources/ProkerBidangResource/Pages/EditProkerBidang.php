<?php

namespace App\Filament\Resources\ProkerBidangResource\Pages;

use App\Filament\Resources\ProkerBidangResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditProkerBidang extends EditRecord
{
    protected static string $resource = ProkerBidangResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
