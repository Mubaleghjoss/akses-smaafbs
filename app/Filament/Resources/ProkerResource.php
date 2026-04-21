<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasConfirmedDeleteActions;
use App\Filament\Concerns\HasModulePermissions;
use App\Filament\Resources\ProkerResource\Pages;
use App\Filament\Resources\ProkerResource\RelationManagers\IndikatorsRelationManager;
use App\Filament\Resources\ProkerResource\RelationManagers\UpdatesRelationManager;
use App\Models\Proker;
use App\Models\ProkerBidang;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema as SchemaFacade;

class ProkerResource extends Resource
{
    use HasConfirmedDeleteActions;
    use HasModulePermissions;

    protected static ?string $model = Proker::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static string|\UnitEnum|null $navigationGroup = 'Manajemen Sekolah';

    protected static ?int $navigationSort = 20;

    protected static ?string $modelLabel = 'Proker';

    protected static ?string $pluralModelLabel = 'Proker';

    protected static ?string $permissionPrefix = 'proker';

    public static function form(Schema $schema): Schema
    {
        $hasImportColumns = static::hasImportColumns();

        $informasiSchema = [
            Forms\Components\Select::make('bidang_id')
                ->label('Bidang')
                ->required()
                ->searchable()
                ->getSearchResultsUsing(fn (string $search): array => static::searchBidangOptions($search))
                ->getOptionLabelUsing(fn ($value): ?string => static::resolveBidangLabel($value)),
        ];

        if ($hasImportColumns) {
            $informasiSchema[] = Forms\Components\TextInput::make('point_dari')
                ->label('Point Dari / Sumber')
                ->helperText('Contoh: KURIKULUM, HUMAS, SARPRAS')
                ->maxLength(150);

            $informasiSchema[] = Forms\Components\TextInput::make('nomor_urut')
                ->label('Nomor Urut')
                ->numeric()
                ->minValue(1);
        }

        $informasiSchema = [
            ...$informasiSchema,
            Forms\Components\TextInput::make('nama')
                ->label('Nama Proker')
                ->required()
                ->maxLength(255),
            Forms\Components\TextInput::make('periode_tahun')
                ->label('Tahun')
                ->required()
                ->numeric()
                ->default((int) now()->format('Y')),
            Forms\Components\TextInput::make('periode_label')
                ->label('Periode')
                ->placeholder('Contoh: 2026-2027')
                ->maxLength(100),
            Forms\Components\TextInput::make('penanggung_jawab')
                ->label('PIC / Penanggung Jawab')
                ->maxLength(255),
            Forms\Components\Select::make('prioritas')
                ->label('Prioritas')
                ->required()
                ->options([
                    'rendah' => 'Rendah',
                    'sedang' => 'Sedang',
                    'tinggi' => 'Tinggi',
                ])
                ->default('sedang'),
            Forms\Components\DatePicker::make('target_mulai')
                ->label('Target Mulai'),
            Forms\Components\DatePicker::make('target_selesai')
                ->label('Target Selesai'),
        ];

        $schemaComponents = [
            Section::make('Informasi Proker')
                ->columns(['default' => 1, 'md' => 2])
                ->schema($informasiSchema),
        ];

        if ($hasImportColumns) {
            $schemaComponents[] = Section::make('Jadwal, Anggaran, dan Keterangan')
                ->columns(['default' => 1, 'md' => 2])
                ->schema([
                    Forms\Components\KeyValue::make('jadwal_bulanan')
                        ->label('Jadwal Per Bulan')
                        ->keyLabel('Bulan')
                        ->valueLabel('Rencana')
                        ->addActionLabel('Tambah Jadwal')
                        ->reorderable()
                        ->columnSpanFull(),
                    Forms\Components\Textarea::make('jadwal_ringkas')
                        ->label('Ringkasan Jadwal')
                        ->rows(3)
                        ->columnSpanFull(),
                    Forms\Components\Textarea::make('waktu_pelaksanaan')
                        ->label('Waktu / Pola Pelaksanaan')
                        ->rows(3),
                    Forms\Components\TextInput::make('rab_global')
                        ->label('RAB Global')
                        ->maxLength(255),
                    Forms\Components\Textarea::make('keterangan')
                        ->label('Keterangan')
                        ->rows(4)
                        ->columnSpanFull(),
                ]);
        }

        $schemaComponents = [
            ...$schemaComponents,
            Section::make('Monitoring')
                ->columns(['default' => 1, 'md' => 2, 'xl' => 3])
                ->schema([
                    Forms\Components\Select::make('status')
                        ->label('Status')
                        ->required()
                        ->options([
                            'draft' => 'Draft',
                            'berjalan' => 'Berjalan',
                            'terkendala' => 'Terkendala',
                            'selesai' => 'Selesai',
                        ])
                        ->default('draft'),
                    Forms\Components\TextInput::make('progress_persen')
                        ->label('Progress (%)')
                        ->numeric()
                        ->minValue(0)
                        ->maxValue(100)
                        ->default(0),
                    Forms\Components\DateTimePicker::make('last_monitored_at')
                        ->label('Monitoring Terakhir'),
                ]),
            Section::make('Deskripsi dan Evaluasi')
                ->schema([
                    Forms\Components\Textarea::make('deskripsi')
                        ->label('Deskripsi Proker')
                        ->rows(4),
                    Forms\Components\Textarea::make('output_target')
                        ->label('Output / Target')
                        ->rows(4),
                    Forms\Components\Textarea::make('evaluasi_akhir')
                        ->label('Evaluasi Umum')
                        ->rows(4),
                    Forms\Components\Textarea::make('tindak_lanjut_umum')
                        ->label('Tindak Lanjut Umum')
                        ->rows(4),
                    Forms\Components\Hidden::make('created_by')
                        ->default(fn () => auth()->id()),
                ]),
        ];

        return $schema->schema($schemaComponents);
    }

