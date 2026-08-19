<?php

namespace App\Filament\Pages;

use App\Filament\Resources\HotspotUserResource;
use App\Services\HotspotStudentAccounts;
use App\Support\Hotspot\HotspotAccessible;
use Filament\Actions;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Concerns\InteractsWithFormActions;
use Filament\Pages\Page;

class BuatAkunSiswa extends Page implements HasForms
{
    use HotspotAccessible;
    use InteractsWithForms;
    use InteractsWithFormActions;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-user-plus';

    protected static \UnitEnum|string|null $navigationGroup = 'Manajemen Sekolah';

    protected static ?string $navigationLabel = 'Buat Akun Siswa';

    protected static ?string $title = 'Buat Akun Hotspot dari Siswa';

    protected string $view = 'filament.pages.buat-akun-siswa';

    public static function canAccess(): bool
    {
        return self::hotspotAccessGranted();
    }

    protected function getFormStatePath(): ?string
    {
        return 'data';
    }

    public ?array $data = [];

    /** @var array<int, array{id:int,nama:string,rombel:string,username:string,password:string}> */
    public array $candidates = [];

    public array $selected = [];

    public bool $selectAll = false;

    public function updatedSelectAll(bool $value): void
    {
        $this->selected = $value ? array_keys($this->candidates) : [];
    }

    public function mount(): void
    {
        $this->form->fill([
            'rombel' => '',
            'profil' => array_key_first(HotspotStudentAccounts::rombelOptions() ?: ['']) ?: 'default',
            'password_mode' => 'username',
            'prefix' => '',
            'durasi' => 0,
        ]);
    }

    public function getFormSchema(): array
    {
        return [
            Forms\Components\Select::make('rombel')
                ->label('Rombel (kosong = semua siswa aktif)')
                ->options(['' => 'Semua rombel'] + HotspotStudentAccounts::rombelOptions())
                ->searchable(),
            Forms\Components\Select::make('profil')
                ->label('Grup / Profil hotspot')
                ->options(fn (): array => HotspotUserResource::profileOptions())
                ->required(),
            Forms\Components\Select::make('password_mode')
                ->label('Password')
                ->options([
                    'username' => 'Sama dengan username',
                    'nipd4' => '4 digit terakhir NIPD',
                    'nisn4' => '4 digit terakhir NISN',
                ])
                ->default('username'),
            Forms\Components\TextInput::make('prefix')
                ->label('Awalan username (opsional, mis. "siswa-")')
                ->maxLength(10),
            Forms\Components\TextInput::make('durasi')
                ->label('Durasi (hari, 0 = unlimited)')
                ->numeric()
                ->minValue(0)
                ->default(0),
        ];
    }

    public function preview(): void
    {
        $data = $this->form->getState();
        $students = HotspotStudentAccounts::candidates((string) ($data['rombel'] ?? ''));
        $usernames = HotspotStudentAccounts::buildUsernames($students, (string) ($data['prefix'] ?? ''));
        $this->candidates = $students
            ->map(fn ($st): array => [
                'id' => (int) $st->id,
                'nama' => (string) $st->nama,
                'rombel' => (string) ($st->rombel_saat_ini ?? ''),
                'username' => (string) ($usernames[$st->id] ?? ''),
                'password' => HotspotStudentAccounts::passwordFor((string) ($usernames[$st->id] ?? ''), (string) ($data['password_mode'] ?? 'username'), $st),
            ])
            ->values()
            ->all();
        $this->selected = array_keys($this->candidates);
        Notification::make()
            ->title(count($this->candidates).' username di-generate — centang yang mau dibuat, lalu klik "Buat Akun"')
            ->info()
            ->send();
    }

    public function buat(): void
    {
        if ($this->selected === []) {
            Notification::make()->title('Tidak ada akun yang dipilih')->warning()->send();

            return;
        }
        $data = $this->form->getState();
        $items = collect($this->candidates)
            ->whereIn('id', $this->selected)
            ->map(fn (array $c): array => [
                'username' => $c['username'],
                'password' => $c['password'],
                'nama' => $c['nama'],
                'rombel' => $c['rombel'],
            ])
            ->values()
            ->all();

        $r = HotspotStudentAccounts::createAccounts($items, (string) ($data['profil'] ?? 'default'), (int) ($data['durasi'] ?? 0));

        if (! ($r['connected'] ?? true)) {
            Notification::make()->title('Router tidak terhubung: '.($r['failed'][0] ?? ''))->danger()->send();

            return;
        }

        $title = "✅ {$r['done']} akun siswa dibuat di router";
        if ($r['skipped'] > 0) {
            $title .= ", {$r['skipped']} sudah ada (dilewati)";
        }
        if ($r['failed'] !== []) {
            $title .= ', '.count($r['failed']).' gagal';
        }
        Notification::make()
            ->title($title)
            ->body($r['failed'] !== [] ? implode('; ', array_slice($r['failed'], 0, 3)) : null)
            ->{$r['failed'] === [] ? 'success' : 'warning'}()
            ->send();

        $this->candidates = [];
        $this->selected = [];
    }

    protected function getFormActions(): array
    {
        return [
            Actions\Action::make('preview')
                ->label('👁 Preview Username')
                ->icon('heroicon-o-eye')
                ->color('info')
                ->action('preview'),
            Actions\Action::make('buat')
                ->label('🚀 Buat Akun Terpilih')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->submit('buat'),
        ];
    }
}