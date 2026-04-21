<?php

namespace App\Filament\Resources\JenisBerkasResource\Pages;

use App\Filament\Resources\JenisBerkasResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListJenisBerkas extends ListRecords
{
    protected static string $resource = JenisBerkasResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
