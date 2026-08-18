<?php

namespace App\Filament\Widgets;

use App\Services\HotspotBlocker;
use App\Services\HotspotManager;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class HealthOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $m = new HotspotManager();
        if (! $m->connect()) {
            return [
                Stat::make('MikroTik', 'Tidak terhubung')
                    ->description('Periksa konfigurasi HOTSPOT_MT_* di .env')
                    ->descriptionIcon('heroicon-m-exclamation-triangle')
                    ->color('danger'),
            ];
        }
        try {
            $h = (new HotspotBlocker($m->ros()))->health();
            $color = fn (int $pct): string => $pct >= 90 ? 'danger' : ($pct >= 75 ? 'warning' : 'success');

            return [
                Stat::make('CPU Load', "{$h['cpu']}%")
                    ->description("{$h['identity']} · RouterOS {$h['version']}")
                    ->color($color($h['cpu'])),
                Stat::make('RAM', $this->fmtBytes($h['ram_used']) . ' / ' . $this->fmtBytes($h['ram_total']))
                    ->description("{$h['ram_pct']}% terpakai")
                    ->color($color($h['ram_pct'])),
                Stat::make('Storage', $this->fmtBytes($h['hdd_used']) . ' / ' . $this->fmtBytes($h['hdd_total']))
                    ->description("{$h['hdd_pct']}% terpakai · uptime {$h['uptime']}")
                    ->color($color($h['hdd_pct'])),
                Stat::make('User Online', (string) $h['active'])
                    ->description("{$h['blocked']} domain diblokir")
                    ->color('primary'),
            ];
        } finally {
            $m->close();
        }
    }

    private function fmtBytes(int $n): string
    {
        if ($n >= 1073741824) {
            return number_format($n / 1073741824, 2, ',', '.') . ' GB';
        }
        if ($n >= 1048576) {
            return number_format($n / 1048576, 1, ',', '.') . ' MB';
        }
        if ($n >= 1024) {
            return number_format($n / 1024, 0, ',', '.') . ' KB';
        }

        return $n . ' B';
    }
}