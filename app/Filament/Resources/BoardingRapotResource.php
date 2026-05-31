<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasModulePermissions;
use App\Filament\Concerns\HasOptimizedAdminTable;
use App\Filament\Resources\BoardingRapotResource\Pages;
use App\Models\BoardingPencapaian;
use App\Models\BoardingRapot;
use App\Models\DataSiswa;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema as SchemaFacade;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\Rule;

class BoardingRapotResource extends Resource
{
    use HasModulePermissions;
    use HasOptimizedAdminTable;

    protected static ?string $model = BoardingRapot::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-text';

    protected static string|\UnitEnum|null $navigationGroup = 'Boarding';

    protected static ?string $navigationLabel = 'Rapot';

    protected static ?string $modelLabel = 'rapot boarding';

    protected static ?string $pluralModelLabel = 'Rapot Boarding';

    protected static ?int $navigationSort = 10;

    protected static ?string $permissionPrefix = 'boarding_rapot';

    public static function form(Schema $schema): Schema
    {
        return $schema->schema(static::rapotFormComponents());
    }

    /**
     * @return array<int, mixed>
     */
    protected static function rapotFormComponents(): array
    {
        return [
                Section::make('Data Rapot Boarding')
                    ->description('Rapot boarding dapat disinkron otomatis dari data siswa, pencapaian target, konseling, dan keuangan kas.')
                    ->columns(['default' => 1, 'md' => 2])
                    ->schema([
                        Forms\Components\Select::make('siswa_id')
                            ->label('Murid')
                            ->relationship(
                                name: 'siswa',
                                titleAttribute: 'nama',
                                modifyQueryUsing: fn (Builder $query) => DataSiswa::applyVisibleScope($query, auth()->user())->orderBy('nama')
                            )
                            ->getOptionLabelFromRecordUsing(
                                fn (DataSiswa $record): string => trim($record->nama.' - '.($record->rombel_saat_ini ?: 'Tanpa rombel'))
                            )
                            ->searchable()
                            ->required(),
                        Forms\Components\Select::make('pamong_user_id')
                            ->label('Pamong Penanggung Jawab')
                            ->relationship(
                                name: 'pamongUser',
                                titleAttribute: 'name',
                                modifyQueryUsing: fn (Builder $query) => User::boardingPamongQuery()->orderBy('name')
                            )
                            ->searchable()
                            ->getSearchResultsUsing(fn (string $search): array => User::searchBoardingPamongOptions($search))
                            ->getOptionLabelUsing(fn ($value): ?string => User::resolveNameOptionLabel($value))
                            ->live()
                            ->afterStateUpdated(function (Set $set, ?string $state): void {
                                $set('wali_pamong_nama', User::query()->whereKey($state)->value('name'));
                            })
                            ->default(fn (): ?int => auth()->user()?->isBoardingPamong() ? auth()->id() : null)
                            ->disabled(fn (): bool => (bool) auth()->user()?->isBoardingPamong())
                            ->dehydrated()
                            ->required(),
                        Forms\Components\TextInput::make('periode_tahun')
                            ->label('Periode Tahun')
                            ->required()
                            ->maxLength(20)
                            ->default(fn (): string => BoardingRapot::defaultPeriodeTahun()),
                        Forms\Components\Select::make('semester')
                            ->required()
                            ->default(fn (): string => BoardingRapot::defaultSemester())
                            ->options([
                                'ganjil' => 'Ganjil',
                                'genap' => 'Genap',
                            ])
                            ->rules([
                                fn (Get $get, ?BoardingRapot $record) => Rule::unique('boarding_rapots')
                                    ->where(fn (Builder $query) => $query
                                        ->where('siswa_id', $get('siswa_id'))
                                        ->where('periode_tahun', $get('periode_tahun')))
                                    ->ignore($record),
                            ]),
                        Forms\Components\DatePicker::make('tanggal_rapot')
                            ->label('Tanggal Rapot')
                            ->default(now()),
                        Forms\Components\Select::make('status_rapot')
                            ->label('Status Rapot')
                            ->required()
                            ->default('draft')
                            ->options(BoardingRapot::statusOptions()),
                        Forms\Components\TextInput::make('nomor_dokumen')
                            ->label('Nomor Dokumen')
                            ->maxLength(50)
                            ->placeholder('Contoh: RB/BOARDING/2026/001'),
                        Forms\Components\Placeholder::make('kelas_boarding_view')
                            ->label('Kelas Boarding Otomatis')
                            ->content(fn (?BoardingRapot $record): string => static::kelasBoardingAutoLabel($record)),
                        Forms\Components\Select::make('kelas_boarding_override')
                            ->label('Kelas Boarding Manual')
                            ->placeholder('Ikuti otomatis')
                            ->options(BoardingRapot::boardingClassOptions())
                            ->dehydrated(fn (): bool => static::rapotColumnAvailable('kelas_boarding_override'))
                            ->helperText(fn (?BoardingRapot $record, Get $get): HtmlString => static::kelasBoardingManualHelper(
                                $record,
                                BoardingRapot::normalizeBoardingClassKey($get('kelas_boarding_override')),
                            ))
                            ->visible(fn (): bool => static::rapotColumnAvailable('kelas_boarding_override')),
                        Forms\Components\Checkbox::make('konfirmasi_kelas_boarding_manual')
                            ->label(fn (?BoardingRapot $record, Get $get): string => static::kelasBoardingConfirmationLabel(
                                BoardingRapot::normalizeBoardingClassKey($get('kelas_boarding_override')),
                            ))
                            ->dehydrated(false)
                            ->rules(fn (?BoardingRapot $record, Get $get): array => static::kelasBoardingOverrideChanged(
                                $record,
                                BoardingRapot::normalizeBoardingClassKey($get('kelas_boarding_override')),
                            ) ? ['accepted'] : [])
                            ->validationMessages([
                                'accepted' => 'Konfirmasi perubahan kelas boarding wajib dicentang.',
                            ])
                            ->visible(fn (?BoardingRapot $record, Get $get): bool => static::kelasBoardingOverrideChanged(
                                $record,
                                BoardingRapot::normalizeBoardingClassKey($get('kelas_boarding_override')),
                            ))
                            ->columnSpanFull(),
                        Forms\Components\Placeholder::make('generated_at_view')
                            ->label('Sinkron Terakhir')
                            ->content(fn (?BoardingRapot $record): string => $record?->generated_at?->translatedFormat('d M Y H:i') ?? 'Belum pernah disinkronkan'),
                    ]),
                Section::make('Administrasi Rapot Manual')
                    ->description('Tambahkan baris pertanyaan dan jawaban yang perlu tampil di bagian Administrasi Rapot.')
                    ->schema([
                        Forms\Components\Repeater::make('administrasi_rapot_items')
                            ->label('Pertanyaan dan Jawaban')
                            ->visible(fn (): bool => static::rapotColumnAvailable('administrasi_rapot_items'))
                            ->dehydrated(fn (): bool => static::rapotColumnAvailable('administrasi_rapot_items'))
                            ->addActionLabel('Tambah Pertanyaan')
                            ->reorderableWithButtons()
                            ->collapsed()
                            ->schema([
                                Forms\Components\TextInput::make('question')
                                    ->label('Pertanyaan')
                                    ->required()
                                    ->maxLength(120),
                                Forms\Components\Textarea::make('answer')
                                    ->label('Jawaban')
                                    ->rows(2)
                                    ->required()
                                    ->maxLength(500),
                            ])
                            ->columns(['default' => 1, 'md' => 2])
                            ->columnSpanFull(),
                    ]),
                Section::make('Tanda Tangan dan Legalisasi')
                    ->description('Nilai ini menjadi fallback jika Pengaturan Dokumen Rapot tidak mengisi nama pada slot terkait.')
                    ->columns(['default' => 1, 'md' => 2])
                    ->schema([
                        Forms\Components\TextInput::make('wali_pamong_nama')
                            ->label('Nama Tanda Tangan 1')
                            ->maxLength(100),
                        Forms\Components\TextInput::make('kepala_boarding_nama')
                            ->label('Nama Tanda Tangan 2')
                            ->maxLength(100),
                        Forms\Components\TextInput::make('mudir_asrama_nama')
                            ->label('Nama Tanda Tangan 3')
                            ->helperText('Untuk slot Pamong, isi nama di sini jika perlu berbeda dari pamong penanggung jawab.')
                            ->maxLength(100),
                        Forms\Components\TextInput::make('tempat_cetak')
                            ->label('Tempat Cetak')
                            ->maxLength(100),
                    ]),
                Section::make('Ringkasan Naratif Rapot')
                    ->description('Konten ini tersimpan di rekapan data rapot, tidak masuk format rapot ringkas A4.')
                    ->columns(['default' => 1, 'md' => 2])
                    ->schema([
                        Forms\Components\Textarea::make('ringkasan_pencapaian')
                            ->label('Ringkasan Pencapaian')
                            ->rows(5)
                            ->columnSpanFull(),
                        Forms\Components\Textarea::make('catatan_pamong')
                            ->label('Catatan Pamong')
                            ->rows(4)
                            ->columnSpanFull(),
                        Forms\Components\Textarea::make('rekomendasi_tindak_lanjut')
                            ->label('Rekomendasi Tindak Lanjut')
                            ->rows(4)
                            ->columnSpanFull(),
                    ]),
                Section::make('Materi Rapot dari Pencapaian Target')
                    ->description('Rapot hanya menampilkan materi yang dipilih di Pencapaian Target murid.')
                    ->columns(['default' => 1, 'md' => 2])
                    ->schema([
                        Forms\Components\Placeholder::make('materi_rapot_aktif')
                            ->label('Target Materi Aktif')
                            ->content(fn (?BoardingRapot $record): string => BoardingPencapaian::materiRapotScopeLabel(static::activeMateriRapotScope($record))),
                        Forms\Components\Placeholder::make('catatan_saran_aktif')
                            ->label('Catatan / Saran Materi Aktif')
                            ->content(fn (?BoardingRapot $record): HtmlString => static::catatanSaranPlaceholder($record, static::activeMateriRapotScope($record))),
                    ]),
            ];
    }

