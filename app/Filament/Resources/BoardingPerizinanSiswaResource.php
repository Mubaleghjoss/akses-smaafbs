<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasModulePermissions;
use App\Filament\Concerns\HasOptimizedAdminTable;
use App\Filament\Resources\BoardingPerizinanSiswaResource\Pages;
use App\Filament\Resources\BoardingPerizinanSiswaResource\RelationManagers\BoardingPerizinanSiswasRelationManager;
use App\Models\BoardingPerizinanSiswa;
use App\Models\DataSiswa;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema as SchemaFacade;

class BoardingPerizinanSiswaResource extends Resource
{
    use HasModulePermissions;
    use HasOptimizedAdminTable;

    protected static ?bool $requiredTablesAvailable = null;

    protected static ?string $model = DataSiswa::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-calendar-days';

    protected static string|\UnitEnum|null $navigationGroup = 'Perizinan';

    protected static ?string $navigationLabel = 'Perizinan Siswa';

    protected static ?string $modelLabel = 'perizinan siswa';

    protected static ?string $pluralModelLabel = 'Perizinan Siswa';

    protected static ?int $navigationSort = 45;

    protected static ?string $permissionPrefix = 'boarding_perizinan';

    public static function canAccess(): bool
    {
        return static::$requiredTablesAvailable ??= SchemaFacade::hasTable('data_siswa')
            && SchemaFacade::hasTable('boarding_perizinan_siswas')
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
    public static function permitFormSchema(): array
    {
        return [
            Section::make('Isi Perizinan Boarding')
                ->columns(['default' => 1, 'md' => 2])
                ->schema([
                    Forms\Components\TextInput::make('judul_izin')
                        ->label('Judul Izin')
                        ->datalist(fn (): array => static::leaveTitleSuggestions())
                        ->helperText('Judul yang pernah dipakai akan tampil sebagai saran untuk input berikutnya.')
                        ->maxLength(150)
                        ->required(),
                    Forms\Components\DatePicker::make('tanggal_izin')
                        ->label('Tanggal Jemput / Izin Keluar')
                        ->default(now())
                        ->required(),
                    Forms\Components\TimePicker::make('waktu_izin')
                        ->label('Waktu Jemput (Opsional)')
                        ->seconds(false)
                        ->visible(fn (): bool => BoardingPerizinanSiswa::returnFieldColumnAvailable('waktu_izin')),
                    Forms\Components\Textarea::make('detail_izin')
                        ->label('Detail Perizinan (Opsional)')
                        ->rows(4)
                        ->columnSpanFull()
                        ->visible(fn (): bool => BoardingPerizinanSiswa::returnFieldColumnAvailable('detail_izin')),
                    Forms\Components\Radio::make('approval_mode')
                        ->label('Yang Mengizinkan')
                        ->options([
                            'akun' => 'Pilih akun',
                            'manual' => 'Isi manual',
                        ])
                        ->helperText('Pilih akun sistem jika tersedia. Jika tidak ada, isi nama manual.')
                        ->default('akun')
                        ->afterStateHydrated(function (Forms\Components\Radio $component, mixed $record, mixed $state): void {
                            if (filled($state)) {
                                return;
                            }

                            if ($record instanceof BoardingPerizinanSiswa && $record->diizinkan_oleh_user_id) {
                                $component->state('akun');

                                return;
                            }

                            if ($record instanceof BoardingPerizinanSiswa && filled($record->diizinkan_oleh_nama)) {
                                $component->state('manual');

                                return;
                            }

                            $component->state('akun');
                        })
                        ->afterStateUpdated(function (Get $get, Set $set, ?string $state): void {
                            if ($state === 'manual') {
                                $set('diizinkan_oleh_user_id', null);

                                return;
                            }

                            if (blank($get('diizinkan_oleh_user_id')) && auth()->check()) {
                                $set('diizinkan_oleh_user_id', auth()->id());
                            }

                            $set('diizinkan_oleh_nama', null);
                        })
                        ->live()
                        ->dehydrated(false)
                        ->columnSpanFull()
                        ->visible(fn (): bool => BoardingPerizinanSiswa::approvalUserColumnAvailable() || BoardingPerizinanSiswa::approvalNameColumnAvailable()),
                    Forms\Components\Select::make('diizinkan_oleh_user_id')
                        ->label('Akun Yang Mengizinkan')
                        ->searchable()
                        ->getSearchResultsUsing(fn (string $search): array => User::searchNameOptions($search))
                        ->getOptionLabelUsing(fn ($value): ?string => User::resolveNameOptionLabel($value))
                        ->default(fn (): ?int => auth()->id())
                        ->visible(fn (Get $get): bool => BoardingPerizinanSiswa::approvalUserColumnAvailable() && ($get('approval_mode') ?? 'akun') === 'akun')
                        ->required(fn (Get $get): bool => BoardingPerizinanSiswa::approvalUserColumnAvailable() && ($get('approval_mode') ?? 'akun') === 'akun')
                        ->dehydrated(fn (Get $get): bool => BoardingPerizinanSiswa::approvalUserColumnAvailable() && ($get('approval_mode') ?? 'akun') === 'akun'),
                    Forms\Components\TextInput::make('diizinkan_oleh_nama')
                        ->label('Nama Yang Mengizinkan')
                        ->maxLength(150)
                        ->placeholder('Contoh: Orang tua, wali, atau pamong')
                        ->visible(fn (Get $get): bool => BoardingPerizinanSiswa::approvalNameColumnAvailable() && ($get('approval_mode') ?? 'akun') === 'manual')
                        ->required(fn (Get $get): bool => BoardingPerizinanSiswa::approvalNameColumnAvailable() && ($get('approval_mode') ?? 'akun') === 'manual')
                        ->dehydrated(fn (Get $get): bool => BoardingPerizinanSiswa::approvalNameColumnAvailable() && ($get('approval_mode') ?? 'akun') === 'manual'),
                ]),
        ];
    }

    /**
     * @return array<int, Forms\Components\Component|Section>
     */
    public static function returnFormSchema(): array
    {
        return [
            Section::make('Lengkapi Kepulangan')
                ->columns(['default' => 1, 'md' => 2])
                ->schema([
                    Forms\Components\DatePicker::make('tanggal_kembali')
                        ->label('Tanggal Kembali')
                        ->required(),
                    Forms\Components\TimePicker::make('waktu_kembali')
                        ->label('Waktu Kembali (Opsional)')
                        ->seconds(false)
                        ->visible(fn (): bool => BoardingPerizinanSiswa::returnFieldColumnAvailable('waktu_kembali')),
                    Forms\Components\TextInput::make('kafaroh_keterlambatan')
                        ->label('Kafaroh Keterlambatan (Opsional)')
                        ->maxLength(150)
                        ->visible(fn (): bool => BoardingPerizinanSiswa::returnFieldColumnAvailable('kafaroh_keterlambatan')),
                    Forms\Components\Textarea::make('detail_kembali')
                        ->label('Detail Kedatangan Kembali (Opsional)')
                        ->rows(4)
                        ->columnSpanFull()
                        ->visible(fn (): bool => BoardingPerizinanSiswa::returnFieldColumnAvailable('detail_kembali') || BoardingPerizinanSiswa::returnFieldColumnAvailable('catatan_kembali')),
                ]),
        ];
    }

    public static function userCanManageEntries(): bool
    {
        return static::userCanModule('manage');
    }

    public static function createPermitForStudent(DataSiswa $student, array $data): void
    {
        $payload = [
            'judul_izin' => trim((string) ($data['judul_izin'] ?? '')),
            'tanggal_izin' => $data['tanggal_izin'],
        ];

        if (BoardingPerizinanSiswa::returnFieldColumnAvailable('waktu_izin')) {
            $payload['waktu_izin'] = $data['waktu_izin'] ?? null;
        }

        if (BoardingPerizinanSiswa::returnFieldColumnAvailable('detail_izin')) {
            $payload['detail_izin'] = filled($data['detail_izin'] ?? null)
                ? trim((string) $data['detail_izin'])
                : null;
        }

        if (BoardingPerizinanSiswa::approvalUserColumnAvailable()) {
            $payload['diizinkan_oleh_user_id'] = $data['diizinkan_oleh_user_id'] ?? null;
        }

        if (BoardingPerizinanSiswa::approvalNameColumnAvailable()) {
            $payload['diizinkan_oleh_nama'] = filled($data['diizinkan_oleh_nama'] ?? null)
                ? trim((string) $data['diizinkan_oleh_nama'])
                : null;
        }

        $student->boardingPerizinanSiswas()->create($payload);
    }

    public static function table(Table $table): Table
    {
        return static::optimizeAdminTable(
            $table,
            searchPlaceholder: 'Cari murid atau rombel boarding...',
            emptyStateHeading: 'Belum ada data murid untuk perizinan boarding',
            emptyStateDescription: 'Daftar siswa boarding akan tampil di sini, lalu izin keluar bisa dicatat per murid.'
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
                Tables\Columns\TextColumn::make('boarding_perizinan_siswas_count')
                    ->label('Perizinan')
                    ->badge()
                    ->alignCenter()
                    ->sortable(),
                Tables\Columns\TextColumn::make('boarding_perizinan_terakhir_at')
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
                Action::make('isiPerizinan')
                    ->label('Isi Perizinan')
                    ->icon('heroicon-o-pencil-square')
                    ->color('primary')
                    ->modalHeading(fn (DataSiswa $record): string => 'Isi Perizinan Boarding: '.$record->nama)
                    ->modalSubmitActionLabel('Simpan Perizinan')
                    ->schema(static::permitFormSchema())
                    ->visible(fn (): bool => static::userCanManageEntries())
                    ->action(function (DataSiswa $record, array $data): void {
                        static::createPermitForStudent($record, $data);

                        Notification::make()
                            ->success()
                            ->title('Perizinan boarding berhasil disimpan.')
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
                        TextEntry::make('jumlah_perizinan')
                            ->label('Jumlah Perizinan')
                            ->state(fn (DataSiswa $record): int => (int) ($record->boarding_perizinan_siswas_count
                                ?? $record->boardingPerizinanSiswas()->visibleToUser(auth()->user())->count())),
                        TextEntry::make('perizinan_aktif')
                            ->label('Masih Keluar')
                            ->state(fn (DataSiswa $record): int => (int) ($record->boarding_perizinan_pending_count
                                ?? static::pendingHistoryQuery($record)->count())),
                        TextEntry::make('perizinan_terakhir')
                            ->label('Perizinan Terakhir')
                            ->state(fn (DataSiswa $record): string => filled($record->boarding_perizinan_terakhir_at)
                                ? Carbon::parse($record->boarding_perizinan_terakhir_at)->format('d/m/Y')
                                : '-'),
                    ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            BoardingPerizinanSiswasRelationManager::class,
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return static::applyStudentSummaryAggregates(
            DataSiswa::applyVisibleScope(parent::getEloquentQuery(), auth()->user())
        );
    }

    public static function statusLabel(?string $status): string
    {
        return match ($status) {
            'selesai' => 'Sudah Kembali',
            default => 'Belum Kembali',
        };
    }

    public static function leaveSummaryDescription(BoardingPerizinanSiswa $record): ?string
    {
        $parts = array_filter([
            $record->tanggal_izin?->translatedFormat('d M Y'),
            filled($record->waktu_izin) ? Carbon::parse($record->waktu_izin)->format('H:i') : null,
            filled($record->detail_izin) ? $record->detail_izin : null,
        ]);

        return $parts !== [] ? implode(' | ', $parts) : null;
    }

    public static function returnPrimaryLabel(BoardingPerizinanSiswa $record): string
    {
        if (blank($record->tanggal_kembali)) {
            return 'Belum diisi';
        }

        $parts = array_filter([
            $record->tanggal_kembali?->translatedFormat('d M Y'),
            filled($record->waktu_kembali) ? Carbon::parse($record->waktu_kembali)->format('H:i') : null,
        ]);

        return 'Kembali: '.implode(' • ', $parts);
    }

    public static function returnDetailLabel(BoardingPerizinanSiswa $record): ?string
    {
        $details = array_filter([
            filled($record->kafaroh_keterlambatan) ? 'Kafaroh: '.$record->kafaroh_keterlambatan : null,
            filled($record->detail_kembali) ? 'Keterangan: '.$record->detail_kembali : null,
        ]);

        if ($details === []) {
            return blank($record->tanggal_kembali) ? 'Belum ada data kepulangan.' : null;
        }

        return implode(' | ', $details);
    }

    public static function returnSummaryLabel(BoardingPerizinanSiswa $record): string
    {
        $summary = static::returnPrimaryLabel($record);
        $details = static::returnDetailLabel($record);

        return $details ? $summary.' | '.$details : $summary;
    }

    public static function approvalLabel(BoardingPerizinanSiswa $record): string
    {
        if (filled($record->diizinkanOlehUser?->name)) {
            return $record->diizinkanOlehUser->name;
        }

        if (filled($record->diizinkan_oleh_nama)) {
            return $record->diizinkan_oleh_nama;
        }

        return 'Belum diisi';
    }

    public static function approvalSourceLabel(BoardingPerizinanSiswa $record): ?string
    {
        if (filled($record->diizinkanOlehUser?->name)) {
            return 'Dipilih dari akun sistem';
        }

        if (filled($record->diizinkan_oleh_nama)) {
            return 'Diisi manual';
        }

        return 'Belum dicatat';
    }

    public static function applyApprovalSearch(Builder $query, string $search): Builder
    {
        $search = trim($search);

        if ($search === '') {
            return $query;
        }

        return $query->where(function (Builder $approvalQuery) use ($search): void {
            $hasPreviousCondition = false;

            if (BoardingPerizinanSiswa::approvalUserColumnAvailable()) {
                $approvalQuery->whereHas('diizinkanOlehUser', function (Builder $userQuery) use ($search): void {
                    $userQuery->where('name', 'like', "%{$search}%");
                });

                $hasPreviousCondition = true;
            }

            if (BoardingPerizinanSiswa::approvalNameColumnAvailable()) {
                $method = $hasPreviousCondition ? 'orWhere' : 'where';

                $approvalQuery->{$method}('diizinkan_oleh_nama', 'like', "%{$search}%");
            }
        });
    }

    public static function applyApprovalSourceFilter(Builder $query, ?string $source): Builder
    {
        return match ($source) {
            'akun' => static::applyApprovalPresenceFilter($query, user: true, manual: false),
            'manual' => static::applyApprovalPresenceFilter($query, user: false, manual: true),
            'kosong' => static::applyApprovalPresenceFilter($query, user: false, manual: false),
            default => $query,
        };
    }

    protected static function applyApprovalPresenceFilter(Builder $query, bool $user, bool $manual): Builder
    {
        return $query->where(function (Builder $approvalQuery) use ($user, $manual): void {
            if (BoardingPerizinanSiswa::approvalUserColumnAvailable()) {
                $user ? $approvalQuery->whereNotNull('diizinkan_oleh_user_id') : $approvalQuery->whereNull('diizinkan_oleh_user_id');
            }

            if (BoardingPerizinanSiswa::approvalNameColumnAvailable()) {
                if ($manual) {
                    $approvalQuery->whereNotNull('diizinkan_oleh_nama')->where('diizinkan_oleh_nama', '!=', '');
                } else {
                    $approvalQuery->where(function (Builder $nameQuery): void {
                        $nameQuery
                            ->whereNull('diizinkan_oleh_nama')
                            ->orWhere('diizinkan_oleh_nama', '');
                    });
                }
            }
        });
    }

    public static function leaveTitleSuggestions(): array
    {
        $user = auth()->user();

        return Cache::remember(
            'boarding_perizinan_title_suggestions:'.($user?->getKey() ?? 'guest'),
            now()->addMinutes(10),
            fn (): array => BoardingPerizinanSiswa::query()
                ->visibleToUser($user)
                ->whereNotNull('judul_izin')
                ->where('judul_izin', '!=', '')
                ->select('judul_izin')
                ->distinct()
                ->orderByDesc('updated_at')
                ->limit(20)
                ->pluck('judul_izin')
                ->values()
                ->all(),
        );
    }

    public static function historyQueryForStudent(?int $siswaId, mixed $user): Builder
    {
        $query = BoardingPerizinanSiswa::query()
            ->select(static::historySelectColumns())
            ->with([
                'diizinkanOlehUser:id,name',
                'siswa:id,nama',
            ])
            ->visibleToUser($user)
            ->when($siswaId, fn (Builder $builder) => $builder->where('siswa_id', $siswaId));

        if (BoardingPerizinanSiswa::statusPerizinanColumnAvailable()) {
            $query->orderByRaw("CASE WHEN status_perizinan = 'pending' THEN 0 ELSE 1 END");
        }

        return $query
            ->orderByDesc('tanggal_izin')
            ->orderByDesc('id');
    }

    public static function updateApproval(BoardingPerizinanSiswa $record, array $data): void
    {
        $approvalMode = $data['approval_mode'] ?? 'akun';

        $record->update([
            'diizinkan_oleh_user_id' => BoardingPerizinanSiswa::approvalUserColumnAvailable() && $approvalMode === 'akun'
                ? ($data['diizinkan_oleh_user_id'] ?? auth()->id())
                : null,
            'diizinkan_oleh_nama' => BoardingPerizinanSiswa::approvalNameColumnAvailable() && $approvalMode === 'manual'
                ? (filled($data['diizinkan_oleh_nama'] ?? null) ? trim((string) $data['diizinkan_oleh_nama']) : null)
                : null,
        ]);
    }

    public static function approvalActionSchema(): array
    {
        return [
            Forms\Components\Radio::make('approval_mode')
                ->label('Yang Mengizinkan')
                ->options([
                    'akun' => 'Pilih akun',
                    'manual' => 'Isi manual',
                ])
                ->default('akun')
                ->live()
                ->required(),
            Forms\Components\Select::make('diizinkan_oleh_user_id')
                ->label('Akun Yang Mengizinkan')
                ->searchable()
                ->getSearchResultsUsing(fn (string $search): array => User::searchNameOptions($search))
                ->getOptionLabelUsing(fn ($value): ?string => User::resolveNameOptionLabel($value))
                ->default(fn (): ?int => auth()->id())
                ->visible(fn (Get $get): bool => BoardingPerizinanSiswa::approvalUserColumnAvailable() && ($get('approval_mode') ?? 'akun') === 'akun')
                ->required(fn (Get $get): bool => BoardingPerizinanSiswa::approvalUserColumnAvailable() && ($get('approval_mode') ?? 'akun') === 'akun')
                ->dehydrated(fn (Get $get): bool => BoardingPerizinanSiswa::approvalUserColumnAvailable() && ($get('approval_mode') ?? 'akun') === 'akun'),
            Forms\Components\TextInput::make('diizinkan_oleh_nama')
                ->label('Nama Yang Mengizinkan')
                ->maxLength(150)
                ->placeholder('Contoh: Orang tua, wali, atau pamong')
                ->visible(fn (Get $get): bool => BoardingPerizinanSiswa::approvalNameColumnAvailable() && ($get('approval_mode') ?? 'akun') === 'manual')
                ->required(fn (Get $get): bool => BoardingPerizinanSiswa::approvalNameColumnAvailable() && ($get('approval_mode') ?? 'akun') === 'manual')
                ->dehydrated(fn (Get $get): bool => BoardingPerizinanSiswa::approvalNameColumnAvailable() && ($get('approval_mode') ?? 'akun') === 'manual'),
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBoardingPerizinanSiswas::route('/'),
            'view' => Pages\ViewBoardingPerizinanSiswa::route('/{record}'),
        ];
    }

    public static function historySelectColumns(): array
    {
        $columns = [
            'id',
            'siswa_id',
            'judul_izin',
            'tanggal_izin',
            'created_at',
            'updated_at',
        ];

        foreach ([
            'waktu_izin',
            'waktu_jemput',
            'detail_izin',
            'tanggal_kembali',
            'waktu_kembali',
            'detail_kembali',
            'catatan_kembali',
            'kafaroh_keterlambatan',
            'status_perizinan',
            'diizinkan_oleh_user_id',
            'diizinkan_oleh_nama',
        ] as $column) {
            if (BoardingPerizinanSiswa::returnFieldColumnAvailable($column)) {
                $columns[] = $column;
            }
        }

        return $columns;
    }

    protected static function pendingHistoryQuery(DataSiswa $record): Builder|\Illuminate\Database\Eloquent\Relations\HasMany
    {
        $query = $record->boardingPerizinanSiswas()->visibleToUser(auth()->user());

        if (BoardingPerizinanSiswa::statusPerizinanColumnAvailable()) {
            return $query->where('status_perizinan', 'pending');
        }

        return $query->whereNull('tanggal_kembali');
    }

    protected static function applyStudentSummaryAggregates(Builder $query): Builder
    {
        $user = auth()->user();

        return $query
            ->select([
                'id',
                'nama',
                'rombel_saat_ini',
                'jk',
                'status',
            ])
            ->withCount([
                'boardingPerizinanSiswas' => fn (Builder $relationQuery): Builder => $relationQuery->visibleToUser($user),
                'boardingPerizinanSiswas as boarding_perizinan_pending_count' => fn (Builder $relationQuery): Builder => BoardingPerizinanSiswa::statusPerizinanColumnAvailable()
                    ? $relationQuery->visibleToUser($user)->where('status_perizinan', 'pending')
                    : $relationQuery->visibleToUser($user)->whereNull('tanggal_kembali'),
            ])
            ->withMax([
                'boardingPerizinanSiswas as boarding_perizinan_terakhir_at' => fn (Builder $relationQuery): Builder => $relationQuery->visibleToUser($user),
            ], 'tanggal_izin');
    }
}
