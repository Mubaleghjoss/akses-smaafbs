<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasConfirmedDeleteActions;
use App\Filament\Concerns\HasModulePermissions;
use App\Filament\Resources\PerpustakaanBukuResource\Pages;
use App\Models\PerpustakaanBuku;
use App\Models\PerpustakaanKategori;
use App\Models\PerpustakaanLemari;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;

class PerpustakaanBukuResource extends Resource
{
    use HasConfirmedDeleteActions;
    use HasModulePermissions;

    protected static ?string $model = PerpustakaanBuku::class;

    protected static ?string $permissionPrefix = 'perpustakaan_literasi';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static string|\UnitEnum|null $navigationGroup = 'Manajemen Sekolah';

    protected static ?int $navigationSort = 10;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Forms\Components\TextInput::make('judul_buku')
                    ->required()
                    ->maxLength(500),
                Forms\Components\TextInput::make('penulis')
                    ->maxLength(255)
                    ->default(null),
                Forms\Components\TextInput::make('penerbit')
                    ->maxLength(255)
                    ->default(null),
                Forms\Components\TextInput::make('isbn')
                    ->maxLength(50)
                    ->default(null),
                Forms\Components\TextInput::make('tahun_terbit'),
                Forms\Components\Select::make('kategori_id')
                    ->required()
                    ->searchable()
                    ->options(fn (): array => PerpustakaanKategori::searchOptionLabels(limit: 25))
                    ->getSearchResultsUsing(fn (string $search): array => PerpustakaanKategori::searchOptionLabels($search))
                    ->getOptionLabelUsing(fn ($value): ?string => PerpustakaanKategori::resolveOptionLabel($value)),
                Forms\Components\Select::make('lemari_id')
                    ->required()
                    ->searchable()
                    ->options(fn (): array => PerpustakaanLemari::searchOptionLabels(limit: 25))
                    ->getSearchResultsUsing(fn (string $search): array => PerpustakaanLemari::searchOptionLabels($search))
                    ->getOptionLabelUsing(fn ($value): ?string => PerpustakaanLemari::resolveOptionLabel($value)),
                Forms\Components\TextInput::make('jumlah_buku')
                    ->required()
                    ->numeric()
                    ->default(1),
                Forms\Components\TextInput::make('jumlah_tersedia')
                    ->required()
                    ->numeric()
                    ->default(1),
                Forms\Components\Select::make('status')
                    ->required()
                    ->options([
                        'tersedia' => 'Tersedia',
                        'dipinjam' => 'Dipinjam',
                        'rusak' => 'Rusak',
                        'hilang' => 'Hilang',
                    ])
                    ->default('tersedia'),
                Forms\Components\Textarea::make('deskripsi')
                    ->columnSpanFull(),
                Forms\Components\Select::make('file_type')
                    ->label('Tipe')
                    ->options([
                        'physical' => 'Fisik',
                        'ebook' => 'E-Book',
                    ])
                    ->default('physical'),
                Forms\Components\FileUpload::make('file_path')
                    ->label('File E-Book')
                    ->disk('public')
                    ->directory('ebooks')
                    ->acceptedFileTypes(['application/pdf'])
                    ->maxSize(4096)
                    ->nullable(),
                Forms\Components\TextInput::make('file_size')
                    ->numeric()
                    ->default(null),
                Forms\Components\TextInput::make('mime_type')
                    ->maxLength(100)
                    ->default(null),
                Forms\Components\DateTimePicker::make('upload_date'),
                Forms\Components\TextInput::make('download_count')
                    ->numeric()
                    ->default(0),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('judul_buku')
                    ->searchable(),
                Tables\Columns\TextColumn::make('penulis')
                    ->searchable(),
                Tables\Columns\TextColumn::make('penerbit')
                    ->searchable(),
                Tables\Columns\TextColumn::make('isbn')
                    ->searchable(),
                Tables\Columns\TextColumn::make('tahun_terbit'),
                Tables\Columns\TextColumn::make('kategori_id')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('lemari_id')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('jumlah_buku')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('jumlah_tersedia')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('status'),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('file_path')
                    ->label('E-Book')
                    ->url(function (PerpustakaanBuku $record): ?string {
                        $path = (string) ($record->file_path ?? '');
                        if ($path === '') {
                            return null;
                        }
                        if (str_starts_with($path, 'assets/')) {
                            $path = preg_replace('#^assets/uploads/#', '', $path) ?: (string) $record->file_path;
                        }

                        return Storage::disk('public')->url($path);
                    })
                    ->openUrlInNewTab()
                    ->searchable(),
                Tables\Columns\TextColumn::make('file_type'),
                Tables\Columns\TextColumn::make('file_size')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('mime_type')
                    ->searchable(),
                Tables\Columns\TextColumn::make('upload_date')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('download_count')
                    ->numeric()
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->actions([
                EditAction::make(),
                static::makeDeleteTableAction('buku perpustakaan'),
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
            'index' => Pages\ListPerpustakaanBukus::route('/'),
            'create' => Pages\CreatePerpustakaanBuku::route('/create'),
            'edit' => Pages\EditPerpustakaanBuku::route('/{record}/edit'),
        ];
    }
}
