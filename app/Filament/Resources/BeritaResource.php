<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasConfirmedDeleteActions;
use App\Filament\Concerns\HasModulePermissions;
use App\Filament\Resources\BeritaResource\Pages;
use App\Filament\Resources\BeritaResource\RelationManagers\UpdatesRelationManager;
use App\Models\Berita;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class BeritaResource extends Resource
{
    use HasConfirmedDeleteActions;
    use HasModulePermissions;

    protected static ?string $model = Berita::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-newspaper';

    protected static string|\UnitEnum|null $navigationGroup = 'Konten';

    protected static ?int $navigationSort = 10;

    protected static ?string $navigationLabel = 'Berita';

    protected static ?string $modelLabel = 'berita';

    protected static ?string $pluralModelLabel = 'Berita';

    protected static ?string $permissionPrefix = 'berita';

    public static function form(Schema $schema): Schema
    {
        $resourceSchema = [
            Section::make('Konten Berita')
                ->columns(['default' => 1, 'md' => 2])
                ->schema([
                    Forms\Components\TextInput::make('judul')
                        ->required()
                        ->maxLength(255),
                    Forms\Components\DatePicker::make('tanggal_berita')
                        ->label('Tanggal Berita'),
                    Forms\Components\Select::make('status')
                        ->required()
                        ->options([
                            'aktif' => 'Aktif',
                            'tidak aktif' => 'Tidak Aktif',
                        ])
                        ->default('aktif'),
                    Forms\Components\FileUpload::make('gambar')
                        ->label('Banner Kegiatan / Gambar Berita')
                        ->disk('public')
                        ->directory('news')
                        ->image()
                        ->imageEditor()
                        ->maxSize(4096)
                        ->nullable(),
                    Forms\Components\Textarea::make('konten')
                        ->required()
                        ->label('Deskripsi')
                        ->columnSpanFull(),
                ]),
        ];

        if (Berita::updatesTableAvailable()) {
            $resourceSchema[] = Section::make('Timeline kegiatan')
                ->schema([
                    Forms\Components\Placeholder::make('timeline_helper')
                        ->hiddenLabel()
                        ->content('Simpan data kegiatan terlebih dahulu, lalu tambahkan update tahap pada bagian "Timeline perkembangan kegiatan" di bawah form. Setiap update akan tersimpan sebagai riwayat dan snapshot terbaru akan dipakai untuk homepage.'),
                ]);
        } else {
            $trackerFields = [];

            if (Berita::trackerPhaseColumnAvailable()) {
                $trackerFields[] = Forms\Components\Select::make('tracker_phase')
                    ->label('Fase Kegiatan')
                    ->options(Berita::TRACKER_PHASES)
                    ->placeholder('Tidak dipakai sebagai tracker')
                    ->nullable();
            }

            if (Berita::trackerProgressPercentColumnAvailable()) {
                $trackerFields[] = Forms\Components\TextInput::make('tracker_progress_percent')
                    ->label('Progress Saat Ini')
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(100)
                    ->suffix('%')
                    ->nullable();
            }

            if (Berita::trackerUpdateTextColumnAvailable()) {
                $trackerFields[] = Forms\Components\Textarea::make('tracker_update_text')
                    ->label('Update Aktivitas Saat Ini')
                    ->rows(3)
                    ->columnSpanFull();
            }

            if (Berita::trackerDocumentationMediaColumnAvailable()) {
                $trackerFields[] = Forms\Components\FileUpload::make('tracker_documentation_media')
                    ->label('Dokumentasi Kegiatan')
                    ->disk('public')
                    ->directory('news/documentation')
                    ->image()
                    ->multiple()
                    ->reorderable()
                    ->maxFiles(20)
                    ->maxSize(4096)
                    ->nullable()
                    ->columnSpanFull();
            }

            if (Berita::trackerLiveUrlColumnAvailable()) {
                $trackerFields[] = Forms\Components\TextInput::make('tracker_live_url')
                    ->label('URL Live Stream')
                    ->url()
                    ->nullable()
                    ->maxLength(2048)
                    ->columnSpanFull();
            }

            if ($trackerFields !== []) {
                $resourceSchema[] = Section::make('Tracker Kegiatan (Opsional)')
                    ->columns(['default' => 1, 'md' => 2])
                    ->schema($trackerFields);
            }
        }

        $resourceSchema[] = Forms\Components\Hidden::make('id_admin')
            ->default(1);

        return $schema
            ->schema($resourceSchema);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('judul')
                    ->searchable(),
                Tables\Columns\ImageColumn::make('gambar')
                    ->disk('public')
                    ->label('Gambar'),
                Tables\Columns\TextColumn::make('status')
                    ->badge(),
                Tables\Columns\TextColumn::make('tracker_phase_label')
                    ->label('Fase Kegiatan')
                    ->badge()
                    ->color(fn (?string $state): string => match (strtolower((string) $state)) {
                        'persiapan' => 'warning',
                        'acara' => 'info',
                        'selesai' => 'success',
                        default => 'gray',
                    })
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('tanggal_berita')
                    ->date()
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->actions([
                EditAction::make(),
                static::makeDeleteTableAction('berita'),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    static::makeDeleteBulkTableAction(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return Berita::updatesTableAvailable()
            ? [
                UpdatesRelationManager::class,
            ]
            : [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBeritas::route('/'),
            'create' => Pages\CreateBerita::route('/create'),
            'edit' => Pages\EditBerita::route('/{record}/edit'),
        ];
    }
}
