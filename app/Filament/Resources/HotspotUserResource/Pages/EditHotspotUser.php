<?php

namespace App\Filament\Resources\HotspotUserResource\Pages;

use App\Filament\Resources\HotspotUserResource;
use App\Models\HotspotUser;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditHotspotUser extends EditRecord
{
    protected static string $resource = HotspotUserResource::class;

    protected function afterSave(): void
    {
        /** @var HotspotUser $record */
        $record = $this->record;
        $oldName = (string) $this->oldAttributes['username'] ?? $record->username;
        $r = HotspotUserResource::syncToRouter($record, $oldName);
        if (! $r['ok']) {
            Notification::make()
                ->title('Perubahan GAGAL ke router: ' . $r['msg'])
                ->danger()
                ->send();
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}