<?php

namespace App\Filament\Resources\SppPaymentAttachmentResource\Pages;

use App\Filament\Resources\SppPaymentAttachmentResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSppPaymentAttachment extends EditRecord
{
    protected static string $resource = SppPaymentAttachmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
