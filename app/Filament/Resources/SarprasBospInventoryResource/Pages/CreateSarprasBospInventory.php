<?php

namespace App\Filament\Resources\SarprasBospInventoryResource\Pages;

use App\Filament\Resources\SarprasBospInventoryResource;
use Filament\Resources\Pages\CreateRecord;

class CreateSarprasBospInventory extends CreateRecord
{
    protected static string $resource = SarprasBospInventoryResource::class;

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('edit', ['record' => $this->record]);
    }
}
