<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasConfirmedDeleteActions;
use App\Filament\Concerns\HasModulePermissions;
use App\Filament\Concerns\HasOptimizedAdminTable;
use App\Filament\Resources\SarprasActivityResource\Pages;
use App\Models\SarprasActivity;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema as SchemaFacade;

class SarprasActivityResource extends Resource
{
    use HasConfirmedDeleteActions;
    use HasModulePermissions;
    use HasOptimizedAdminTable;

    protected static ?string $model = SarprasActivity::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-wrench-screwdriver';

    protected static string|\UnitEnum|null $navigationGroup = 'Manajemen Sekolah';

    protected static ?string $navigationLabel = 'Kegiatan Sarpras';

    protected static ?string $modelLabel = 'kegiatan sarpras';

    protected static ?string $pluralModelLabel = 'Kegiatan Sarpras';

    protected static ?int $navigationSort = 30;

    protected static ?string $permissionPrefix = 'sarpras_activity';

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public static function canAccess(): bool
    {
        return SchemaFacade::hasTable('sarpras_activities') && parent::canAccess();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Pekerjaan Sarpras')
                    ->description('Catat pekerjaan perbaikan, hasil akhir, dokumentasi sebelum-sesudah, dan penanggung jawabnya.')
                    ->columns(['default' => 1, 'md' => 2])
                    ->schema([
                        Forms\Components\DatePicker::make('tanggal_pengerjaan')
                            ->label('Tanggal Pengerjaan')
                            ->native(false)
                            ->closeOnDateSelection()
                            ->required(),
                        Forms\Components\TextInput::make('penanggung_jawab')
                            ->label('PJ')
                            ->maxLength(150),
                        Forms\Components\Textarea::make('perbaikan')
                            ->label('Perbaikan')
                            ->required()
                            ->rows(4)
                            ->columnSpanFull(),
                        Forms\Components\Textarea::make('hasil_akhir')
                            ->label('Hasil Akhir')
                            ->rows(4)
                            ->columnSpanFull(),
                        Forms\Components\FileUpload::make('foto_sebelum')
                            ->label('Foto Sebelum')
                            ->disk('public')
                            ->directory('sarpras/kegiatan/sebelum')
                            ->image()
                            ->imageEditor()
                            ->downloadable()
                            ->openable(),
                        Forms\Components\FileUpload::make('foto_sesudah')
                            ->label('Foto Sesudah')
                            ->disk('public')
                            ->directory('sarpras/kegiatan/sesudah')
                            ->image()
                            ->imageEditor()
                            ->downloadable()
                            ->openable(),
                        Forms\Components\TextInput::make('pelaksana_paraf')
                            ->label('Pelaksana (Paraf)')
                            ->maxLength(150),
                        Forms\Components\Textarea::make('catatan')
                            ->label('Catatan')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return static::optimizeAdminTable(
            $table,
            searchPlaceholder: 'Cari perbaikan, hasil akhir, atau nama penanggung jawab...',
            emptyStateHeading: 'Belum ada kegiatan sarpras',
            emptyStateDescription: 'Tambahkan riwayat pekerjaan sarpras agar dokumentasi perbaikan tersimpan rapi.'
        )
            ->defaultSort('tanggal_pengerjaan', 'desc')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->orderByDesc('tanggal_pengerjaan')
                ->orderByDesc('id'))
            ->columns([
                Tables\Columns\TextColumn::make('tanggal_pengerjaan')
                    ->label('Tanggal')
                    ->date('d/m/Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('perbaikan')
                    ->label('Perbaikan')
                    ->searchable()
                    ->limit(70)
                    ->wrap()
                    ->description(fn (SarprasActivity $record): ?string => filled($record->penanggung_jawab) ? 'PJ: '.$record->penanggung_jawab : null),
                Tables\Columns\TextColumn::make('hasil_akhir')
                    ->label('Hasil Akhir')
                    ->limit(70)
                    ->wrap()
                    ->visibleFrom('md'),
                Tables\Columns\ImageColumn::make('foto_sebelum')
                    ->label('Sebelum')
                    ->disk('public')
                    ->square()
                    ->visibleFrom('xl')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\ImageColumn::make('foto_sesudah')
                    ->label('Sesudah')
                    ->disk('public')
                    ->square()
                    ->visibleFrom('xl')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('pelaksana_paraf')
                    ->label('Pelaksana')
                    ->wrap()
                    ->visibleFrom('lg'),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Diupdate')
                    ->since()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->actions([
                EditAction::make(),
                static::makeDeleteTableAction('kegiatan sarpras'),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    static::makeDeleteBulkTableAction(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSarprasActivities::route('/'),
            'create' => Pages\CreateSarprasActivity::route('/create'),
            'edit' => Pages\EditSarprasActivity::route('/{record}/edit'),
        ];
    }
}
