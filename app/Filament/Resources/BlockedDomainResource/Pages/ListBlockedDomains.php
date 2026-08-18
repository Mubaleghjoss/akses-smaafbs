<?php

namespace App\Filament\Resources\BlockedDomainResource\Pages;

use App\Filament\Resources\BlockedDomainResource;
use App\Services\HotspotBlocker;
use App\Services\HotspotManager;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListBlockedDomains extends ListRecords
{
    protected static string $resource = BlockedDomainResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
            Actions\Action::make('sync')
                ->label('Sync ke Router')
                ->icon('heroicon-o-arrow-path')
                ->color('info')
                ->requiresConfirmation()
                ->modalDescription('Semua domain di daftar ini dipush ke address-list blocklist router (dengan verifikasi).')
                ->action(function (): void {
                    $m = new HotspotManager();
                    if (! $m->connect()) {
                        Notification::make()->title('Router tidak terhubung: ' . $m->error())->danger()->send();

                        return;
                    }
                    try {
                        $b = new HotspotBlocker($m->ros());
                        $okRule = $b->ensureRule(config('hotspot.comment_block'), 'drop');
                        $st = $b->syncAll();
                        $msg = "Sync selesai: {$st['exists']} sudah ada, {$st['added']} baru dipush";
                        if ($st['failed'] > 0) {
                            $msg .= ", {$st['failed']} GAGAL";
                        }
                        $msg .= '. Aturan firewall blokir: ' . ($okRule ? 'aktif' : 'GAGAL dibuat');
                        Notification::make()
                            ->title($msg)
                            ->{$st['failed'] > 0 || ! $okRule ? 'danger' : 'success'}()
                            ->send();
                    } finally {
                        $m->close();
                    }
                }),
            Actions\Action::make('aktifkan_semua')
                ->label('Aktifkan Semua')
                ->icon('heroicon-o-bolt')
                ->color('success')
                ->requiresConfirmation()
                ->modalDescription('Pastikan semua proteksi aktif: rule blokir, kunci DNS 53, anti-bypass DoT/DoH, sync domain.')
                ->action(function (): void {
                    $m = new HotspotManager();
                    if (! $m->connect()) {
                        Notification::make()->title('Router tidak terhubung: ' . $m->error())->danger()->send();

                        return;
                    }
                    try {
                        $b = new HotspotBlocker($m->ros());
                        $blk = $b->ensureRule(config('hotspot.comment_block'), 'drop');
                        $dns = $b->ensureRule(config('hotspot.comment_dns'), 'dnslock');
                        $dns2 = $b->enableDnsLock2();
                        $st = $b->syncAll();
                        Notification::make()
                            ->title("Proteksi: blokir=" . ($blk ? 'ON' : 'GAGAL') . ", kunciDNS=" . ($dns ? 'ON' : 'GAGAL') . ", antiBypass=" . ($dns2['ok'] ? 'ON' : 'GAGAL') . "; domain: {$st['added']} baru, {$st['exists']} ada")
                            ->{$blk && $dns && $dns2['ok'] && $st['failed'] === 0 ? 'success' : 'danger'}()
                            ->send();
                    } finally {
                        $m->close();
                    }
                }),
        ];
    }
}