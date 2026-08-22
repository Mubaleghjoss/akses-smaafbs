<?php

namespace App\Filament\Pages;

use App\Services\HotspotBlocker;
use App\Services\HotspotManager;
use App\Support\Hotspot\HotspotAccessible;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;

class Monitor extends Page
{
    use HotspotAccessible;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-heart';

    protected static \UnitEnum|string|null $navigationGroup = 'Manajemen Sekolah';

    protected static ?string $navigationLabel = 'Monitor MikroTik';

    protected static ?string $title = 'Monitor MikroTik';

    protected string $view = 'filament.hotspot.pages.monitor';

    public static function canAccess(): bool
    {
        return self::hotspotAccessGranted();
    }

    // Dipensiunkan dari navigasi: monitoring MikroTik pindah ke aplikasi terpisah
    // (mikrotik.smaafbs.sch.id). Halaman & route tetap ada (reversibel).
    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public array $health = [];

    public array $traffic = [];

    public array $active = [];

    public string $error = '';

    public function mount(): void
    {
        $m = new HotspotManager();
        if (! $m->connect()) {
            $this->error = $m->error();

            return;
        }
        try {
            $b = new HotspotBlocker($m->ros());
            $this->health = $b->health();
            $this->traffic = $b->trafficRates();
            $this->active = $m->routerActive();
        } finally {
            $m->close();
        }
    }

    public function getWidgets(): array
    {
        return [
            \App\Filament\Widgets\HealthOverview::class,
        ];
    }

    public function getTitle(): string|Htmlable
    {
        return 'Monitor MikroTik';
    }

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\Action::make('refresh')
                ->label('Segarkan')
                ->icon('heroicon-o-arrow-path')
                ->url(static::getUrl()),
        ];
    }

    // Helper format (dipakai view)
    public static function fmtBytes(int $n): string
    {
        if ($n >= 1073741824) {
            return number_format($n / 1073741824, 2, ',', '.') . ' GB';
        }
        if ($n >= 1048576) {
            return number_format($n / 1048576, 1, ',', '.') . ' MB';
        }

        return $n >= 1024 ? number_format($n / 1024, 0, ',', '.') . ' KB' : $n . ' B';
    }

    public static function fmtBps(int $n): string
    {
        if ($n >= 1000000000) {
            return number_format($n / 1000000000, 2, ',', '.') . ' Gbps';
        }
        if ($n >= 1000000) {
            return number_format($n / 1000000, 1, ',', '.') . ' Mbps';
        }

        return $n >= 1000 ? number_format($n / 1000, 0, ',', '.') . ' Kbps' : $n . ' bps';
    }
}