    public static function table(Table $table): Table
    {
        $hasImportColumns = static::hasImportColumns();

        $columns = [
            Tables\Columns\TextColumn::make('bidang.nama')
                ->label('Bidang')
                ->searchable()
                ->sortable(),
        ];

        if ($hasImportColumns) {
            $columns[] = Tables\Columns\TextColumn::make('point_dari')
                ->label('Point Dari')
                ->searchable()
                ->toggleable();

            $columns[] = Tables\Columns\TextColumn::make('nomor_urut')
                ->label('No')
                ->sortable()
                ->toggleable();
        }

        $columns = [
            ...$columns,
            Tables\Columns\TextColumn::make('nama')
                ->label('Proker')
                ->searchable()
                ->sortable()
                ->wrap(),
            Tables\Columns\TextColumn::make('periode_tahun')
                ->label('Tahun')
                ->sortable(),
            Tables\Columns\TextColumn::make('penanggung_jawab')
                ->label('PIC')
                ->searchable()
                ->toggleable(isToggledHiddenByDefault: true),
            Tables\Columns\TextColumn::make('status')
                ->label('Status')
                ->badge()
                ->color(fn (string $state): string => match ($state) {
                    'draft' => 'gray',
                    'berjalan' => 'primary',
                    'terkendala' => 'danger',
                    'selesai' => 'success',
                    default => 'gray',
                }),
            Tables\Columns\TextColumn::make('progress_persen')
                ->label('Progress')
                ->formatStateUsing(fn ($state): string => (int) $state.'%')
                ->badge()
                ->color(fn ($state): string => (int) $state >= 100 ? 'success' : ((int) $state > 0 ? 'warning' : 'gray'))
                ->sortable(),
            Tables\Columns\TextColumn::make('indikator_progress')
                ->label('Checklist')
                ->state(fn (Proker $record): string => ((int) ($record->checked_indikators_count ?? 0)).'/'.((int) ($record->indikators_count ?? 0)))
                ->toggleable(isToggledHiddenByDefault: true),
            Tables\Columns\TextColumn::make('updates_count')
                ->label('Update')
                ->state(fn (Proker $record): int => (int) ($record->updates_count ?? 0))
                ->toggleable(isToggledHiddenByDefault: true),
            Tables\Columns\TextColumn::make('target_selesai')
                ->label('Target Selesai')
                ->date()
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: true),
        ];

        if ($hasImportColumns) {
            $columns[] = Tables\Columns\TextColumn::make('jadwal_ringkas')
                ->label('Jadwal')
                ->limit(60)
                ->wrap()
                ->toggleable(isToggledHiddenByDefault: true);

            $columns[] = Tables\Columns\TextColumn::make('waktu_pelaksanaan')
                ->label('Pelaksanaan')
                ->limit(40)
                ->toggleable(isToggledHiddenByDefault: true);

            $columns[] = Tables\Columns\TextColumn::make('rab_global')
                ->label('RAB')
                ->toggleable(isToggledHiddenByDefault: true);
        }

        $columns[] = Tables\Columns\TextColumn::make('last_monitored_at')
            ->label('Monitoring')
            ->since()
            ->toggleable();

