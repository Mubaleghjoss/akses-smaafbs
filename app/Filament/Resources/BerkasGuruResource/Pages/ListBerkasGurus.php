<?php

namespace App\Filament\Resources\BerkasGuruResource\Pages;

use App\Filament\Resources\BerkasGuruResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListBerkasGurus extends ListRecords
{
    protected static string $resource = BerkasGuruResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->visible(fn (): bool => BerkasGuruResource::canCreate()),
        ];
    }
}
