<?php

namespace App\Filament\Resources\JenisBerkasResource\Pages;

use App\Filament\Resources\JenisBerkasResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditJenisBerkas extends EditRecord
{
    protected static string $resource = JenisBerkasResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
