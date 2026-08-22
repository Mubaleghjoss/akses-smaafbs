<?php

namespace App\Filament\Pages;

use App\Services\HotspotManager;
use App\Support\Hotspot\HotspotAccessible;
use Filament\Actions;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Concerns\InteractsWithFormActions;
use Filament\Pages\Page;

class HotspotSettings extends Page implements HasForms
{
    use HotspotAccessible;
    use InteractsWithForms;
    use InteractsWithFormActions;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static \UnitEnum|string|null $navigationGroup = 'Manajemen Sekolah';

    protected static ?string $navigationLabel = 'Pengaturan Koneksi';

    protected static ?string $title = 'Pengaturan Koneksi MikroTik';

    protected string $view = 'filament.pages.hotspot-settings';

    public static function canAccess(): bool
    {
        return self::hotspotAccessGranted();
    }

    // Dipensiunkan dari navigasi: pengaturan koneksi MikroTik pindah ke aplikasi
    // terpisah (mikrotik.smaafbs.sch.id). Halaman & route tetap ada (reversibel).
    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    protected function getFormStatePath(): ?string
    {
        return 'data';
    }

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill(HotspotManager::settings());
    }

    public function getFormSchema(): array
    {
        return [
            Forms\Components\TextInput::make('host')
                ->label('Host / IP Router')
                ->required()
                ->helperText('Contoh: 192.168.90.1 (jaringan sekolah) atau 192.168.0.100 (jaringan utama).'),
            Forms\Components\TextInput::make('port')
                ->label('Port API')
                ->numeric()
                ->required()
                ->default(8728),
            Forms\Components\TextInput::make('user')
                ->label('Username API')
                ->required(),
            Forms\Components\TextInput::make('pass')
                ->label('Password API')
                ->password()
                ->revealable()
                ->autocomplete('off'),
        ];
    }

    public function simpan(): void
    {
        $data = $this->form->getState();
        foreach (['host', 'port', 'user', 'pass'] as $k) {
            \App\Models\HhSetting::set('mt_'.$k, (string) ($data[$k] ?? ''));
        }
        Notification::make()
            ->title('Pengaturan koneksi tersimpan')
            ->success()
            ->send();
    }

    public function tesKoneksi(): void
    {
        $data = $this->form->getState();
        $r = HotspotManager::testConnection(
            (string) ($data['host'] ?? ''),
            (int) ($data['port'] ?? 8728),
            (string) ($data['user'] ?? ''),
            (string) ($data['pass'] ?? ''),
        );
        if ($r['ok']) {
            Notification::make()
                ->title('✅ Terhubung: '.$r['identity'].' (RouterOS '.$r['version'].')')
                ->success()
                ->send();
        } else {
            Notification::make()
                ->title('❌ Tidak dapat terhubung')
                ->body($r['error'])
                ->danger()
                ->send();
        }
    }

    protected function getFormActions(): array
    {
        return [
            Actions\Action::make('tes')
                ->label('Tes Koneksi')
                ->icon('heroicon-o-bolt')
                ->color('info')
                ->action('tesKoneksi'),
            Actions\Action::make('simpan')
                ->label('Simpan Pengaturan')
                ->icon('heroicon-o-check')
                ->color('success')
                ->submit('simpan'),
        ];
    }
}