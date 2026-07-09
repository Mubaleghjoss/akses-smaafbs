<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasConfirmedDeleteActions;
use App\Filament\Concerns\HasModulePermissions;
use App\Filament\Concerns\HasOptimizedAdminTable;
use App\Filament\Resources\DataSiswaResource\Pages;
use App\Models\DataSiswa;
use App\Models\Rombel;
use App\Support\DataSiswa\DataSiswaSupport;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema as DatabaseSchema;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Throwable;

class DataSiswaResource extends Resource
{
    use HasConfirmedDeleteActions;
    use HasModulePermissions;
    use HasOptimizedAdminTable;

    /**
     * @var array<int, TextEntry>|null
     */
    protected static ?array $additionalDatabaseEntriesCache = null;

    protected static ?string $model = DataSiswa::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-users';

    protected static string|\UnitEnum|null $navigationGroup = 'Siswa';

    protected static ?int $navigationSort = 10;

    protected static ?string $navigationLabel = 'Data Siswa';

    protected static ?string $modelLabel = 'data siswa';

    protected static ?string $pluralModelLabel = 'Data Siswa';

    protected static ?string $permissionPrefix = 'data_siswa';

    protected static function dataTesColor(?string $field): string
    {
        return match (strtolower((string) $field)) {
            'kepribadian' => 'info',
            'gaya_belajar' => 'success',
            'profiling' => 'warning',
            'mbti' => 'primary',
            default => 'gray',
        };
    }

