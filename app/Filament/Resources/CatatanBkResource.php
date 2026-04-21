<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasModulePermissions;
use App\Filament\Concerns\HasOptimizedAdminTable;
use App\Filament\Resources\CatatanBkResource\Pages;
use App\Filament\Resources\CatatanBkResource\RelationManagers\CatatanBksRelationManager;
use App\Models\DataSiswa;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema as SchemaFacade;

class CatatanBkResource extends Resource
{
    use HasModulePermissions;
    use HasOptimizedAdminTable;

    protected static ?string $model = DataSiswa::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static string|\UnitEnum|null $navigationGroup = 'Manajemen Sekolah';

    protected static ?string $navigationLabel = 'Catatan BK';

    protected static ?string $modelLabel = 'catatan BK siswa';

    protected static ?string $pluralModelLabel = 'Catatan BK';

    protected static ?int $navigationSort = 33;

    protected static ?string $permissionPrefix = 'catatan_bk';

    public static function canAccess(): bool
    {
        return SchemaFacade::hasTable('data_siswa')
            && SchemaFacade::hasTable('catatan_bks')
            && parent::canAccess();
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema;
    }

    /**
     * @return array<int, Forms\Components\Component|Section>
     */
    public static function noteFormSchema(): array
    {
        return [
            Section::make('Isi Konseling BK')
                ->columns(['default' => 1, 'md' => 2])
                ->schema([
                    Forms\Components\DatePicker::make('tanggal_konseling')
                        ->label('Tanggal Konseling')
                        ->default(now())
                        ->required(),
                    Forms\Components\TextInput::make('topik_pembahasan')
                        ->label('Topik Pembahasan')
                        ->required()
                        ->maxLength(180),
                    Forms\Components\Textarea::make('hasil_konseling')
                        ->label('Hasil Konseling')
                        ->rows(5)
                        ->required()
                        ->columnSpanFull(),
                ]),
        ];
    }

    public static function userCanManageEntries(): bool
    {
        return static::userCanModule('manage');
    }

    public static function createNoteForStudent(DataSiswa $student, array $data): void
    {
        $student->catatanBks()->create([
            'tanggal_konseling' => $data['tanggal_konseling'],
            'topik_pembahasan' => trim((string) ($data['topik_pembahasan'] ?? '')),
            'hasil_konseling' => trim((string) ($data['hasil_konseling'] ?? '')),
            'created_by' => auth()->id(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return static::optimizeAdminTable(
            $table,
            searchPlaceholder: 'Cari nama murid, NISN, NIPD, atau rombel...',
            emptyStateHeading: 'Belum ada data murid untuk Catatan BK',
            emptyStateDescription: 'Daftar siswa akan tampil di sini, lalu konseling bisa dicatat per murid.'
        )
            ->modifyQueryUsing(fn (Builder $query): Builder => DataSiswa::applyVisibleScope($query, auth()->user())
                ->withCount('catatanBks')
                ->withMax('catatanBks as catatan_bk_terakhir_at', 'tanggal_konseling'))
            ->defaultSort('nama')
            ->columns([
                Tables\Columns\TextColumn::make('nama')
                    ->label('Murid')
                    ->searchable()
                    ->sortable()
                    ->description(fn (DataSiswa $record): string => collect([
                        $record->rombel_saat_ini ?: 'Tanpa rombel',
                        DataSiswa::statusLabel($record->status),
                    ])->filter()->implode(' | '))
                    ->wrap(),
                Tables\Columns\TextColumn::make('nisn')
                    ->label('NISN')
                    ->searchable()
                    ->visibleFrom('md'),
                Tables\Columns\TextColumn::make('nipd')
                    ->label('NIPD')
                    ->searchable()
                    ->visibleFrom('md')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('catatan_bks_count')
                    ->label('Konseling')
                    ->badge()
                    ->alignCenter()
                    ->sortable(),
                Tables\Columns\TextColumn::make('catatan_bk_terakhir_at')
                    ->label('Terakhir')
                    ->date('d/m/Y')
                    ->placeholder('-')
                    ->visibleFrom('md'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options(DataSiswa::statusOptions()),
                Tables\Filters\SelectFilter::make('jk')
                    ->label('JK')
                    ->options([
                        'L' => 'Laki-laki',
                        'P' => 'Perempuan',
                    ]),
            ])
            ->recordUrl(fn (DataSiswa $record): string => static::getUrl('view', ['record' => $record]))
            ->actions([
                Action::make('isiKonseling')
                    ->label('Isi Konseling')
                    ->icon('heroicon-o-pencil-square')
                    ->color('primary')
                    ->modalHeading(fn (DataSiswa $record): string => 'Isi Konseling BK: '.$record->nama)
                    ->modalSubmitActionLabel('Simpan Konseling')
                    ->schema(static::noteFormSchema())
                    ->visible(fn (): bool => static::userCanManageEntries())
                    ->action(function (DataSiswa $record, array $data): void {
                        static::createNoteForStudent($record, $data);

                        Notification::make()
                            ->success()
                            ->title('Catatan BK berhasil disimpan.')
                            ->send();
                    }),
                ViewAction::make('riwayat')
                    ->label('Riwayat'),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->columns(['default' => 1, 'md' => 2])
            ->schema([
                Section::make('Ringkasan Murid')
                    ->columns(['default' => 1, 'md' => 2])
                    ->schema([
                        TextEntry::make('nama')
                            ->label('Nama')
                            ->placeholder('-'),
                        TextEntry::make('status')
                            ->label('Status')
                            ->badge()
                            ->formatStateUsing(fn (?string $state): string => DataSiswa::statusLabel($state))
                            ->placeholder('-'),
                        TextEntry::make('nisn')
                            ->label('NISN')
                            ->placeholder('-'),
                        TextEntry::make('nipd')
                            ->label('NIPD')
                            ->placeholder('-'),
                        TextEntry::make('rombel_saat_ini')
                            ->label('Rombel')
                            ->placeholder('-'),
                        TextEntry::make('jumlah_konseling')
                            ->label('Jumlah Konseling')
                            ->state(fn (DataSiswa $record): int => $record->catatanBks()->count()),
                        TextEntry::make('konseling_terakhir')
                            ->label('Konseling Terakhir')
                            ->state(function (DataSiswa $record): string {
                                $tanggal = $record->catatanBks()->max('tanggal_konseling');

                                return filled($tanggal)
                                    ? \Illuminate\Support\Carbon::parse($tanggal)->format('d/m/Y')
                                    : '-';
                            }),
                    ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            CatatanBksRelationManager::class,
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return DataSiswa::applyVisibleScope(parent::getEloquentQuery(), auth()->user());
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCatatanBks::route('/'),
            'view' => Pages\ViewCatatanBk::route('/{record}'),
        ];
    }
}
