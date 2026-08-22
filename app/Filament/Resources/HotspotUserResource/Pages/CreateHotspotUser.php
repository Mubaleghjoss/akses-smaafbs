<?php

namespace App\Filament\Resources\HotspotUserResource\Pages;

use App\Filament\Resources\HotspotUserResource;
use App\Models\HotspotUser;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateHotspotUser extends CreateRecord
{
    protected static string $resource = HotspotUserResource::class;

    protected function afterCreate(): void
    {
        /** @var HotspotUser $record */
        $record = $this->record;
        $r = HotspotUserResource::syncToRouter($record);
        if (! $r['ok']) {
            Notification::make()
                ->title('Tersimpan lokal, tapi GAGAL ke router: ' . $r['msg'])
                ->danger()
                ->send();
            $record->update(['source' => 'local']);
        } else {
            $record->update(['source' => 'both']);
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}