<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasConfirmedDeleteActions;
use App\Filament\Concerns\HasModulePermissions;
use App\Filament\Concerns\HasOptimizedAdminTable;
use App\Filament\Resources\JenisBerkasResource\Pages;
use App\Models\JenisBerkas;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class JenisBerkasResource extends Resource
{
    use HasConfirmedDeleteActions;
    use HasModulePermissions;
    use HasOptimizedAdminTable;

    protected static ?string $model = JenisBerkas::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-tag';

    protected static string|\UnitEnum|null $navigationGroup = 'Guru/Tendik';

    protected static ?string $navigationLabel = 'Jenis Berkas';

    protected static ?string $modelLabel = 'jenis berkas';

    protected static ?string $pluralModelLabel = 'Jenis Berkas';

    protected static ?int $navigationSort = 15;

    protected static ?string $permissionPrefix = 'jenis_berkas';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Jenis Berkas')
                    ->description('Daftar ini dipakai oleh berkas siswa dan berkas guru. Untuk berkas guru, nama folder Google Drive bisa disetel per jenis agar struktur arsip lebih fleksibel.')
                    ->columns(['default' => 1, 'md' => 2])
                    ->schema([
                        Forms\Components\TextInput::make('nama_berkas')
                            ->label('Nama Berkas')
                            ->required()
                            ->maxLength(100),
                        Forms\Components\TextInput::make('google_drive_folder_name')
                            ->label('Folder Google Drive')
                            ->maxLength(150)
                            ->helperText('Opsional. Jika diisi, berkas guru dengan jenis ini akan masuk ke folder Google Drive dengan nama ini. Jika kosong, sistem memakai Nama Berkas.'),
                        Forms\Components\Textarea::make('deskripsi')
                            ->columnSpanFull(),
                        Forms\Components\Select::make('wajib')
                            ->required()
                            ->options(['ya' => 'Ya', 'tidak' => 'Tidak'])
                            ->default('ya'),
                        Forms\Components\TextInput::make('urutan')
                            ->numeric()
                            ->default(0),
                        Forms\Components\TextInput::make('status')
                            ->default('aktif'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return static::optimizeAdminTable(
            $table,
            searchPlaceholder: 'Cari nama jenis berkas, folder Google Drive, atau status...',
            emptyStateHeading: 'Belum ada jenis berkas',
            emptyStateDescription: 'Tambahkan daftar jenis berkas agar arsip siswa dan guru lebih terstruktur.'
        )
            ->defaultSort('urutan')
            ->columns([
                Tables\Columns\TextColumn::make('nama_berkas')
                    ->label('Nama Berkas')
                    ->searchable()
                    ->wrap(),
                Tables\Columns\TextColumn::make('google_drive_folder_name')
                    ->label('Folder Drive')
                    ->state(fn (JenisBerkas $record): string => $record->resolvedGoogleDriveFolderName())
                    ->description(fn (JenisBerkas $record): ?string => filled($record->google_drive_folder_name) ? 'Custom' : 'Mengikuti nama berkas')
                    ->wrap()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('deskripsi')
                    ->label('Deskripsi')
                    ->limit(50)
                    ->wrap()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('wajib')
                    ->badge()
                    ->color(fn (?string $state): string => $state === 'ya' ? 'success' : 'gray'),
                Tables\Columns\TextColumn::make('urutan')
                    ->label('Urutan')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('wajib')
                    ->label('Kewajiban')
                    ->options([
                        'ya' => 'Wajib',
                        'tidak' => 'Opsional',
                    ]),
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options(fn (): array => JenisBerkas::statusOptions()),
            ])
            ->actions([
                EditAction::make(),
                static::makeDeleteTableAction('jenis berkas'),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    static::makeDeleteBulkTableAction(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListJenisBerkas::route('/'),
            'create' => Pages\CreateJenisBerkas::route('/create'),
            'edit' => Pages\EditJenisBerkas::route('/{record}/edit'),
        ];
    }
}