        $filters = [
            Tables\Filters\SelectFilter::make('bidang')
                ->relationship('bidang', 'nama')
                ->label('Bidang'),
            Tables\Filters\SelectFilter::make('status')
                ->options([
                    'draft' => 'Draft',
                    'berjalan' => 'Berjalan',
                    'terkendala' => 'Terkendala',
                    'selesai' => 'Selesai',
                ]),
            Tables\Filters\SelectFilter::make('target_status')
                ->label('Tanggal Target')
                ->options([
                    'missing' => 'Belum ada target',
                    'scheduled' => 'Sudah ada target',
                    'overdue' => 'Lewat target',
                ])
                ->query(function (Builder $query, array $data): Builder {
                    return match ($data['value'] ?? null) {
                        'missing' => $query->whereNull('target_selesai'),
                        'scheduled' => $query->whereNotNull('target_selesai'),
                        'overdue' => $query
                            ->whereNotNull('target_selesai')
                            ->whereDate('target_selesai', '<', now()->toDateString())
                            ->where('status', '!=', 'selesai'),
                        default => $query,
                    };
                }),
            Tables\Filters\SelectFilter::make('monitoring_status')
                ->label('Monitoring')
                ->options([
                    'no_update' => 'Belum ada update',
                    'stale' => 'Update terlambat',
                    'active' => 'Update terbaru aktif',
                ])
                ->query(function (Builder $query, array $data): Builder {
                    $staleDate = now()->subDays(30)->toDateString();

                    return match ($data['value'] ?? null) {
                        'no_update' => $query->doesntHave('updates'),
                        'stale' => $query->whereDoesntHave('updates', fn (Builder $updateQuery) => $updateQuery->whereDate('tanggal_update', '>=', $staleDate)),
                        'active' => $query->whereHas('updates', fn (Builder $updateQuery) => $updateQuery->whereDate('tanggal_update', '>=', $staleDate)),
                        default => $query,
                    };
                }),
        ];

        if ($hasImportColumns) {
            $filters[] = Tables\Filters\SelectFilter::make('point_dari')
                ->label('Point Dari')
                ->options(fn (): array => Proker::pointDariOptions());
        }

        $filters[] = Tables\Filters\SelectFilter::make('periode_tahun')
            ->label('Tahun')
            ->options(fn (): array => Proker::periodeTahunOptions());

        return $table
            ->defaultSort('periode_tahun', 'desc')
            ->defaultPaginationPageOption(10)
            ->paginated([10, 25, 50])
            ->deferLoading()
            ->searchDebounce('750ms')
            ->columns($columns)
            ->filters($filters)
            ->actions([
                EditAction::make(),
                static::makeDeleteTableAction('proker'),
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
            IndikatorsRelationManager::class,
            UpdatesRelationManager::class,
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['bidang:id,nama'])
            ->withCount('indikators')
            ->withCount([
                'indikators as checked_indikators_count' => fn (Builder $query) => $query->where('is_checked', true),
                'updates',
            ]);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::hasRequiredTables() && parent::canAccess();
    }

    public static function canAccess(): bool
    {
        return static::hasRequiredTables() && parent::canAccess();
    }

    protected static function hasImportColumns(): bool
    {
        return SchemaFacade::hasTable('prokers')
            && SchemaFacade::hasColumns('prokers', [
                'point_dari',
                'nomor_urut',
                'jadwal_ringkas',
                'waktu_pelaksanaan',
                'rab_global',
                'keterangan',
            ]);
    }

    public static function getPeriodYearOptions(bool $withAll = false): array
    {
        if (! SchemaFacade::hasTable('prokers')) {
            return $withAll ? ['' => 'Semua periode'] : [];
        }

        $options = Proker::query()
            ->select(['periode_tahun', 'periode_label'])
            ->whereNotNull('periode_tahun')
            ->orderByDesc('periode_tahun')
            ->orderBy('periode_label')
            ->get()
            ->unique('periode_tahun')
            ->mapWithKeys(function (Proker $proker): array {
                $label = filled($proker->periode_label)
                    ? "{$proker->periode_tahun} ({$proker->periode_label})"
                    : (string) $proker->periode_tahun;

                return [(string) $proker->periode_tahun => $label];
            })
            ->all();

        return $withAll ? ['' => 'Semua periode'] + $options : $options;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProkers::route('/'),
            'create' => Pages\CreateProker::route('/create'),
            'edit' => Pages\EditProker::route('/{record}/edit'),
        ];
    }

    protected static function searchBidangOptions(string $search): array
    {
        return ProkerBidang::query()
            ->where('is_active', true)
            ->where('nama', 'like', '%'.$search.'%')
            ->orderBy('nama')
            ->limit(50)
            ->pluck('nama', 'id')
            ->toArray();
    }

    protected static function resolveBidangLabel(mixed $value): ?string
    {
        $id = (int) $value;

        if ($id <= 0) {
            return null;
        }

        return ProkerBidang::query()
            ->whereKey($id)
            ->value('nama');
    }

    protected static function hasRequiredTables(): bool
    {
        return SchemaFacade::hasTable('proker_bidangs')
            && SchemaFacade::hasTable('prokers')
            && SchemaFacade::hasTable('proker_indikators')
            && SchemaFacade::hasTable('proker_updates');
    }
}
