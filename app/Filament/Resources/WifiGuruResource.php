<?php

namespace App\Filament\Resources;

use App\Filament\Resources\WifiGuruResource\Pages;

/**
 * Varian Data Akun WiFi untuk GURU. Ditempatkan di grup menu "Guru".
 * Mewarisi form/table dari WifiAccountResource, beda scope role saja.
 */
class WifiGuruResource extends WifiAccountResource
{
    protected static ?string $permissionPrefix = 'akun_wifi_guru';

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-wifi';

    protected static ?string $navigationLabel = 'Data Akun WiFi';

    protected static ?string $pluralModelLabel = 'Data Akun WiFi (Guru)';

    protected static string $accountRole = 'guru';

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListWifiGuru::route('/'),
            'create' => Pages\CreateWifiGuru::route('/create'),
            'edit' => Pages\EditWifiGuru::route('/{record}/edit'),
        ];
    }
}
