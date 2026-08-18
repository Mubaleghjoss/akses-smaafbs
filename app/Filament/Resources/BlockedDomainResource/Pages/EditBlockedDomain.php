<?php

namespace App\Filament\Resources\BlockedDomainResource\Pages;

use App\Filament\Resources\BlockedDomainResource;
use App\Services\HotspotBlocker;
use App\Services\HotspotManager;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditBlockedDomain extends EditRecord
{
    protected static string $resource = BlockedDomainResource::class;

    protected function afterSave(): void
    {
        $old = (string) ($this->oldAttributes['domain'] ?? '');
        $new = (string) $this->record->domain;
        $m = new HotspotManager();
        if (! $m->connect()) {
            Notification::make()->title('Perubahan GAGAL ke router (tidak terhubung): ' . $m->error())->warning()->send();

            return;
        }
        try {
            $b = new HotspotBlocker($m->ros());
            $removed = $old !== '' && $old !== $new ? $b->removeDomain($old) : 0;
            $ok = $b->pushDomain($new);
            Notification::make()
                ->title($ok
                    ? "{$new} diperbarui di router" . ($removed > 0 ? " (lama: {$old} dibersihkan)" : '')
                    : "GAGAL push {$new} ke router")
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