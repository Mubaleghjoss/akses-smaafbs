<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasConfirmedDeleteActions;
use App\Filament\Concerns\HasModulePermissions;
use App\Filament\Concerns\HasOptimizedAdminTable;
use App\Filament\Resources\BkKasusResource\Pages;
use App\Models\BkKasus;
use App\Models\DataSiswa;
use App\Models\Rombel;
use App\Models\User;
use App\Support\Bk\BkKasusSiswaSync;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema as SchemaFacade;

class BkKasusResource extends Resource
{
    use HasConfirmedDeleteActions;
    use HasModulePermissions;
    use HasOptimizedAdminTable;

    protected static ?string $model = BkKasus::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-exclamation-triangle';

    protected static string|\UnitEnum|null $navigationGroup = 'Manajemen Sekolah';

    protected static ?string $navigationLabel = 'Laporan SIGAP';

    protected static ?string $modelLabel = 'laporan SIGAP';

    protected static ?string $pluralModelLabel = 'Laporan SIGAP';

    protected static ?int $navigationSort = 34;

    protected static ?string $permissionPrefix = 'bk_kasus';

    public static function canAccess(): bool
    {
        return static::tablesAvailable() && parent::canAccess();
    }

    public static function tablesAvailable(): bool
    {
        return SchemaFacade::hasTable('data_siswa')
            && SchemaFacade::hasTable('bk_kasus')
            && SchemaFacade::hasTable('bk_kasus_siswa');
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = auth()->user();

        if ($user instanceof User && $user->usesGuruPersonalScope()) {
            $query->where('created_by', $user->getKey());
        }

        return $query;
    }

    public static function canView($record): bool
    {
        return static::userCanModule('view') && static::userCanAccessRecord($record);
    }

    public static function canEdit($record): bool
    {
        return static::userCanModule('manage') && static::userCanAccessRecord($record);
    }

    public static function canDelete($record): bool
    {
        return static::userCanModule('manage') && static::userCanAccessRecord($record);
    }

    public static function canDeleteAny(): bool
    {
        $user = auth()->user();

        return static::userCanModule('manage')
            && ! ($user instanceof User && $user->usesGuruPersonalScope());
    }

