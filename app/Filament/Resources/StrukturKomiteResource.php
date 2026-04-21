<?php

namespace App\Filament\Resources;

use App\Filament\Resources\StrukturKomiteResource\Pages;
use App\Models\StrukturOrganisasi;

class StrukturKomiteResource extends StrukturOrganisasiResource
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationLabel = 'Struktur Komite';

    protected static ?int $navigationSort = 30;

    protected static ?string $modelLabel = 'Struktur Komite';

    protected static ?string $pluralModelLabel = 'Struktur Komite';

    protected static ?string $permissionPrefix = 'struktur_komite';

    protected static ?string $structureCategory = StrukturOrganisasi::CATEGORY_COMMITTEE;

    protected static bool $allowsGuruTendikLink = false;

    protected static string $uploadDirectory = 'struktur-komite';

    protected static bool $requiresPhoto = false;

    protected static bool $usesPeriods = true;

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStrukturKomites::route('/'),
            'create' => Pages\CreateStrukturKomite::route('/create'),
            'edit' => Pages\EditStrukturKomite::route('/{record}/edit'),
        ];
    }
}
