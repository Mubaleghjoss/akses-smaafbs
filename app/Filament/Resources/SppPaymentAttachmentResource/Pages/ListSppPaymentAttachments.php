<?php

namespace App\Filament\Resources\SppPaymentAttachmentResource\Pages;

use App\Filament\Resources\SppPaymentAttachmentResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSppPaymentAttachments extends ListRecords
{
    protected static string $resource = SppPaymentAttachmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
