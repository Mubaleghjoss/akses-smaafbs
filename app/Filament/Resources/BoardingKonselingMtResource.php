<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasModulePermissions;
use App\Filament\Concerns\HasOptimizedAdminTable;
use App\Filament\Resources\BoardingKonselingMtResource\Pages;
use App\Filament\Resources\BoardingKonselingMtResource\RelationManagers\BoardingKonselingMtsRelationManager;
use App\Models\DataSiswa;
use App\Models\User;
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
use Illuminate\Support\Str;

class BoardingKonselingMtResource extends Resource
{
    use HasModulePermissions;
    use HasOptimizedAdminTable;

    protected static ?string $model = DataSiswa::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static string|\UnitEnum|null $navigationGroup = 'Boarding';

    protected static ?string $navigationLabel = 'Konseling Boarding';

    protected static ?string $modelLabel = 'konseling boarding';

    protected static ?string $pluralModelLabel = 'Konseling Boarding';

    protected static ?int $navigationSort = 40;

    protected static ?string $permissionPrefix = 'boarding_konseling';

    public static function canAccess(): bool
    {
        return SchemaFacade::hasTable('data_siswa')
            && SchemaFacade::hasTable('boarding_konseling_mts')
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
            Section::make('Isi Konseling Boarding')
                ->columns(['default' => 1, 'md' => 2])
                ->schema([
                    Forms\Components\DatePicker::make('tanggal_konseling')
                        ->label('Tanggal Konseling')
                        ->default(now())
                        ->required(),
                    Forms\Components\Select::make('pamong_user_id')
                        ->label('Pamong Penanggung Jawab')
                        ->searchable()
                        ->getSearchResultsUsing(fn (string $search): array => User::searchBoardingPamongOptions($search))
                        ->getOptionLabelUsing(fn ($value): ?string => User::resolveNameOptionLabel($value))
                        ->visible(fn (): bool => static::shouldShowPamongOwnerField())
                        ->required(fn (): bool => static::shouldShowPamongOwnerField()),
                    Forms\Components\TextInput::make('ringkasan_masalah')
                        ->label('Topik Pembahasan')
                        ->required()
                        ->maxLength(255),
                    Forms\Components\Textarea::make('tindak_lanjut')
                        ->label('Hasil Konseling')
                        ->rows(5)
                        ->required()
                        ->columnSpanFull(),
                    Forms\Components\Hidden::make('kategori')
                        ->dehydrateStateUsing(fn ($state, $get): string => Str::limit(trim((string) $get('ringkasan_masalah')), 100, '')),
                    Forms\Components\Hidden::make('prioritas')
                        ->default('sedang'),
                    Forms\Components\Hidden::make('status_tindak_lanjut')
                        ->default('dipantau'),
                    Forms\Components\Hidden::make('konselor')
                        ->default(fn (): ?string => auth()->user()?->name),
                ]),
        ];
    }

    public static function userCanManageEntries(): bool
    {
        return static::userCanModule('manage');
    }

    public static function createNoteForStudent(DataSiswa $student, array $data): void
    {
        $ownerId = filled($data['pamong_user_id'] ?? null)
            ? (int) $data['pamong_user_id']
            : null;
        $topik = trim((string) ($data['ringkasan_masalah'] ?? ''));
        $hasil = trim((string) ($data['tindak_lanjut'] ?? ''));

        $student->boardingKonselingMts()->create([
            'tanggal_konseling' => $data['tanggal_konseling'],
            'pamong_user_id' => static::pamongOwnerColumnAvailable() ? $ownerId : null,
            'kategori' => Str::limit($topik, 100, ''),
            'prioritas' => 'sedang',
            'status_tindak_lanjut' => 'dipantau',
            'konselor' => auth()->user()?->name,
            'ringkasan_masalah' => $topik,
            'tindak_lanjut' => $hasil,
        ]);
    }

    public static function table(Table $table): Table
    {
        return static::optimizeAdminTable(
            $table,
            searchPlaceholder: 'Cari murid atau rombel boarding...',
            emptyStateHeading: 'Belum ada data murid untuk konseling boarding',
            emptyStateDescription: 'Daftar siswa boarding akan tampil di sini, lalu konseling bisa dicatat per murid.'
        )
            ->modifyQueryUsing(fn (Builder $query): Builder => static::applyStudentSummaryAggregates(
                DataSiswa::applyVisibleScope($query, auth()->user())
            ))
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
                Tables\Columns\TextColumn::make('jk')
                    ->label('JK')
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'L' => 'Laki-laki',
                        'P' => 'Perempuan',
                        default => '-',
                    })
                    ->visibleFrom('md')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('boarding_konseling_mts_count')
                    ->label('Konseling')
                    ->badge()
                    ->alignCenter()
                    ->sortable(),
                Tables\Columns\TextColumn::make('boarding_konseling_terakhir_at')
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
                    ->modalHeading(fn (DataSiswa $record): string => 'Isi Konseling Boarding: '.$record->nama)
                    ->modalSubmitActionLabel('Simpan Konseling')
                    ->schema(static::noteFormSchema())
                    ->visible(fn (): bool => static::userCanManageEntries())
                    ->action(function (DataSiswa $record, array $data): void {
                        static::createNoteForStudent($record, $data);

                        Notification::make()
                            ->success()
                            ->title('Catatan konseling boarding berhasil disimpan.')
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
                        TextEntry::make('rombel_saat_ini')
                            ->label('Rombel')
                            ->placeholder('-'),
                        TextEntry::make('jk')
                            ->label('JK')
                            ->formatStateUsing(fn (?string $state): string => match ($state) {
                                'L' => 'Laki-laki',
                                'P' => 'Perempuan',
                                default => '-',
                            }),
                        TextEntry::make('status')
                            ->label('Status')
                            ->badge()
                            ->formatStateUsing(fn (?string $state): string => DataSiswa::statusLabel($state))
                            ->placeholder('-'),
                        TextEntry::make('jumlah_konseling')
                            ->label('Jumlah Konseling')
                            ->state(fn (DataSiswa $record): int => (int) ($record->boarding_konseling_mts_count
                                ?? $record->boardingKonselingMts()->visibleToUser(auth()->user())->count())),
                        TextEntry::make('konseling_terakhir')
                            ->label('Konseling Terakhir')
                            ->state(fn (DataSiswa $record): string => filled($record->boarding_konseling_terakhir_at)
                                ? \Illuminate\Support\Carbon::parse($record->boarding_konseling_terakhir_at)->format('d/m/Y')
                                : '-'),
                    ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            BoardingKonselingMtsRelationManager::class,
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return static::applyStudentSummaryAggregates(
            DataSiswa::applyVisibleScope(parent::getEloquentQuery(), auth()->user())
        );
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBoardingKonselingMts::route('/'),
            'view' => Pages\ViewBoardingKonselingMt::route('/{record}'),
        ];
    }

    protected static function shouldShowPamongOwnerField(): bool
    {
        $user = auth()->user();

        return $user instanceof User
            && ! $user->isBoardingPamong()
            && static::pamongOwnerColumnAvailable();
    }

    protected static function pamongOwnerColumnAvailable(): bool
    {
        return SchemaFacade::hasTable('boarding_konseling_mts')
            && SchemaFacade::hasColumn('boarding_konseling_mts', 'pamong_user_id');
    }

    protected static function applyStudentSummaryAggregates(Builder $query): Builder
    {
        return $query
            ->select([
                'id',
                'nama',
                'rombel_saat_ini',
                'jk',
                'status',
            ])
            ->withCount([
                'boardingKonselingMts' => fn (Builder $relationQuery): Builder => $relationQuery->visibleToUser(auth()->user()),
            ])
            ->withMax([
                'boardingKonselingMts as boarding_konseling_terakhir_at' => fn (Builder $relationQuery): Builder => $relationQuery->visibleToUser(auth()->user()),
            ], 'tanggal_konseling');
    }
}
