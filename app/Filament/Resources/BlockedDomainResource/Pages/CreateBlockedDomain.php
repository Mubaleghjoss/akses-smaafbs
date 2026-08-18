<?php

namespace App\Filament\Resources\BlockedDomainResource\Pages;

use App\Filament\Resources\BlockedDomainResource;
use App\Services\HotspotBlocker;
use App\Services\HotspotManager;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateBlockedDomain extends CreateRecord
{
    protected static string $resource = BlockedDomainResource::class;

    protected function afterCreate(): void
    {
        $domain = (string) $this->record->domain;
        $m = new HotspotManager();
        if (! $m->connect()) {
            Notification::make()->title('Tersimpan lokal, tapi router tidak terhubung: ' . $m->error())->warning()->send();

            return;
        }
        try {
            $b = new HotspotBlocker($m->ros());
            $ok = $b->pushDomain($domain);
            Notification::make()
                ->title($ok ? "{$domain} terblokir di router" : "GAGAL push {$domain} ke router")
                ->{$ok ? 'success' : 'danger'}()
                ->send();
        } finally {
            $m->close();
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}