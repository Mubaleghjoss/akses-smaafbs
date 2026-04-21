<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasConfirmedDeleteActions;
use App\Filament\Concerns\HasModulePermissions;
use App\Filament\Concerns\HasOptimizedAdminTable;
use App\Filament\Resources\UksRecordResource\Pages;
use App\Models\DataSiswa;
use App\Models\UksRecord;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class UksRecordResource extends Resource
{
    use HasConfirmedDeleteActions;
    use HasModulePermissions;
    use HasOptimizedAdminTable;

    protected static ?string $model = UksRecord::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-heart';

    protected static string|\UnitEnum|null $navigationGroup = 'UKS';

    protected static ?string $navigationLabel = 'Rekam UKS';

    protected static ?string $modelLabel = 'rekam UKS';

    protected static ?string $pluralModelLabel = 'Rekam UKS';

    protected static ?int $navigationSort = 10;

    protected static ?string $permissionPrefix = 'uks_records';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Kunjungan UKS')
                    ->description('Pilih siswa yang sudah terdaftar, lalu catat keluhan dan penanganan secara ringkas.')
                    ->columns(['default' => 1, 'md' => 2])
                    ->schema([
                        Forms\Components\Select::make('student_picker')
                            ->label('Pilih Siswa')
                            ->searchable()
                            ->getSearchResultsUsing(fn (string $search): array => static::searchStudentOptions($search))
                            ->getOptionLabelUsing(fn ($value): ?string => static::studentOptionLabel($value))
                            ->live()
                            ->dehydrated(false)
                            ->required()
                            ->visibleOn('create')
                            ->afterStateUpdated(function (Set $set, ?string $state): void {
                                $student = filled($state) ? DataSiswa::query()->find($state, ['nama', 'rombel_saat_ini']) : null;

                                $set('nama_siswa', $student?->nama);
                                $set('kelas', $student?->rombel_saat_ini);
                            })
                            ->helperText('Nama dan rombel akan diisi otomatis dari data siswa aktif.'),
                        Forms\Components\Placeholder::make('student_summary')
                            ->label('Data Siswa')
                            ->content(fn (Get $get): string => trim(($get('nama_siswa') ?: 'Belum dipilih').($get('kelas') ? ' - '.$get('kelas') : '')))
                            ->visibleOn('create'),
                        Forms\Components\TextInput::make('nama_siswa')
                            ->label('Nama Siswa')
                            ->maxLength(150)
                            ->visibleOn('edit'),
                        Forms\Components\TextInput::make('kelas')
                            ->label('Rombel')
                            ->maxLength(50)
                            ->default(null)
                            ->visibleOn('edit'),
                        Forms\Components\DatePicker::make('tanggal_sakit')
                            ->label('Tanggal Kunjungan')
                            ->default(now()->toDateString())
                            ->required(),
                        Forms\Components\TextInput::make('kategori')
                            ->label('Keluhan / Kategori')
                            ->placeholder('Contoh: Pusing, Demam, Nyeri perut')
                            ->maxLength(100)
                            ->required(),
                        Forms\Components\Textarea::make('penanganan')
                            ->label('Penanganan')
                            ->rows(3)
                            ->placeholder('Obat, istirahat, observasi, pulang, atau tindakan lain')
                            ->required()
                            ->columnSpanFull(),
                        Forms\Components\Textarea::make('catatan')
                            ->label('Catatan Tambahan')
                            ->rows(3)
                            ->placeholder('Opsional')
                            ->columnSpanFull(),
                    ]),
                Section::make('Pengukuran Opsional')
                    ->description('Isi hanya jika kunjungan UKS juga mencatat antropometri.')
                    ->collapsible()
                    ->collapsed()
                    ->columns(['default' => 1, 'md' => 3])
                    ->schema([
                        Forms\Components\TextInput::make('berat_badan')
                            ->label('Berat Badan (kg)')
                            ->numeric()
                            ->step('0.01')
                            ->minValue(0),
                        Forms\Components\TextInput::make('tinggi_badan')
                            ->label('Tinggi Badan (cm)')
                            ->numeric()
                            ->step('0.01')
                            ->minValue(0),
                        Forms\Components\TextInput::make('lingkar_kepala')
                            ->label('Lingkar Kepala (cm)')
                            ->numeric()
                            ->step('0.01')
                            ->minValue(0),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return static::optimizeAdminTable(
            $table,
            searchPlaceholder: 'Cari nama siswa, kelas, kategori, atau penanganan...',
            emptyStateHeading: 'Belum ada rekam UKS',
            emptyStateDescription: 'Import data sakit atau tambah catatan UKS baru agar histori kesehatan siswa tersusun.'
        )
            ->defaultSort('tanggal_sakit', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('tanggal_sakit')
                    ->label('Tanggal')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('nama_siswa')
                    ->label('Siswa')
                    ->searchable()
                    ->description(fn (UksRecord $record): string => $record->kelas ?: 'Rombel belum tercatat')
                    ->wrap(),
                Tables\Columns\TextColumn::make('kategori')
                    ->label('Keluhan')
                    ->searchable()
                    ->badge()
                    ->description(fn (UksRecord $record): string => filled($record->catatan) ? (string) str($record->catatan)->limit(70) : 'Tanpa catatan tambahan')
                    ->wrap(),
                Tables\Columns\TextColumn::make('penanganan')
                    ->label('Penanganan')
                    ->searchable()
                    ->limit(70)
                    ->wrap(),
                Tables\Columns\TextColumn::make('pengukuran_ringkas')
                    ->label('Antropometri')
                    ->state(function (UksRecord $record): string {
                        $parts = array_filter([
                            $record->berat_badan !== null ? 'BB '.number_format((float) $record->berat_badan, 2, ',', '.').' kg' : null,
                            $record->tinggi_badan !== null ? 'TB '.number_format((float) $record->tinggi_badan, 2, ',', '.').' cm' : null,
                            $record->lingkar_kepala !== null ? 'LK '.number_format((float) $record->lingkar_kepala, 2, ',', '.').' cm' : null,
                        ]);

                        return $parts !== [] ? implode(' | ', $parts) : '-';
                    })
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('kategori')
                    ->label('Kategori')
                    ->options(fn (): array => UksRecord::kategoriOptions()),
                Tables\Filters\SelectFilter::make('kelas')
                    ->label('Kelas')
                    ->options(fn (): array => UksRecord::kelasOptions()),
            ])
            ->actions([
                EditAction::make(),
                static::makeDeleteTableAction('rekam UKS'),
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
            'index' => Pages\ListUksRecords::route('/'),
            'anthropometry' => Pages\ManageUksAnthropometry::route('/antropometri'),
            'create' => Pages\CreateUksRecord::route('/create'),
            'edit' => Pages\EditUksRecord::route('/{record}/edit'),
        ];
    }

    protected static function studentOptions(): array
    {
        return DataSiswa::query()
            ->visibleToUser(auth()->user())
            ->where('status', 'aktif')
            ->orderBy('nama')
            ->get(['id', 'nama', 'rombel_saat_ini'])
            ->mapWithKeys(fn (DataSiswa $student): array => [
                $student->id => trim($student->nama.($student->rombel_saat_ini ? ' - '.$student->rombel_saat_ini : '')),
            ])
            ->all();
    }

    protected static function searchStudentOptions(string $search): array
    {
        return DataSiswa::query()
            ->visibleToUser(auth()->user())
            ->where('status', 'aktif')
            ->where(function ($query) use ($search): void {
                $query
                    ->where('nama', 'like', '%'.$search.'%')
                    ->orWhere('nisn', 'like', '%'.$search.'%')
                    ->orWhere('rombel_saat_ini', 'like', '%'.$search.'%');
            })
            ->orderBy('nama')
            ->limit(50)
            ->get(['id', 'nama', 'rombel_saat_ini'])
            ->mapWithKeys(fn (DataSiswa $student): array => [
                (string) $student->getKey() => trim($student->nama.($student->rombel_saat_ini ? ' - '.$student->rombel_saat_ini : '')),
            ])
            ->all();
    }

    protected static function studentOptionLabel(mixed $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        $student = DataSiswa::query()
            ->visibleToUser(auth()->user())
            ->where('status', 'aktif')
            ->find($value, ['id', 'nama', 'rombel_saat_ini']);

        if (! $student) {
            return null;
        }

        return trim($student->nama.($student->rombel_saat_ini ? ' - '.$student->rombel_saat_ini : ''));
    }
}
