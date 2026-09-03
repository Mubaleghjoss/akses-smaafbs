<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasModulePermissions;
use App\Filament\Resources\PengaturanResource\Pages;
use App\Models\Pengaturan;
use Filament\Resources\Resource;
use Illuminate\Support\Facades\Schema as SchemaFacade;

class PengaturanResource extends Resource
{
    use HasModulePermissions;

    protected static ?string $model = Pengaturan::class;

    protected static ?string $permissionPrefix = 'pengaturan_situs';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-swatch';

    protected static string|\UnitEnum|null $navigationGroup = 'Pengaturan';

    protected static ?int $navigationSort = 10;

    protected static ?string $modelLabel = 'Pengaturan Situs';

    protected static ?string $pluralModelLabel = 'Pengaturan Situs';

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public static function canAccess(): bool
    {
        return SchemaFacade::hasTable('pengaturan') && static::userCanModule('view');
    }

    public static function canViewAny(): bool
    {
        return SchemaFacade::hasTable('pengaturan') && static::userCanModule('view');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPengaturans::route('/'),
        ];
    }
}
