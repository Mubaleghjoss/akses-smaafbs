<?php

namespace App\Filament\Resources\AccountCategoryResource\Pages;

use App\Filament\Resources\AccountCategoryResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListAccountCategories extends ListRecords
{
    protected static string $resource = AccountCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
