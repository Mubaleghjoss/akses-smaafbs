<?php

namespace App\Filament\Resources\HotspotUserResource\Pages;

use App\Filament\Resources\HotspotUserResource;
use App\Models\HotspotUser;
use App\Services\HotspotManager;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListHotspotUsers extends ListRecords
{
    protected static string $resource = HotspotUserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
            Actions\Action::make('bulk_add')
                ->label('Tambah Massal')
                ->icon('heroicon-o-plus-circle')
                ->color('primary')
                ->modalHeading('Tambah Massal Akun Hotspot')
                ->form([
                    Forms\Components\Textarea::make('lines')
                        ->label('Satu akun per baris:  username | password | profil (opsional)')
                        ->rows(10)
                        ->required()
                        ->helperText('Password kosong = sama dengan username. Profil kosong = profil default di bawah.')
                        ->placeholder("siswa001\nsiswa002|12345|AKUN-WIFI-MURID\nsiswa003|12345|TAMU"),
                    Forms\Components\Select::make('profil')
                        ->label('Profil default')
                        ->options(fn (): array => HotspotUserResource::profileOptions())
                        ->default('default'),
                    Forms\Components\TextInput::make('durasi')
                        ->label('Durasi (hari, 0 = unlimited)')
                        ->numeric()
                        ->minValue(0)
                        ->default(0),
                ])
                ->action(function (array $data): void {
                    $m = new HotspotManager();
                    if (! $m->connect()) {
                        Notification::make()->title('Router tidak terhubung: ' . $m->error())->danger()->send();

                        return;
                    }
                    $done = 0;
                    $errors = [];
                    try {
                        foreach (preg_split('/\r\n|\r|\n/', (string) ($data['lines'] ?? '')) ?: [] as $line) {
                            $line = trim($line);
                            if ($line === '') {
                                continue;
                            }
                            $parts = array_map('trim', explode('|', $line));
                            $username = (string) ($parts[0] ?? '');
                            if ($username === '' || HotspotUser::where('username', $username)->exists()) {
                                if ($username !== '') {
                                    $errors[] = "$username: sudah ada";
                                }

                                continue;
                            }
                            $password = (string) ($parts[1] ?? '') ?: $username;
                            $profile = (string) ($parts[2] ?? '') ?: (string) ($data['profil'] ?? 'default');
                            $r = $m->addUser([
                                'username' => $username,
                                'password' => $password,
                                'profile' => $profile,
                                'durasi' => (int) ($data['durasi'] ?? 0),
                            ]);
                            if ($r['ok']) {
                                HotspotUser::create([
                                    'username' => $username,
                                    'password' => $password,
                                    'profile' => $profile,
                                    'durasi' => (int) ($data['durasi'] ?? 0),
                                    'source' => 'both',
                                ]);
                                $done++;
                            } else {
                                $errors[] = "$username: " . ($r['msg'] ?? 'gagal');
                            }
                        }
                        HotspotUserResource::cacheProfiles($m);
                    } finally {
                        $m->close();
                    }
                    $title = "Tambah massal: {$done} akun dibuat di router";
                    if ($errors !== []) {
                        $title .= ', ' . count($errors) . ' dilewati (' . implode('; ', array_slice($errors, 0, 3)) . ')';
                    }
                    Notification::make()
                        ->title($title)
                        ->{$errors === [] ? 'success' : 'warning'}()
                        ->send();
                }),
            Actions\Action::make('sync_lokal')
                ->label('Sync Lokal → Router')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Push akun lokal ke router?')
                ->modalDescription('Akun ber-label "local" (di DB tapi belum di router) akan dibuatkan di MikroTik.')
                ->action(function (): void {
                    $m = new HotspotManager();
                    if (! $m->connect()) {
                        Notification::make()->title('Router tidak terhubung: ' . $m->error())->danger()->send();

                        return;
                    }
                    $done = 0;
                    $errors = [];
                    try {
                        foreach (HotspotUser::where('source', 'local')->get() as $u) {
                            $r = $m->addUser([
                                'username' => $u->username,
                                'password' => $u->password,
                                'profile' => $u->profile,
                                'durasi' => (int) $u->durasi,
                            ]);
                            if ($r['ok']) {
                                $u->source = 'both';
                                $u->save();
                                $done++;
                            } else {
                                $errors[] = $u->username . ': ' . ($r['msg'] ?? 'gagal');
                            }
                        }
                        HotspotUserResource::cacheProfiles($m);
                    } finally {
                        $m->close();
                    }
                    Notification::make()
                        ->title("Sync lokal → router: {$done} akun di-push" . ($errors !== [] ? ', ' . count($errors) . ' gagal' : ''))
                        ->{$errors === [] ? 'success' : 'warning'}()
                        ->send();
                }),
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