    protected static function dataTesInput(string $name, string $label, int $maxLength, ?string $placeholder = null): Forms\Components\TextInput
    {
        return Forms\Components\TextInput::make($name)
            ->label($label)
            ->placeholder($placeholder)
            ->helperText('Pilih dari saran yang sudah ada atau ketik manual. Nilai akan disimpan dalam huruf besar.')
            ->datalist(fn (): array => DataSiswaSupport::profileSuggestions($name, Auth::user()))
            ->dehydrateStateUsing(fn ($state): ?string => filled($state) ? Str::upper(trim((string) $state)) : null)
            ->maxLength($maxLength);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Identitas')
                    ->columns(['default' => 1, 'md' => 2])
                    ->schema([
                        Forms\Components\TextInput::make('nama')->required()->maxLength(100),
                        Forms\Components\Select::make('jk')
                            ->required()
                            ->options(['L' => 'L', 'P' => 'P']),
                        Forms\Components\Select::make('rombel_saat_ini')
                            ->label('Rombel')
                            ->searchable()
                            ->preload()
                            ->options(fn (): array => DataSiswaSupport::rombelOptions(Auth::user()))
                            ->createOptionForm([
                                Forms\Components\TextInput::make('nama')
                                    ->label('Nama Rombel')
                                    ->required()
                                    ->dehydrateStateUsing(fn ($state): string => Rombel::normalizeName($state))
                                    ->maxLength(50),
                            ])
                            ->createOptionUsing(function (array $data): string {
                                return Rombel::ensureFromName($data['nama'] ?? null)?->nama ?? trim((string) ($data['nama'] ?? ''));
                            })
                            ->rules(['nullable', 'string', 'max:50']),
                        Forms\Components\Select::make('status')
                            ->label('Status')
                            ->live()
                            ->options(DataSiswa::statusOptions())
                            ->default('aktif')
                            ->afterStateUpdated(function (Set $set, ?string $state): void {
                                if (! DataSiswa::isNonActiveStatus($state)) {
                                    $set('kategori_non_aktif', null);
                                    $set('alasan_non_aktif', null);
                                    $set('tanggal_non_aktif', null);
                                }
                            }),
                        Forms\Components\TextInput::make('nipd')->maxLength(20),
                        Forms\Components\TextInput::make('nisn')->maxLength(20),
                        Forms\Components\Select::make('kategori_non_aktif')
                            ->label('Kategori Non Aktif')
                            ->options(DataSiswa::nonActiveCategoryOptions())
                            ->visible(fn (Get $get): bool => DataSiswa::isNonActiveStatus($get('status')))
                            ->required(fn (Get $get): bool => DataSiswa::isNonActiveStatus($get('status')))
                            ->columnSpan(1),
                        Forms\Components\Textarea::make('alasan_non_aktif')
                            ->label('Alasan Non Aktif')
                            ->rows(4)
                            ->maxLength(1000)
                            ->visible(fn (Get $get): bool => DataSiswa::isNonActiveStatus($get('status')))
                            ->required(fn (Get $get): bool => DataSiswa::isNonActiveStatus($get('status')))
                            ->columnSpan(1),
                        Forms\Components\DatePicker::make('tanggal_non_aktif')
                            ->label('Tanggal Non Aktif')
                            ->visible(fn (Get $get): bool => DataSiswa::isNonActiveStatus($get('status')))
                            ->required(fn (Get $get): bool => DataSiswa::isNonActiveStatus($get('status')))
                            ->columnSpan(1),
                    ]),
                Section::make('Data Tes Siswa')
                    ->description('Nilai akan disimpan dan ditampilkan dalam huruf besar.')
                    ->columns(['default' => 1, 'md' => 2])
                    ->schema([
                        static::dataTesInput('kepribadian', 'Kepribadian', 100, 'Contoh: PLEGMATIS'),
                        static::dataTesInput('gaya_belajar', 'Gaya Belajar', 100, 'Contoh: VISUAL DAN KINESTETIK'),
                        static::dataTesInput('profiling', 'Profiling', 150, 'Contoh: EMOTIONAL QUOTIENT (EQ)'),
                        static::dataTesInput('mbti', 'MBTI', 20, 'Contoh: ENTP'),
                    ]),
                Section::make('Tagihan')
                    ->columns(['default' => 1, 'md' => 2])
                    ->schema([
                        Forms\Components\TextInput::make('billing_code')->label('Billing Code')->maxLength(32),
                        Forms\Components\TextInput::make('wa_ortu')->label('WA Ortu')->maxLength(32),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return static::optimizeAdminTable(
            $table,
            searchPlaceholder: 'Cari nama murid, rombel, NIPD, NISN, atau billing...',
            emptyStateHeading: 'Belum ada data siswa',
            emptyStateDescription: 'Gunakan import data siswa atau tambah data baru agar tabel ini terisi.'
        )
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->select([
                'id',
                'nama',
                'kepribadian',
                'gaya_belajar',
                'profiling',
                'mbti',
                'nisn',
                'nipd',
                'tempat_lahir',
                'tanggal_lahir',
                'rombel_saat_ini',
                'jk',
                'status',
                'kategori_non_aktif',
                'alasan_non_aktif',
                'tanggal_non_aktif',
                'billing_code',
                'updated_at',
            ]))
            ->defaultSort('nama')
            ->columns([
                Tables\Columns\TextColumn::make('nama')->label('Nama')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('kepribadian')
                    ->label('Kepribadian')
                    ->searchable()
                    ->badge()
                    ->color(fn (): string => static::dataTesColor('kepribadian'))
                    ->formatStateUsing(fn (?string $state): string => Str::upper((string) $state))
                    ->placeholder('-')
                    ->visibleFrom('md')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('gaya_belajar')
                    ->label('Gaya Belajar')
                    ->searchable()
                    ->badge()
                    ->color(fn (): string => static::dataTesColor('gaya_belajar'))
                    ->formatStateUsing(fn (?string $state): string => Str::upper((string) $state))
                    ->placeholder('-')
                    ->visibleFrom('md')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('profiling')
                    ->label('Profiling')
                    ->searchable()
                    ->badge()
                    ->color(fn (): string => static::dataTesColor('profiling'))
                    ->formatStateUsing(fn (?string $state): string => Str::upper((string) $state))
                    ->wrap()
                    ->placeholder('-')
                    ->visibleFrom('md'),
                Tables\Columns\TextColumn::make('mbti')
                    ->label('MBTI')
                    ->searchable()
                    ->formatStateUsing(fn (?string $state): string => Str::upper((string) $state))
                    ->badge()
                    ->color(fn (): string => static::dataTesColor('mbti'))
                    ->placeholder('-')
                    ->visibleFrom('md')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('nisn')->label('NISN')->searchable(),
                Tables\Columns\TextColumn::make('nipd')->label('NIPD')->searchable()->visibleFrom('md'),
                Tables\Columns\TextColumn::make('tempat_lahir')->label('Tempat Lahir')->searchable()->visibleFrom('md'),
                Tables\Columns\TextColumn::make('tanggal_lahir')
                    ->label('Tanggal Lahir')
                    ->date()
                    ->placeholder('-'),
                Tables\Columns\TextColumn::make('rombel_saat_ini')->label('Rombel')->searchable()->visibleFrom('md'),
                Tables\Columns\TextColumn::make('angkatan_label')
                    ->label('Angkatan')
                    ->state(fn (DataSiswa $record): string => DataSiswaSupport::extractAngkatan($record->rombel_saat_ini) ?? '-')
                    ->visibleFrom('md')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('jk')->label('JK')->badge()->visibleFrom('md'),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => DataSiswa::statusLabel($state))
                    ->visibleFrom('md')
                    ->color(fn (?string $state): string => match (strtolower((string) $state)) {
                        'aktif' => 'success',
                        'alumni' => 'warning',
                        'pindah' => 'info',
                        'keluar' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('kategori_non_aktif_label')
                    ->label('Kategori Non Aktif')
                    ->state(fn (DataSiswa $record): string => DataSiswa::nonActiveCategoryLabel($record->status, $record->kategori_non_aktif))
                    ->visibleFrom('md')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('alasan_non_aktif')
                    ->label('Alasan Non Aktif')
                    ->limit(50)
                    ->wrap()
                    ->visibleFrom('md')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('tanggal_non_aktif')
                    ->label('Tanggal Non Aktif')
                    ->date()
                    ->placeholder('-')
                    ->visibleFrom('md')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('billing_code')->label('Billing')->searchable()->visibleFrom('md')->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')->dateTime()->sortable()->visibleFrom('md')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        '__blank' => 'Belum Ada Status (SPMB)',
                    ] + DataSiswa::statusOptions())
                    ->query(function (Builder $query, array $data): Builder {
                        $value = $data['value'] ?? null;

                        if ($value === null || $value === '') {
                            return $query;
                        }

                        return $value === '__blank'
                            ? $query->where(fn (Builder $subQuery): Builder => $subQuery->whereNull('status')->orWhere('status', ''))
                            : $query->where('status', $value);
                    }),
                Tables\Filters\SelectFilter::make('jk')
                    ->label('JK')
                    ->options([
                        'L' => 'Laki-laki',
                        'P' => 'Perempuan',
                    ]),
                Tables\Filters\Filter::make('angkatan')
                    ->form([
                        Forms\Components\Select::make('value')
                            ->label('Angkatan')
                            ->options(fn (): array => DataSiswaSupport::angkatanOptions(auth()->user())),
                    ])
                    ->indicateUsing(fn (array $data): ?string => filled($data['value'] ?? null) ? 'Angkatan: '.$data['value'] : null)
                    ->query(function (Builder $query, array $data): Builder {
                        if (! filled($data['value'] ?? null)) {
                            return $query;
                        }

                        return $query->where('rombel_saat_ini', 'like', '%'.$data['value'].'%');
                    }),
                Tables\Filters\SelectFilter::make('rombel_saat_ini')
                    ->label('Rombel')
                    ->options(fn (): array => [
                        '__blank' => 'Belum Ada Rombel/Kelas',
                    ] + DataSiswaSupport::rombelOptions(auth()->user()))
                    ->query(function (Builder $query, array $data): Builder {
                        $value = $data['value'] ?? null;

                        if ($value === null || $value === '') {
                            return $query;
                        }

                        return $value === '__blank'
                            ? $query->where(fn (Builder $subQuery): Builder => $subQuery->whereNull('rombel_saat_ini')->orWhere('rombel_saat_ini', ''))
                            : $query->where('rombel_saat_ini', $value);
                    }),
                Tables\Filters\SelectFilter::make('kategori_non_aktif')
                    ->label('Kategori Non Aktif')
                    ->options(DataSiswa::nonActiveCategoryOptions()),
                Tables\Filters\SelectFilter::make('kepribadian')
                    ->label('Kepribadian')
                    ->options(fn (): array => DataSiswaSupport::profileOptions('kepribadian', auth()->user())),
                Tables\Filters\SelectFilter::make('gaya_belajar')
                    ->label('Gaya Belajar')
                    ->options(fn (): array => DataSiswaSupport::profileOptions('gaya_belajar', auth()->user())),
                Tables\Filters\SelectFilter::make('profiling')
                    ->label('Profiling')
                    ->options(fn (): array => DataSiswaSupport::profileOptions('profiling', auth()->user())),
                Tables\Filters\SelectFilter::make('mbti')
                    ->label('MBTI')
                    ->options(fn (): array => DataSiswaSupport::profileOptions('mbti', auth()->user())),
                Tables\Filters\TernaryFilter::make('data_tes_siswa')
                    ->label('Lihat Data Tes Siswa')
                    ->placeholder('Semua siswa')
                    ->trueLabel('Sudah ada data tes')
                    ->falseLabel('Belum ada data tes')
                    ->queries(
                        true: fn (Builder $query): Builder => $query->where(function (Builder $subQuery): void {
                            $subQuery
                                ->whereNotNull('kepribadian')->where('kepribadian', '!=', '')
                                ->orWhere(fn (Builder $query): Builder => $query->whereNotNull('gaya_belajar')->where('gaya_belajar', '!=', ''))
                                ->orWhere(fn (Builder $query): Builder => $query->whereNotNull('profiling')->where('profiling', '!=', ''))
                                ->orWhere(fn (Builder $query): Builder => $query->whereNotNull('mbti')->where('mbti', '!=', ''));
                        }),
                        false: fn (Builder $query): Builder => $query
                            ->where(fn (Builder $subQuery): Builder => $subQuery->whereNull('kepribadian')->orWhere('kepribadian', ''))
                            ->where(fn (Builder $subQuery): Builder => $subQuery->whereNull('gaya_belajar')->orWhere('gaya_belajar', ''))
                            ->where(fn (Builder $subQuery): Builder => $subQuery->whereNull('profiling')->orWhere('profiling', ''))
                            ->where(fn (Builder $subQuery): Builder => $subQuery->whereNull('mbti')->orWhere('mbti', '')),
                    ),
            ])
            ->recordUrl(fn (DataSiswa $record): string => static::getUrl('view', ['record' => $record]))
            ->actions([
                ViewAction::make(),
                EditAction::make(),
                static::makeDeleteTableAction('data siswa'),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    static::makeDeleteBulkTableAction(),
                ]),
            ]);
    }

    public static function canDelete(Model $record): bool
    {
        if (! $record instanceof DataSiswa) {
            return false;
        }

        $linkedAttributes = collect([
            'boarding_rapots_exists',
            'boarding_pencapaian_exists',
            'boarding_arsip_mt_exists',
            'boarding_konseling_mts_exists',
            'boarding_keuangan_siswa_exists',
            'prestasis_exists',
            'berkas_siswas_exists',
        ]);

        $hasLinkedAttributes = $linkedAttributes->every(fn (string $attribute): bool => array_key_exists($attribute, $record->getAttributes()));
        $hasLinkedData = $linkedAttributes->contains(function (string $attribute) use ($record): bool {
            return array_key_exists($attribute, $record->getAttributes()) && (bool) $record->getAttribute($attribute);
        });

        return static::userCanModule('manage')
            && ! $hasLinkedData
            && ($hasLinkedAttributes || (
                ! $record->boardingRapots()->exists()
                && ! $record->boardingPencapaian()->exists()
                && ! $record->boardingArsipMt()->exists()
                && ! $record->boardingKonselingMts()->exists()
                && ! $record->boardingKeuanganSiswa()->exists()
                && ! $record->prestasis()->exists()
                && ! $record->berkasSiswas()->exists()
            ));
    }

    public static function getEloquentQuery(): Builder
    {
        return DataSiswa::applyVisibleScope(parent::getEloquentQuery(), auth()->user())
            ->withExists([
                'boardingRapots',
                'boardingPencapaian',
                'boardingArsipMt',
                'boardingKonselingMts',
                'boardingKeuanganSiswa',
                'prestasis',
                'berkasSiswas',
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->columns(['default' => 1, 'md' => 2])
            ->schema([
                Section::make('Ringkasan Siswa')
                    ->columns(['default' => 1, 'md' => 2])
                    ->schema([
                        TextEntry::make('nama')
                            ->label('Nama')
                            ->placeholder('-'),
                        TextEntry::make('status')
                            ->label('Status')
                            ->badge()
                            ->formatStateUsing(fn (?string $state): string => DataSiswa::statusLabel($state))
                            ->color(fn (?string $state): string => match (strtolower((string) $state)) {
                                'aktif' => 'success',
                                'alumni' => 'warning',
                                'pindah' => 'info',
                                'keluar' => 'danger',
                                default => 'gray',
                            })
                            ->placeholder('-'),
                        TextEntry::make('jk')
                            ->label('Jenis Kelamin')
                            ->formatStateUsing(fn (?string $state): string => match (strtoupper((string) $state)) {
                                'L' => 'Laki-laki',
                                'P' => 'Perempuan',
                                default => '-',
                            })
                            ->placeholder('-'),
                        TextEntry::make('rombel_saat_ini')
                            ->label('Rombel Saat Ini')
                            ->placeholder('-'),
                        TextEntry::make('angkatan')
                            ->label('Angkatan')
                            ->state(fn (DataSiswa $record): string => DataSiswaSupport::extractAngkatan($record->rombel_saat_ini) ?? '-')
                            ->placeholder('-'),
                    ]),
                Section::make('Identitas & Kontak')
                    ->columns(['default' => 1, 'md' => 2])
                    ->schema([
                        TextEntry::make('nipd')->label('NIPD')->placeholder('-'),
                        TextEntry::make('nisn')->label('NISN')->placeholder('-'),
                        TextEntry::make('billing_code')->label('Billing Code')->placeholder('-'),
                        TextEntry::make('wa_ortu')->label('WA Ortu')->placeholder('-'),
                    ]),
                Section::make('Data Tes Siswa')
                    ->columns(['default' => 1, 'md' => 2])
                    ->schema([
                        TextEntry::make('kepribadian')
                            ->label('Kepribadian')
                            ->badge()
                            ->color(fn (): string => static::dataTesColor('kepribadian'))
                            ->formatStateUsing(fn (?string $state): string => Str::upper((string) $state))
                            ->placeholder('-'),
                        TextEntry::make('gaya_belajar')
                            ->label('Gaya Belajar')
                            ->badge()
                            ->color(fn (): string => static::dataTesColor('gaya_belajar'))
                            ->formatStateUsing(fn (?string $state): string => Str::upper((string) $state))
                            ->placeholder('-'),
                        TextEntry::make('profiling')
                            ->label('Profiling')
                            ->badge()
                            ->color(fn (): string => static::dataTesColor('profiling'))
                            ->formatStateUsing(fn (?string $state): string => Str::upper((string) $state))
                            ->placeholder('-'),
                        TextEntry::make('mbti')
                            ->label('MBTI')
                            ->badge()
                            ->color(fn (): string => static::dataTesColor('mbti'))
                            ->formatStateUsing(fn (?string $state): string => Str::upper((string) $state))
                            ->placeholder('-'),
                    ]),
                Section::make('Kelahiran & Status Non Aktif')
                    ->columns(['default' => 1, 'md' => 2])
                    ->schema([
                        TextEntry::make('tempat_lahir')->label('Tempat Lahir')->placeholder('-'),
                        TextEntry::make('tanggal_lahir')->label('Tanggal Lahir')->date()->placeholder('-'),
                        TextEntry::make('kategori_non_aktif')
                            ->label('Kategori Non Aktif')
                            ->state(fn (DataSiswa $record): string => DataSiswa::nonActiveCategoryLabel($record->status, $record->kategori_non_aktif))
                            ->placeholder('-'),
                        TextEntry::make('tanggal_non_aktif')->label('Tanggal Non Aktif')->date()->placeholder('-'),
                        TextEntry::make('alasan_non_aktif')
                            ->label('Alasan Non Aktif')
                            ->placeholder('-')
                            ->columnSpanFull(),
                    ]),
                Section::make('Ringkasan Relasi')
                    ->columns(['default' => 1, 'md' => 2])
                    ->schema([
                        TextEntry::make('prestasis_count')
                            ->label('Jumlah Prestasi')
                            ->state(fn (DataSiswa $record): int => static::safeRelationCount($record, 'prestasis')),
                        TextEntry::make('boarding_rapots_count')
                            ->label('Jumlah Rapot Boarding')
                            ->state(fn (DataSiswa $record): int => static::safeRelationCount($record, 'boardingRapots')),
                        TextEntry::make('boarding_konseling_count')
                            ->label('Jumlah Konseling Boarding')
                            ->state(fn (DataSiswa $record): int => static::safeRelationCount($record, 'boardingKonselingMts')),
                        TextEntry::make('boarding_perizinan_count')
                            ->label('Jumlah Perizinan Boarding')
                            ->state(fn (DataSiswa $record): int => static::safeRelationCount($record, 'boardingPerizinanSiswas')),
                        TextEntry::make('boarding_pencapaian_tersedia')
                            ->label('Data Pencapaian Boarding')
                            ->state(fn (DataSiswa $record): string => static::safeRelationExists($record, 'boardingPencapaian') ? 'Tersedia' : 'Belum Ada'),
                        TextEntry::make('boarding_arsip_mt_tersedia')
                            ->label('Arsip MT Boarding')
                            ->state(fn (DataSiswa $record): string => static::safeRelationExists($record, 'boardingArsipMt') ? 'Tersedia' : 'Belum Ada'),
                        TextEntry::make('boarding_keuangan_tersedia')
                            ->label('Data Keuangan Boarding')
                            ->state(fn (DataSiswa $record): string => static::safeRelationExists($record, 'boardingKeuanganSiswa') ? 'Tersedia' : 'Belum Ada'),
                    ]),
                Section::make('Kolom Database Lainnya')
                    ->description('Menampilkan kolom siswa lain yang tersedia di database saat ini.')
                    ->columns(['default' => 1, 'md' => 2])
                    ->schema(static::additionalDatabaseEntries()),
                Section::make('Metadata')
                    ->columns(['default' => 1, 'md' => 2])
                    ->schema([
                        TextEntry::make('created_at')->label('Dibuat')->dateTime()->placeholder('-'),
                        TextEntry::make('updated_at')->label('Diperbarui')->dateTime()->placeholder('-'),
                    ]),
            ]);
    }

    /**
     * @return array<int, TextEntry>
     */
    protected static function additionalDatabaseEntries(): array
    {
        if (static::$additionalDatabaseEntriesCache !== null) {
            return static::$additionalDatabaseEntriesCache;
        }

        $table = (new DataSiswa)->getTable();

        if (! DatabaseSchema::hasTable($table)) {
            return [];
        }

        $excludedColumns = [
            'id',
            'nama',
            'kepribadian',
            'gaya_belajar',
            'profiling',
            'mbti',
            'status',
            'jk',
            'rombel_saat_ini',
            'nipd',
            'nisn',
            'billing_code',
            'wa_ortu',
            'tempat_lahir',
            'tanggal_lahir',
            'kategori_non_aktif',
            'tanggal_non_aktif',
            'alasan_non_aktif',
            'created_at',
            'updated_at',
        ];

        return static::$additionalDatabaseEntriesCache = collect(DatabaseSchema::getColumnListing($table))
            ->reject(fn (string $column): bool => in_array($column, $excludedColumns, true))
            ->map(fn (string $column): TextEntry => TextEntry::make($column)
                ->label(Str::of($column)->replace('_', ' ')->title()->toString())
                ->placeholder('-'))
            ->values()
            ->all();
    }

    protected static function safeRelationCount(DataSiswa $record, string $relation): int
    {
        $countAttribute = Str::snake($relation).'_count';

        if (array_key_exists($countAttribute, $record->getAttributes())) {
            return (int) $record->getAttribute($countAttribute);
        }

        if ($record->relationLoaded($relation)) {
            $loadedRelation = $record->getRelation($relation);

            return is_iterable($loadedRelation) ? count($loadedRelation) : 0;
        }

        try {
            return $record->{$relation}()->count();
        } catch (Throwable) {
            return 0;
        }
    }

    protected static function safeRelationExists(DataSiswa $record, string $relation): bool
    {
        $existsAttribute = Str::snake($relation).'_exists';

        if (array_key_exists($existsAttribute, $record->getAttributes())) {
            return (bool) $record->getAttribute($existsAttribute);
        }

        if ($record->relationLoaded($relation)) {
            $loadedRelation = $record->getRelation($relation);

            if ($loadedRelation instanceof \Illuminate\Support\Collection) {
                return $loadedRelation->isNotEmpty();
            }

            return $loadedRelation !== null;
        }

        try {
            return $record->{$relation}()->exists();
        } catch (Throwable) {
            return false;
        }
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageDataSiswas::route('/'),
            'spmb-sync' => Pages\SyncSpmbStudents::route('/sync-spmb'),
            'view' => Pages\ViewDataSiswa::route('/{record}'),
        ];
    }
}
