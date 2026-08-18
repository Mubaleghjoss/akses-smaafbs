<?php

namespace App\Filament\Resources\HotspotUserResource\Pages;

use App\Filament\Resources\HotspotUserResource;
use App\Services\HotspotManager;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListHotspotUsers extends ListRecords
{
    protected static string $resource = HotspotUserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
            Actions\Action::make('sync')
                ->label('Sync dari Router')
                ->icon('heroicon-o-arrow-path')
                ->color('info')
                ->requiresConfirmation()
                ->modalHeading('Sinkronkan akun dari router?')
                ->modalDescription('Mirror lokal akan diisi ulang dari akun di MikroTik. Data lokal yang tidak ada di router akan tetap tersimpan sebagai "local".')
                ->action(function (): void {
                    $m = new HotspotManager();
                    if (! $m->connect()) {
                        Notification::make()->title('Router tidak terhubung: ' . $m->error())->danger()->send();

                        return;
                    }
                    try {
                        $st = $m->syncUsersToLocal();
                        HotspotUserResource::cacheProfiles($m);
                        Notification::make()
                            ->title("Sync selesai: {$st['router']} akun dari router")
                            ->success()
                            ->send();
                    } finally {
                        $m->close();
                    }
                }),
        ];
    }
}