    protected static function userCanAccessRecord($record): bool
    {
        $user = auth()->user();

        return ! ($user instanceof User && $user->usesGuruPersonalScope())
            || (int) $record->created_by === (int) $user->getKey();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Identitas Laporan')
                    ->description('Satu laporan bisa memuat banyak siswa dengan satu keterangan kasus yang sama.')
                    ->columns(['default' => 1, 'md' => 2])
                    ->schema([
                        Forms\Components\DatePicker::make('tanggal_kasus')
                            ->label('Tanggal Kasus')
                            ->default(now()->toDateString())
                            ->maxDate(now()->addDay())
                            ->required(),
                        Forms\Components\TextInput::make('judul_kasus')
                            ->label('Judul Kasus')
                            ->placeholder('Contoh: Terlambat masuk kelas jam pertama')
                            ->maxLength(180)
                            ->required(),
                        Forms\Components\Select::make('kategori')
                            ->label('Kategori')
                            ->options(BkKasus::kategoriOptions())
                            ->searchable()
                            ->placeholder('Pilih kategori'),
                        Forms\Components\Select::make('tingkat')
                            ->label('Tingkat Kasus')
                            ->options(BkKasus::tingkatOptions())
                            ->placeholder('Pilih tingkat'),
                        Forms\Components\TextInput::make('pelapor')
                            ->label('Pelapor')
                            ->placeholder('Nama guru / wali kelas yang melaporkan')
                            ->maxLength(120),
                    ]),
                Section::make('Keterangan Kasus')
                    ->schema([
                        Forms\Components\Textarea::make('keterangan_kasus')
                            ->label('Keterangan Kasus')
                            ->rows(5)
                            ->placeholder('Uraikan kejadian: apa, di mana, kapan, dan kondisi saat kasus terjadi.')
                            ->required()
                            ->columnSpanFull(),
                    ]),
                Section::make('Siswa Terlibat')
                    ->description('Cari berdasarkan nama, NISN, atau rombel. Bisa memilih lebih dari satu siswa.')
                    ->schema([
                        Forms\Components\Select::make('siswa_ids')
                            ->label('Daftar Siswa')
                            ->multiple()
                            ->searchable()
                            ->dehydrated(true)
                            ->getSearchResultsUsing(fn (string $search): array => static::searchStudentOptions($search))
                            ->getOptionLabelsUsing(fn (array $values): array => static::studentOptionLabels($values))
                            ->required()
                            ->minItems(1)
                            ->helperText('Snapshot rombel siswa disimpan otomatis agar rekap lama tidak berubah saat siswa naik kelas.')
                            ->columnSpanFull(),
                    ]),
                Section::make('Tindak Lanjut')
                    ->description('Satu tindak lanjut berlaku untuk seluruh siswa dalam laporan ini.')
                    ->columns(['default' => 1, 'md' => 2])
                    ->schema([
                        Forms\Components\Textarea::make('tindak_lanjut')
                            ->label('Tindak Lanjut')
                            ->rows(5)
                            ->placeholder('Pembinaan, panggilan orang tua, kesepakatan siswa, sanksi, atau rencana pendampingan.')
                            ->columnSpanFull(),
                        Forms\Components\Select::make('status_tindak_lanjut')
                            ->label('Status Tindak Lanjut')
                            ->options(BkKasus::statusOptions())
                            ->default(BkKasus::STATUS_BELUM)
                            ->live()
                            ->required(),
                        Forms\Components\DatePicker::make('tanggal_tindak_lanjut')
                            ->label('Tanggal Tindak Lanjut')
                            ->visible(fn (Get $get): bool => $get('status_tindak_lanjut') !== BkKasus::STATUS_BELUM),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return static::optimizeAdminTable(
            $table,
            searchPlaceholder: 'Cari judul kasus, keterangan, atau pelapor...',
            emptyStateHeading: 'Belum ada laporan SIGAP',
            emptyStateDescription: 'Tambah laporan baru untuk mencatat kasus beserta siswa yang terlibat dan tindak lanjutnya.'
        )
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->withCount('siswa'))
            ->defaultSort('tanggal_kasus', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('tanggal_kasus')
                    ->label('Tanggal')
                    ->date('d/m/Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('judul_kasus')
                    ->label('Kasus')
                    ->searchable()
                    ->description(fn (BkKasus $record): string => (string) str($record->keterangan_kasus)->limit(80))
                    ->wrap(),
                Tables\Columns\TextColumn::make('kategori')
                    ->label('Kategori')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => BkKasus::kategoriLabel($state))
                    ->toggleable(),
                Tables\Columns\TextColumn::make('tingkat')
                    ->label('Tingkat')
                    ->badge()
                    ->color(fn (?string $state): string => BkKasus::tingkatColor($state))
                    ->formatStateUsing(fn (?string $state): string => BkKasus::tingkatLabel($state))
                    ->toggleable(),
                Tables\Columns\TextColumn::make('siswa_count')
                    ->label('Siswa')
                    ->badge()
                    ->alignCenter()
                    ->sortable(),
                Tables\Columns\TextColumn::make('kelas_terlibat')
                    ->label('Kelas')
                    ->state(fn (BkKasus $record): string => implode(', ', $record->kelasTerlibat()) ?: '-')
                    ->wrap()
                    ->visibleFrom('md'),
                Tables\Columns\TextColumn::make('status_tindak_lanjut')
                    ->label('Tindak Lanjut')
                    ->badge()
                    ->color(fn (?string $state): string => BkKasus::statusColor($state))
                    ->formatStateUsing(fn (?string $state): string => BkKasus::statusLabel($state))
                    ->sortable(),
                Tables\Columns\TextColumn::make('pelapor')
                    ->label('Pelapor')
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('kategori')
                    ->label('Kategori')
                    ->options(BkKasus::kategoriOptions()),
                Tables\Filters\SelectFilter::make('tingkat')
                    ->label('Tingkat')
                    ->options(BkKasus::tingkatOptions()),
                Tables\Filters\SelectFilter::make('status_tindak_lanjut')
                    ->label('Status Tindak Lanjut')
                    ->options(BkKasus::statusOptions()),
                Tables\Filters\SelectFilter::make('rombel')
                    ->label('Kelas')
                    ->options(fn (): array => static::rombelFilterOptions())
                    ->query(function (Builder $query, array $data): Builder {
                        $value = trim((string) ($data['value'] ?? ''));

                        if ($value === '') {
                            return $query;
                        }

                        return $query->whereHas('siswa', function (Builder $siswa) use ($value): void {
                            $siswa
                                ->where('bk_kasus_siswa.rombel_snapshot', $value)
                                ->orWhere(function (Builder $fallback) use ($value): void {
                                    $fallback
                                        ->whereNull('bk_kasus_siswa.rombel_snapshot')
                                        ->where('data_siswa.rombel_saat_ini', $value);
                                });
                        });
                    }),
                Tables\Filters\Filter::make('rentang_tanggal')
                    ->label('Rentang Tanggal')
                    ->schema([
                        Forms\Components\DatePicker::make('dari')->label('Dari Tanggal'),
                        Forms\Components\DatePicker::make('sampai')->label('Sampai Tanggal'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                filled($data['dari'] ?? null),
                                fn (Builder $q): Builder => $q->whereDate('tanggal_kasus', '>=', $data['dari'])
                            )
                            ->when(
                                filled($data['sampai'] ?? null),
                                fn (Builder $q): Builder => $q->whereDate('tanggal_kasus', '<=', $data['sampai'])
                            );
                    }),
            ])
            ->recordUrl(fn (BkKasus $record): string => static::getUrl('view', ['record' => $record]))
            ->actions([
                ViewAction::make(),
                EditAction::make(),
                static::makeDeleteTableAction('laporan SIGAP'),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    static::makeDeleteBulkTableAction(),
                ]),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Ringkasan Kasus')
                    ->columns(['default' => 1, 'md' => 3])
                    ->schema([
                        TextEntry::make('tanggal_kasus')
                            ->label('Tanggal Kasus')
                            ->date('d/m/Y'),
                        TextEntry::make('kategori')
                            ->label('Kategori')
                            ->badge()
                            ->formatStateUsing(fn (?string $state): string => BkKasus::kategoriLabel($state)),
                        TextEntry::make('tingkat')
                            ->label('Tingkat')
                            ->badge()
                            ->color(fn (?string $state): string => BkKasus::tingkatColor($state))
                            ->formatStateUsing(fn (?string $state): string => BkKasus::tingkatLabel($state)),
                        TextEntry::make('judul_kasus')
                            ->label('Judul Kasus')
                            ->columnSpanFull(),
                        TextEntry::make('keterangan_kasus')
                            ->label('Keterangan Kasus')
                            ->columnSpanFull(),
                        TextEntry::make('pelapor')
                            ->label('Pelapor')
                            ->placeholder('-'),
                        TextEntry::make('petugas.name')
                            ->label('Dicatat Oleh')
                            ->placeholder('-'),
                        TextEntry::make('kelas_ringkas')
                            ->label('Kelas Terlibat')
                            ->state(fn (BkKasus $record): string => implode(', ', $record->kelasTerlibat()) ?: '-'),
                    ]),
                Section::make('Tindak Lanjut')
                    ->columns(['default' => 1, 'md' => 3])
                    ->schema([
                        TextEntry::make('status_tindak_lanjut')
                            ->label('Status')
                            ->badge()
                            ->color(fn (?string $state): string => BkKasus::statusColor($state))
                            ->formatStateUsing(fn (?string $state): string => BkKasus::statusLabel($state)),
                        TextEntry::make('tanggal_tindak_lanjut')
                            ->label('Tanggal Tindak Lanjut')
                            ->date('d/m/Y')
                            ->placeholder('-'),
                        TextEntry::make('jumlah_siswa')
                            ->label('Jumlah Siswa')
                            ->state(fn (BkKasus $record): int => $record->siswa()->count()),
                        TextEntry::make('tindak_lanjut')
                            ->label('Isi Tindak Lanjut')
                            ->placeholder('Belum ada tindak lanjut yang dicatat.')
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            BkKasusResource\RelationManagers\SiswaRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBkKasus::route('/'),
            'create' => Pages\CreateBkKasus::route('/create'),
            'view' => Pages\ViewBkKasus::route('/{record}'),
            'edit' => Pages\EditBkKasus::route('/{record}/edit'),
        ];
    }

    public static function syncStudents(BkKasus $record, array $siswaIds): void
    {
        BkKasusSiswaSync::sync($record, $siswaIds);
    }

    /**
     * @return array<int, string>
     */
    public static function searchStudentOptions(string $search): array
    {
        return DataSiswa::query()
            ->visibleToUser(auth()->user())
            ->where('status', 'aktif')
            ->where(function ($query) use ($search): void {
                $query
                    ->where('nama', 'like', '%'.$search.'%')
                    ->orWhere('nisn', 'like', '%'.$search.'%')
                    ->orWhere('nipd', 'like', '%'.$search.'%')
                    ->orWhere('rombel_saat_ini', 'like', '%'.$search.'%');
            })
            ->orderBy('nama')
            ->limit(50)
            ->get(['id', 'nama', 'rombel_saat_ini', 'nisn'])
            ->mapWithKeys(fn (DataSiswa $student): array => [
                $student->id => static::formatStudentLabel($student),
            ])
            ->all();
    }

    /**
     * @param  array<int, int|string>  $values
     * @return array<int, string>
     */
    public static function studentOptionLabels(array $values): array
    {
        return DataSiswa::query()
            ->whereIn('id', $values)
            ->orderBy('nama')
            ->get(['id', 'nama', 'rombel_saat_ini', 'nisn'])
            ->mapWithKeys(fn (DataSiswa $student): array => [
                $student->id => static::formatStudentLabel($student),
            ])
            ->all();
    }

    protected static function formatStudentLabel(DataSiswa $student): string
    {
        return collect([
            $student->nama,
            $student->rombel_saat_ini ?: 'Tanpa rombel',
            $student->nisn ? 'NISN '.$student->nisn : null,
        ])->filter()->implode(' - ');
    }

    /**
     * @return array<string, string>
     */
    protected static function rombelFilterOptions(): array
    {
        if (! Rombel::tableAvailable()) {
            return [];
        }

        return Rombel::query()
            ->where('is_active', true)
            ->orderBy('nama')
            ->pluck('nama', 'nama')
            ->all();
    }
}