    public static function table(Table $table): Table
    {
        return static::optimizeAdminTable(
            $table,
            searchPlaceholder: 'Cari nama murid, rombel, pamong, atau nomor rapot...',
            emptyStateHeading: 'Belum ada rapot boarding',
            emptyStateDescription: 'Rapot akan otomatis dibuat dari pencapaian target yang sudah terisi.'
        )
            ->defaultSort('updated_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('siswa.nama')
                    ->label('Murid')
                    ->searchable()
                    ->sortable()
                    ->description(fn (BoardingRapot $record): string => collect([
                        $record->siswa?->rombel_saat_ini ?: 'Tanpa rombel',
                        $record->pamongUser?->name ? 'Pamong '.$record->pamongUser->name : null,
                        trim(($record->periode_tahun ?: '-').' / '.ucfirst((string) $record->semester)),
                        BoardingRapot::statusOptions()[$record->status_rapot] ?? $record->status_rapot,
                    ])->filter()->implode(' | '))
                    ->wrap(),
                Tables\Columns\TextColumn::make('siswa.rombel_saat_ini')
                    ->label('Rombel')
                    ->searchable()
                    ->visibleFrom('md')
                    ->wrap(),
                Tables\Columns\TextColumn::make('pamongUser.name')
                    ->label('Pamong')
                    ->searchable()
                    ->visibleFrom('md')
                    ->wrap(),
                Tables\Columns\TextColumn::make('periode_tahun')
                    ->label('Periode')
                    ->searchable()
                    ->sortable()
                    ->visibleFrom('lg'),
                Tables\Columns\TextColumn::make('semester')
                    ->label('Semester')
                    ->badge()
                    ->visibleFrom('md'),
                Tables\Columns\TextColumn::make('nomor_dokumen')
                    ->label('Nomor')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->wrap(),
                Tables\Columns\TextColumn::make('kelas_boarding')
                    ->label('Kelas Boarding')
                    ->state(fn (BoardingRapot $record): string => static::kelasBoardingLabel($record))
                    ->description(fn (BoardingRapot $record): ?string => static::kelasBoardingDescription($record))
                    ->visibleFrom('lg'),
                Tables\Columns\TextColumn::make('status_rapot')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => BoardingRapot::statusOptions()[$state] ?? ($state ?: '-')),
                Tables\Columns\TextColumn::make('generated_at')
                    ->label('Sinkron')
                    ->since()
                    ->visibleFrom('lg'),
                Tables\Columns\TextColumn::make('tanggal_rapot')
                    ->label('Tanggal')
                    ->date()
                    ->sortable()
                    ->visibleFrom('lg'),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Diupdate')
                    ->since()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('periode_tahun')
                    ->label('Periode')
                    ->options(BoardingRapot::periodeTahunOptions()),
                Tables\Filters\SelectFilter::make('semester')
                    ->options([
                        'ganjil' => 'Ganjil',
                        'genap' => 'Genap',
                    ]),
                Tables\Filters\SelectFilter::make('status_rapot')
                    ->label('Status')
                    ->options(BoardingRapot::statusOptions()),
                Tables\Filters\SelectFilter::make('pamong_user_id')
                    ->label('Pamong')
                    ->relationship(
                        name: 'pamongUser',
                        titleAttribute: 'name',
                        modifyQueryUsing: fn (Builder $query) => User::boardingPamongQuery()->orderBy('name')
                    )
                    ->visible(fn (): bool => ! auth()->user()?->isBoardingPamong()),
            ])
            ->actions([
                Action::make('edit_rapot')
                    ->label('Edit')
                    ->icon('heroicon-o-pencil-square')
                    ->color('primary')
                    ->url(fn (BoardingRapot $record): string => route('admin.boarding-rapots.manual-edit', $record)),
                ActionGroup::make([
                    Action::make('sinkronkan')
                        ->label('Sinkronkan Data')
                        ->icon('heroicon-o-arrow-path')
                        ->color('gray')
                        ->action(function (BoardingRapot $record): void {
                            $record->syncFromSources(overwriteNarratives: true);
                        }),
                    Action::make('preview')
                        ->label('Preview Rapot')
                        ->icon('heroicon-o-eye')
                        ->color('info')
                        ->url(fn (BoardingRapot $record): string => route('admin.boarding-rapots.preview', $record))
                        ->openUrlInNewTab(),
                    Action::make('rekap_data')
                        ->label('Rekap Data Lengkap')
                        ->icon('heroicon-o-clipboard-document-list')
                        ->color('gray')
                        ->url(fn (BoardingRapot $record): string => route('admin.boarding-rapots.rekap', $record))
                        ->openUrlInNewTab(),
                    Action::make('cetak')
                        ->label('Cetak / PDF')
                        ->icon('heroicon-o-printer')
                        ->color('success')
                        ->url(fn (BoardingRapot $record): string => route('admin.boarding-rapots.print', $record))
                        ->openUrlInNewTab(),
                    Action::make('export_excel')
                        ->label('Export Excel')
                        ->icon('heroicon-o-document-arrow-down')
                        ->color('warning')
                        ->url(fn (BoardingRapot $record): string => route('admin.boarding-rapots.export', $record))
                        ->openUrlInNewTab(),
                    DeleteAction::make(),
                ])
                    ->label('Aksi')
                    ->icon('heroicon-o-ellipsis-horizontal')
                    ->color('gray'),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        $select = [
                'id',
                'siswa_id',
                'pamong_user_id',
                'periode_tahun',
                'semester',
                'nomor_dokumen',
                'predikat_boarding',
                'rekap_payload',
                'status_rapot',
                'generated_at',
                'tanggal_rapot',
                'ringkasan_pencapaian',
                'catatan_pamong',
                'rekomendasi_tindak_lanjut',
                'wali_pamong_nama',
                'kepala_boarding_nama',
                'mudir_asrama_nama',
                'tempat_cetak',
                'updated_at',
            ];

        foreach (['administrasi_rapot_items', 'kelas_boarding_override'] as $column) {
            if (static::rapotColumnAvailable($column)) {
                $select[] = $column;
            }
        }

        return parent::getEloquentQuery()
            ->select($select)
            ->with([
                'siswa:id,nama,rombel_saat_ini',
                'pamongUser:id,name',
            ])
            ->visibleToUser(auth()->user());
    }

    protected static function catatanSaranPlaceholder(?BoardingRapot $record, string $source): HtmlString
    {
        $rows = static::catatanSaranRows($record, $source);

        if ($rows === []) {
            $label = $source === BoardingPencapaian::MATERI_RAPOT_SCOPE_MT ? 'Materi MT' : 'Materi Boarding';

            return new HtmlString('<span class="text-sm text-gray-500">Belum ada Catatan dan Saran '.$label.' yang tersinkron.</span>');
        }

        $items = collect($rows)
            ->map(function (array $row): string {
                $targetName = e((string) ($row['target_name'] ?? '-'));
                $value = e((string) static::catatanSaranValue($row));
                $notes = trim((string) ($row['notes'] ?? ''));
                $notesHtml = $notes !== '' ? '<div class="text-gray-500">'.e($notes).'</div>' : '';

                return '<div class="rounded-lg border border-gray-200 px-3 py-2">'
                    .'<div class="font-medium text-gray-950">'.$targetName.'</div>'
                    .'<div class="text-gray-700">'.$value.'</div>'
                    .$notesHtml
                    .'</div>';
            })
            ->implode('');

        return new HtmlString('<div class="space-y-2 text-sm">'.$items.'</div>');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected static function catatanSaranRows(?BoardingRapot $record, string $source): array
    {
        $payload = static::rapotPayload($record);
        $groups = $source === BoardingPencapaian::MATERI_RAPOT_SCOPE_MT
            ? ($payload['pencapaian']['mt']['groups'] ?? [])
            : ($payload['pencapaian']['materi_boarding']['manual_groups'] ?? []);

        $rows = [];

        foreach ($groups as $group) {
            if (! is_array($group)) {
                continue;
            }

            $groupKey = (string) ($group['group'] ?? '');
            $groupTitle = strtolower((string) ($group['judul'] ?? ''));

            if ($groupKey !== 'catatan_saran' && ! str_contains($groupTitle, 'catatan')) {
                continue;
            }

            foreach ($group['rows'] ?? [] as $row) {
                if (! is_array($row)) {
                    continue;
                }

                $rows[] = $row;
            }
        }

        return $rows;
    }

    protected static function activeMateriRapotScope(?BoardingRapot $record): string
    {
        return BoardingPencapaian::normalizeMateriRapotScope(static::rapotPayload($record)['pencapaian']['materi_rapot_scope'] ?? null);
    }

    protected static function kelasBoardingLabel(?BoardingRapot $record): string
    {
        $kelasBoarding = static::rapotPayload($record)['rapot']['kelas_boarding'] ?? null;

        return filled($kelasBoarding) ? (string) $kelasBoarding : 'Akan terisi setelah rapot disinkronkan';
    }

    protected static function kelasBoardingAutoLabel(?BoardingRapot $record): string
    {
        $payload = static::rapotPayload($record);
        $auto = $payload['rapot']['kelas_boarding_auto'] ?? $payload['rapot']['kelas_boarding'] ?? null;

        return filled($auto) ? (string) $auto : 'Kelas Pegon Bacaan';
    }

    protected static function kelasBoardingDescription(BoardingRapot $record): ?string
    {
        $payload = static::rapotPayload($record);

        return filled($payload['rapot']['kelas_boarding_override_key'] ?? null) ? 'Manual' : 'Otomatis';
    }

    protected static function kelasBoardingOverrideChanged(?BoardingRapot $record, ?string $selectedKey): bool
    {
        if (! $record?->exists) {
            return filled($selectedKey);
        }

        return BoardingRapot::normalizeBoardingClassKey($record->kelas_boarding_override ?? null) !== $selectedKey;
    }

    protected static function kelasBoardingConfirmationLabel(?string $selectedKey): string
    {
        $targetLabel = $selectedKey ? (BoardingRapot::boardingClassOptions()[$selectedKey] ?? $selectedKey) : 'kelas otomatis';

        return 'Saya yakin merubah menjadi '.$targetLabel.', sebab ada materi kelas yang belum sesuai pada kelasnya.';
    }

    protected static function kelasBoardingManualHelper(?BoardingRapot $record, ?string $selectedKey): HtmlString
    {
        $payload = static::rapotPayload($record);
        $autoLabel = $payload['rapot']['kelas_boarding_auto'] ?? $payload['rapot']['kelas_boarding'] ?? 'Kelas Pegon Bacaan';
        $finalLabel = $selectedKey ? (BoardingRapot::boardingClassOptions()[$selectedKey] ?? $selectedKey) : $autoLabel;
        $rows = collect($payload['pencapaian']['materi_boarding']['hafalan'] ?? [])
            ->map(function (array $row): string {
                $judul = e((string) ($row['judul'] ?? '-'));
                $assessed = (int) ($row['assessed'] ?? 0);
                $total = (int) ($row['total'] ?? 0);
                $grade = e((string) ($row['grade_label'] ?? '-'));

                return '<li>'.$judul.': '.$assessed.' dari '.$total.' materi terisi, '.$grade.'</li>';
            })
            ->implode('');

        $rows = $rows !== '' ? '<ul class="list-disc ps-5">'.$rows.'</ul>' : '<div>Belum ada materi hafalan yang terisi.</div>';

        return new HtmlString(
            '<div class="space-y-2 text-sm">'
            .'<div>Kelas otomatis dari hafalan: <strong>'.e((string) $autoLabel).'</strong>.</div>'
            .'<div>Kelas yang akan tampil di rapot: <strong>'.e((string) $finalLabel).'</strong>.</div>'
            .'<div>Materi hafalan yang sudah terisi:</div>'
            .$rows
            .'</div>'
        );
    }

    protected static function rapotColumnAvailable(string $column): bool
    {
        return SchemaFacade::hasTable('boarding_rapots') && SchemaFacade::hasColumn('boarding_rapots', $column);
    }

    protected static function catatanSaranValue(array $row): string
    {
        $value = $row['grade'] ?? $row['capaian'] ?? null;

        return filled($value) ? (string) $value : 'Belum Diisi';
    }

    /**
     * @return array<string, mixed>
     */
    protected static function rapotPayload(?BoardingRapot $record): array
    {
        if (! $record || ! $record->exists) {
            return [];
        }

        $payload = $record->rekap_payload;

        if (! is_array($payload)) {
            $payload = BoardingRapot::query()
                ->whereKey($record->getKey())
                ->value('rekap_payload');
        }

        if (is_string($payload)) {
            $payload = json_decode($payload, true) ?: [];
        }

        return is_array($payload) ? $payload : [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageBoardingRapots::route('/'),
            'create' => Pages\CreateBoardingRapot::route('/create'),
            'edit' => Pages\EditBoardingRapot::route('/{record}/edit'),
        ];
    }
}
