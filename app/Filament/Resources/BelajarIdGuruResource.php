<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BelajarIdGuruResource\Pages;

/**
 * Varian Data Akun Belajar.id untuk GURU/TENDIK. Ditempatkan di grup menu "Guru".
 * Mewarisi semua form/table/import dari BelajarIdAccountResource, hanya beda scope role.
 */
class BelajarIdGuruResource extends BelajarIdAccountResource
{
    protected static ?string $permissionPrefix = 'akun_belajar_id_guru';

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-academic-cap';

    protected static ?string $navigationLabel = 'Data Akun Belajar.id';

    protected static ?string $pluralModelLabel = 'Data Akun Belajar.id (Guru)';

    protected static string $accountRole = 'guru';

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBelajarIdGuru::route('/'),
            'create' => Pages\CreateBelajarIdGuru::route('/create'),
            'edit' => Pages\EditBelajarIdGuru::route('/{record}/edit'),
        ];
    }
}
