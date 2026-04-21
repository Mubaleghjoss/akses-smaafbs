<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasConfirmedDeleteActions;
use App\Filament\Concerns\HasModulePermissions;
use App\Filament\Concerns\HasOptimizedAdminTable;
use App\Filament\Resources\StrukturOrganisasiResource\Pages;
use App\Models\GuruTendik;
use App\Models\StrukturOrganisasi;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
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
use LogicException;

class StrukturOrganisasiResource extends Resource
{
    use HasConfirmedDeleteActions;
    use HasModulePermissions;
    use HasOptimizedAdminTable;

    protected static ?string $model = StrukturOrganisasi::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-user-group';

    protected static string|\UnitEnum|null $navigationGroup = 'Manajemen Sekolah';

    protected static ?string $navigationLabel = 'Struktur Sekolah';

    protected static ?int $navigationSort = 20;

    protected static ?string $modelLabel = 'Struktur Sekolah';

    protected static ?string $pluralModelLabel = 'Struktur Sekolah';

    protected static ?string $permissionPrefix = 'struktur_organisasi';

    protected static ?string $structureCategory = StrukturOrganisasi::CATEGORY_SCHOOL;

    protected static bool $allowsGuruTendikLink = true;

    protected static string $uploadDirectory = 'struktur-organisasi';

    protected static bool $requiresPhoto = true;

    protected static bool $usesPeriods = false;

    /** @var array<string, \Illuminate\Support\Collection<int, StrukturOrganisasi>> */
    protected static array $scopedStructureRecordsCache = [];

    /** @var array<string, array<int, int>> */
    protected static array $scopedStructureDescendantMapCache = [];

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public static function canAccess(): bool
    {
        return SchemaFacade::hasTable('struktur_organisasis') && parent::canAccess();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Data '.static::displayLabel())
                    ->columns(['default' => 1, 'md' => 2])
                    ->schema([
                        Forms\Components\Hidden::make('kategori')
                            ->default(static::structureCategory())
                            ->dehydrated(fn (): bool => StrukturOrganisasi::categoryColumnAvailable()),
                        ...static::periodFormComponents(),
                        Forms\Components\Placeholder::make('hierarchy_guide')
                            ->label('Cara mengatur hirarki')
                            ->content(new HtmlString('Posisi <strong>sejajar</strong> dibuat dengan memilih <strong>Atasan Langsung</strong> yang sama. Di tabel, gunakan aksi <strong>Ke Bawah</strong> untuk menjadikan item anak dari item di atasnya, aksi <strong>Sejajar</strong> untuk menaikkan level, dan kolom <strong>Urut</strong> untuk mengatur urutan pada level yang sama.'))
                            ->columnSpanFull(),
                        Forms\Components\Select::make('parent_id')
                            ->label('Atasan Langsung / Level')
                            ->placeholder('Jadikan posisi utama / root')
                            ->searchable()
                            ->getSearchResultsUsing(fn (string $search, ?StrukturOrganisasi $record, Get $get): array => static::searchParentOptions(
                                $search,
                                $record,
                                static::resolvePeriodYearFromForm($get, $record),
                            ))
                            ->getOptionLabelUsing(fn ($value, ?StrukturOrganisasi $record, Get $get): ?string => static::resolveStructureOptionLabel(
                                $value,
                                static::resolvePeriodYearFromForm($get, $record),
                            ))
                            ->helperText(static::usesPeriods()
                                ? 'Untuk posisi sejajar, pilih atasan yang sama dalam periode komite yang dipilih. Kosongkan jika posisi ini berada di level teratas.'
                                : 'Untuk posisi sejajar, pilih atasan yang sama. Kosongkan jika posisi ini berada di level teratas.'),
                        Forms\Components\TextInput::make('jabatan')
                            ->label('Jabatan')
                            ->required()
                            ->maxLength(150),
                        Forms\Components\Select::make('guru_tendik_id')
                            ->label('Guru / Tendik Terkait')
                            ->placeholder('Belum dihubungkan ke profil publik')
                            ->searchable()
                            ->native(false)
                            ->getSearchResultsUsing(fn (string $search): array => static::searchGuruTendikOptions($search))
                            ->getOptionLabelUsing(fn ($value): ?string => static::resolveGuruTendikLabel($value))
                            ->unique(ignoreRecord: true)
                            ->helperText('Opsional. Jika dipilih, node di homepage akan membuka halaman biografi publik guru/tendik ini.')
                            ->visible(static::allowsGuruTendikLink())
                            ->dehydrated(static::allowsGuruTendikLink()),
                        Forms\Components\TextInput::make('nama')
                            ->label('Nama')
                            ->required()
                            ->maxLength(150),
                        Forms\Components\FileUpload::make('foto')
                            ->label('Foto')
                            ->disk('public')
                            ->directory(static::uploadDirectory())
                            ->image()
                            ->imageEditor()
                            ->maxSize(4096)
                            ->required(static::requiresPhoto())
                            ->helperText(static::requiresPhoto() ? null : 'Opsional. Jika kosong, frontend akan menampilkan badge nama.'),
                    ]),
                Section::make('Tampilan Homepage')
                    ->columns(['default' => 1, 'md' => 3])
                    ->schema([
                        Forms\Components\Placeholder::make('homepage_layout_guide')
                            ->label('Cara mengatur tampilan homepage')
                            ->content(new HtmlString('Opsional. Gunakan jika garis dan susunan di homepage perlu berbeda dari struktur jabatan asli. Anda bisa memilih <strong>Atasan Tampilan Homepage</strong> lain, lalu mengatur <strong>Baris</strong> dan <strong>Urutan</strong> visualnya.'))
                            ->columnSpanFull(),
                        Forms\Components\Select::make('homepage_parent_id')
                            ->label('Atasan Tampilan Homepage')
                            ->placeholder('Ikuti atasan asli')
                            ->searchable()
                            ->getSearchResultsUsing(fn (string $search, ?StrukturOrganisasi $record, Get $get): array => static::searchHomepageParentOptions(
                                $search,
                                $record,
                                static::resolvePeriodYearFromForm($get, $record),
                            ))
                            ->getOptionLabelUsing(fn ($value, ?StrukturOrganisasi $record, Get $get): ?string => static::resolveStructureOptionLabel(
                                $value,
                                static::resolvePeriodYearFromForm($get, $record),
                            ))
                            ->helperText(static::usesPeriods()
                                ? 'Hanya memengaruhi garis dan posisi di homepage dalam periode komite yang sama, bukan struktur jabatan utama.'
                                : 'Hanya memengaruhi garis dan posisi di homepage, bukan struktur jabatan utama.'),
                        Forms\Components\TextInput::make('homepage_row')
                            ->label('Baris Tampilan')
                            ->numeric()
                            ->minValue(1)
                            ->placeholder('1')
                            ->helperText('Baris 1 paling dekat dengan atasan tampilan. Isi 4 jika ingin muncul di baris ke-4.'),
                        Forms\Components\TextInput::make('homepage_order')
                            ->label('Urutan Tampilan')
                            ->numeric()
                            ->minValue(1)
                            ->placeholder('Ikuti urutan utama')
                            ->helperText('Urutan kiri ke kanan pada baris tampilan yang sama.'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        $branchFilterOptions = static::branchOptions();
        $levelFilterOptions = static::levelOptions();

        return static::optimizeAdminTable(
            $table,
            searchPlaceholder: 'Cari posisi, nama, atau profil publik...',
            emptyStateHeading: 'Belum ada '.strtolower(static::displayLabel()),
            emptyStateDescription: 'Tambahkan '.strtolower(static::displayLabel()).' agar struktur kepengurusan tampil rapi di admin dan homepage.'
        )
            ->modifyQueryUsing(fn (Builder $query): Builder => static::applyStructureListingOrder(
                static::applyStructureCategoryScope($query)->with(['parent', 'homepageParent'])
            ))
            ->defaultSort('urutan')
            ->columns([
                ...static::periodTableColumns(),
                Tables\Columns\SelectColumn::make('parent_id')
                    ->label('Posisi')
                    ->placeholder('Posisi utama')
                    ->searchableOptions()
                    ->options(fn (StrukturOrganisasi $record): array => static::parentOptions($record))
                    ->tooltip('Pilih atasan. Atasan yang sama berarti sejajar.')
                    ->toggleable(),
                Tables\Columns\SelectColumn::make('homepage_parent_id')
                    ->label('Atasan Home')
                    ->placeholder('Ikuti atasan asli')
                    ->searchableOptions()
                    ->options(fn (StrukturOrganisasi $record): array => static::homepageParentOptions($record))
                    ->tooltip('Opsional. Untuk mengubah garis dan susunan di homepage tanpa mengubah struktur utama.')
                    ->toggleable(),
                Tables\Columns\TextInputColumn::make('urutan')
                    ->label('Urut')
                    ->type('number')
                    ->rules(['required', 'integer', 'min:1'])
                    ->extraInputAttributes(['min' => 1, 'inputmode' => 'numeric'])
                    ->tooltip('Atur urutan dalam level yang sama.')
                    ->alignCenter(),
                Tables\Columns\TextInputColumn::make('homepage_row')
                    ->label('Baris Home')
                    ->type('number')
                    ->rules(['nullable', 'integer', 'min:1'])
                    ->extraInputAttributes(['min' => 1, 'inputmode' => 'numeric'])
                    ->placeholder('1')
                    ->tooltip('Baris visual di homepage.')
                    ->alignCenter(),
                Tables\Columns\TextInputColumn::make('homepage_order')
                    ->label('Urut Home')
                    ->type('number')
                    ->rules(['nullable', 'integer', 'min:1'])
                    ->extraInputAttributes(['min' => 1, 'inputmode' => 'numeric'])
                    ->placeholder('Ikuti')
                    ->tooltip('Urutan kiri ke kanan di homepage.')
                    ->alignCenter(),
                Tables\Columns\ImageColumn::make('foto')
                    ->label('Foto')
                    ->disk('public')
                    ->square(),
                Tables\Columns\TextColumn::make('jabatan')
                    ->label('Posisi')
                    ->searchable()
                    ->sortable()
                    ->html()
                    ->formatStateUsing(fn (string $state, StrukturOrganisasi $record): HtmlString => static::formatIndentedJabatan($state, $record))
                    ->description(fn (StrukturOrganisasi $record): string => collect([
                        $record->nama,
                        'Level '.$record->levelNumber(),
                        filled($record->parent_id)
                            ? 'Di bawah '.($record->parent?->jabatan ?? 'atasan')
                            : 'Posisi utama',
                    ])->filter()->implode(' | '))
                    ->wrap(),
                Tables\Columns\TextColumn::make('guruTendik.nama')
                    ->label('Profil Publik')
                    ->placeholder('Belum dihubungkan')
                    ->searchable()
                    ->wrap()
                    ->visible(static::allowsGuruTendikLink())
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('homepage_tampilan')
                    ->label('Tampilan Home')
                    ->state(fn (StrukturOrganisasi $record): string => filled($record->homepage_parent_id)
                        ? 'Di bawah '.($record->homepageParent?->jabatan ?? 'atasan tampilan')
                        : 'Ikuti struktur asli')
                    ->description(fn (StrukturOrganisasi $record): string => 'Baris '.$record->homepageRowNumber().' | Urut '.$record->homepageOrderNumber())
                    ->wrap()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Diupdate')
                    ->since()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                ...static::periodTableFilters(),
                Tables\Filters\SelectFilter::make('cabang_root')
                    ->label('Cabang')
                    ->options($branchFilterOptions)
                    ->searchable()
                    ->query(function (Builder $query, array $data): Builder {
                        $branchId = $data['value'] ?? null;

                        if (! filled($branchId)) {
                            return $query;
                        }

                        $branchRoot = static::structureScopedQuery()->find((int) $branchId);

                        if (! $branchRoot) {
                            return $query->whereRaw('1 = 0');
                        }

                        return $query->whereIn('id', $branchRoot->branchIds());
                    }),
                Tables\Filters\SelectFilter::make('level_number')
                    ->label('Level')
                    ->options($levelFilterOptions)
                    ->query(function (Builder $query, array $data): Builder {
                        $selectedLevel = $data['value'] ?? null;

                        if (! filled($selectedLevel)) {
                            return $query;
                        }

                        $matchingIds = static::structureScopedQuery()
                            ->ordered()
                            ->get(['id', 'parent_id', 'kategori'])
                            ->filter(fn (StrukturOrganisasi $record): bool => $record->levelNumber() === (int) $selectedLevel)
                            ->pluck('id')
                            ->all();

                        if ($matchingIds === []) {
                            return $query->whereRaw('1 = 0');
                        }

                        return $query->whereIn('id', $matchingIds);
                    }),
            ])
            ->actions([
                Action::make('indent')
                    ->label('Ke Bawah')
                    ->icon('heroicon-m-arrow-down')
                    ->color('primary')
                    ->tooltip('Jadikan item ini anak dari item sejajar di atasnya.')
                    ->disabled(fn (StrukturOrganisasi $record): bool => ! $record->canIndentUnderPreviousSibling())
                    ->action(function (StrukturOrganisasi $record): void {
                        $record->indentUnderPreviousSibling();
                    }),
                Action::make('outdent')
                    ->label('Sejajar')
                    ->icon('heroicon-m-arrow-left')
                    ->color('gray')
                    ->tooltip('Naikkan item ini agar sejajar dengan atasannya saat ini.')
                    ->disabled(fn (StrukturOrganisasi $record): bool => ! $record->canOutdentToParentLevel())
                    ->action(function (StrukturOrganisasi $record): void {
                        $record->outdentToParentLevel();
                    }),
                ActionGroup::make([
                    EditAction::make(),
                    DeleteAction::make()
                        ->label('Hapus')
                        ->requiresConfirmation()
                        ->modalHeading('Hapus item '.strtolower(static::displayLabel()).'?')
                        ->modalDescription('Posisi yang masih memiliki cabang tidak dapat dihapus sampai cabangnya dipindahkan atau dihapus terlebih dahulu.')
                        ->failureNotificationTitle('Item '.strtolower(static::displayLabel()).' belum bisa dihapus')
                        ->action(function (StrukturOrganisasi $record): void {
                            try {
                                $record->delete();
                            } catch (LogicException $exception) {
                                throw $exception;
                            }
                        }),
                ])
                    ->label('Lainnya')
                    ->icon('heroicon-o-ellipsis-horizontal'),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    static::makeDeleteBulkTableAction(),
                ]),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return static::applyStructureListingOrder(
            static::applyStructureCategoryScope(parent::getEloquentQuery())
        );
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStrukturOrganisasis::route('/'),
            'create' => Pages\CreateStrukturOrganisasi::route('/create'),
            'edit' => Pages\EditStrukturOrganisasi::route('/{record}/edit'),
        ];
    }

    protected static function parentOptions(?StrukturOrganisasi $record, ?int $periodYear = null): array
    {
        return static::structureOptionsForRecord($record, $periodYear);
    }

    protected static function homepageParentOptions(?StrukturOrganisasi $record, ?int $periodYear = null): array
    {
        return static::structureOptionsForRecord($record, $periodYear);
    }

    protected static function branchOptions(): array
    {
        return static::scopedStructureRecords()
            ->filter(fn (StrukturOrganisasi $item): bool => blank($item->parent_id))
            ->mapWithKeys(fn (StrukturOrganisasi $item): array => [
                $item->id => trim($item->jabatan.' - '.$item->nama),
            ])
            ->all();
    }

    protected static function levelOptions(): array
    {
        $records = static::scopedStructureRecords();
        $maxLevel = max(1, static::maxLevelFromScopedRecords($records));

        return collect(range(1, max(1, $maxLevel)))
            ->mapWithKeys(fn (int $level): array => [$level => 'Level '.$level])
            ->all();
    }

    protected static function formatIndentedJabatan(string $state, StrukturOrganisasi $record): HtmlString
    {
        $depth = max(0, $record->levelNumber() - 1);

        if ($depth === 0) {
            return new HtmlString('<span>'.e($state).'</span>');
        }

        $guides = collect(range(1, $depth))
            ->map(function (int $level) use ($depth): string {
                $leftOffset = ($level - 1) * 1.05;

                if ($level === $depth) {
                    return sprintf(
                        '<span style="position:absolute; left:%1$.2frem; top:0.9rem; width:0.75rem; border-top:1px solid rgba(148, 163, 184, 0.55);"></span><span style="position:absolute; left:%1$.2frem; top:0.25rem; height:0.65rem; border-left:1px solid rgba(148, 163, 184, 0.55);"></span>',
                        $leftOffset
                    );
                }

                return sprintf(
                    '<span style="position:absolute; left:%1$.2frem; top:0.25rem; bottom:0.25rem; border-left:1px solid rgba(148, 163, 184, 0.35);"></span>',
                    $leftOffset
                );
            })
            ->implode('');

        return new HtmlString(sprintf(
            '<span style="position:relative; display:inline-flex; align-items:center; min-height:1.5rem; padding-left:%1$.2frem;">%2$s<span>%3$s</span></span>',
            ($depth * 1.05) + 0.95,
            $guides,
            e($state)
        ));
    }

    protected static function displayLabel(): string
    {
        return static::$navigationLabel
            ?? static::$pluralModelLabel
            ?? 'Struktur';
    }

    protected static function structureCategory(): ?string
    {
        return static::$structureCategory;
    }

    protected static function allowsGuruTendikLink(): bool
    {
        return static::$allowsGuruTendikLink;
    }

    protected static function uploadDirectory(): string
    {
        return static::$uploadDirectory;
    }

    protected static function requiresPhoto(): bool
    {
        return static::$requiresPhoto;
    }

    protected static function usesPeriods(): bool
    {
        return static::$usesPeriods;
    }

    /**
     * @return array<int, Forms\Components\Component>
     */
    protected static function periodFormComponents(): array
    {
        if (! static::usesPeriods() || ! StrukturOrganisasi::periodColumnAvailable()) {
            return [];
        }

        return [
            Forms\Components\TextInput::make('periode_tahun')
                ->label('Periode Tahun')
                ->numeric()
                ->minValue(2000)
                ->maxValue(2100)
                ->default((int) now()->format('Y'))
                ->required()
                ->live()
                ->afterStateUpdated(function (Set $set, mixed $state): void {
                    $set('parent_id', null);
                    $set('homepage_parent_id', null);

                    if (! filled($state)) {
                        $set('periode_label', null);
                    }
                })
                ->helperText('Tahun utama kepengurusan komite. Data hirarki dan relasi atasan akan dipisahkan per periode.'),
            Forms\Components\TextInput::make('periode_label')
                ->label('Label Periode')
                ->maxLength(100)
                ->placeholder('Contoh: 2026-2029')
                ->helperText('Opsional. Jika dikosongkan, sistem memakai tahun sebagai label periode.'),
        ];
    }

    /**
     * @return array<int, Tables\Columns\Column>
     */
    protected static function periodTableColumns(): array
    {
        if (! static::usesPeriods() || ! StrukturOrganisasi::periodColumnAvailable()) {
            return [];
        }

        return [
            Tables\Columns\TextColumn::make('periode_ringkas')
                ->label('Periode')
                ->state(fn (StrukturOrganisasi $record): string => $record->resolvedPeriodLabel() ?? '-')
                ->sortable(query: fn (Builder $query, string $direction): Builder => $query
                    ->orderBy('periode_tahun', $direction)
                    ->orderBy('periode_label', $direction))
                ->wrap(),
        ];
    }

    /**
     * @return array<int, Tables\Filters\BaseFilter>
     */
    protected static function periodTableFilters(): array
    {
        if (! static::usesPeriods() || ! StrukturOrganisasi::periodColumnAvailable()) {
            return [];
        }

        return [
            Tables\Filters\SelectFilter::make('periode_tahun')
                ->label('Periode')
                ->options(static::periodOptions())
                ->query(function (Builder $query, array $data): Builder {
                    $periodYear = $data['value'] ?? null;

                    if (! filled($periodYear)) {
                        return $query;
                    }

                    return $query->where('periode_tahun', (int) $periodYear);
                }),
        ];
    }

    protected static function periodOptions(): array
    {
        if (! static::usesPeriods() || ! StrukturOrganisasi::periodColumnAvailable()) {
            return [];
        }

        return StrukturOrganisasi::query()
            ->where('kategori', static::structureCategory())
            ->whereNotNull('periode_tahun')
            ->orderByDesc('periode_tahun')
            ->get(['periode_tahun', 'periode_label'])
            ->unique('periode_tahun')
            ->mapWithKeys(fn (StrukturOrganisasi $record): array => [
                (string) $record->periode_tahun => StrukturOrganisasi::formatCommitteePeriodLabel(
                    filled($record->periode_tahun) ? (int) $record->periode_tahun : null,
                    $record->periode_label,
                ) ?? (string) $record->periode_tahun,
            ])
            ->all();
    }

    protected static function resolvePeriodYearFromForm(Get $get, ?StrukturOrganisasi $record = null): ?int
    {
        if (! static::usesPeriods() || ! StrukturOrganisasi::periodColumnAvailable()) {
            return null;
        }

        $periodYear = $get('periode_tahun');

        if (! filled($periodYear) && $record?->exists) {
            $periodYear = $record->periode_tahun;
        }

        return filled($periodYear) ? (int) $periodYear : null;
    }

    protected static function structureScopedQuery(?int $periodYear = null): Builder
    {
        return static::applyStructureCategoryScope(StrukturOrganisasi::query(), $periodYear);
    }

    protected static function structureOptionsForRecord(?StrukturOrganisasi $record = null, ?int $periodYear = null): array
    {
        $records = static::scopedStructureRecords($periodYear);
        $excludedIds = $record?->exists
            ? array_merge([(int) $record->getKey()], static::descendantIdsFromScopedRecords((int) $record->getKey(), $periodYear))
            : [];

        return $records
            ->reject(fn (StrukturOrganisasi $item): bool => in_array((int) $item->getKey(), $excludedIds, true))
            ->mapWithKeys(fn (StrukturOrganisasi $item): array => [
                $item->id => trim($item->jabatan.' - '.$item->nama),
            ])
            ->all();
    }

    protected static function searchParentOptions(string $search, ?StrukturOrganisasi $record = null, ?int $periodYear = null): array
    {
        return static::searchStructureOptions($search, $record, $periodYear);
    }

    protected static function searchHomepageParentOptions(string $search, ?StrukturOrganisasi $record = null, ?int $periodYear = null): array
    {
        return static::searchStructureOptions($search, $record, $periodYear);
    }

    protected static function searchStructureOptions(string $search, ?StrukturOrganisasi $record = null, ?int $periodYear = null): array
    {
        return collect(static::structureOptionsForRecord($record, $periodYear))
            ->filter(fn (string $label): bool => str_contains(mb_strtolower($label), mb_strtolower(trim($search))))
            ->take(50)
            ->all();
    }

    protected static function resolveStructureOptionLabel(mixed $value, ?int $periodYear = null): ?string
    {
        if (! filled($value)) {
            return null;
        }

        $record = static::scopedStructureRecords($periodYear)
            ->firstWhere('id', (int) $value);

        if (! $record) {
            return null;
        }

        return trim($record->jabatan.' - '.$record->nama);
    }

    protected static function searchGuruTendikOptions(string $search): array
    {
        return GuruTendik::query()
            ->where('nama', 'like', '%'.$search.'%')
            ->orderBy('nama')
            ->limit(50)
            ->pluck('nama', 'id')
            ->all();
    }

    protected static function resolveGuruTendikLabel(mixed $value): ?string
    {
        if (! filled($value)) {
            return null;
        }

        return GuruTendik::query()
            ->whereKey($value)
            ->value('nama');
    }

    protected static function applyStructureCategoryScope(Builder $query, ?int $periodYear = null): Builder
    {
        if (! filled(static::structureCategory()) || ! StrukturOrganisasi::categoryColumnAvailable()) {
            return $query;
        }

        $query->where('kategori', static::structureCategory());

        if (static::usesPeriods() && StrukturOrganisasi::periodColumnAvailable() && filled($periodYear)) {
            $query->where('periode_tahun', (int) $periodYear);
        }

        return $query;
    }

    protected static function applyStructureListingOrder(Builder $query): Builder
    {
        if (static::usesPeriods() && StrukturOrganisasi::periodColumnAvailable()) {
            $query->orderByDesc('periode_tahun');
        }

        return $query
            ->orderByRaw('COALESCE(parent_id, 0) asc')
            ->ordered();
    }

    /**
     * @return \Illuminate\Support\Collection<int, StrukturOrganisasi>
     */
    protected static function scopedStructureRecords(?int $periodYear = null): \Illuminate\Support\Collection
    {
        $cacheKey = static::scopedStructureCacheKey($periodYear);

        if (! array_key_exists($cacheKey, static::$scopedStructureRecordsCache)) {
            static::$scopedStructureRecordsCache[$cacheKey] = static::structureScopedQuery($periodYear)
                ->ordered()
                ->get(['id', 'parent_id', 'jabatan', 'nama', 'kategori', 'periode_tahun']);
        }

        return static::$scopedStructureRecordsCache[$cacheKey];
    }

    /**
     * @return array<int, int>
     */
    protected static function descendantIdsFromScopedRecords(int $recordId, ?int $periodYear = null): array
    {
        $cacheKey = static::scopedStructureCacheKey($periodYear);

        if (! array_key_exists($cacheKey, static::$scopedStructureDescendantMapCache)) {
            $childrenByParent = static::scopedStructureRecords($periodYear)
                ->groupBy(fn (StrukturOrganisasi $record): string => filled($record->parent_id) ? (string) $record->parent_id : 'root')
                ->map(fn ($items): array => collect($items)->pluck('id')->map(fn ($id): int => (int) $id)->all())
                ->all();

            $descendantMap = [];

            foreach (static::scopedStructureRecords($periodYear) as $record) {
                $pending = $childrenByParent[(string) $record->getKey()] ?? [];
                $ids = [];

                while ($pending !== []) {
                    $childId = array_shift($pending);

                    if ($childId === null || in_array($childId, $ids, true)) {
                        continue;
                    }

                    $ids[] = $childId;

                    foreach ($childrenByParent[(string) $childId] ?? [] as $nestedId) {
                        if (! in_array($nestedId, $ids, true)) {
                            $pending[] = $nestedId;
                        }
                    }
                }

                $descendantMap[(int) $record->getKey()] = $ids;
            }

            static::$scopedStructureDescendantMapCache[$cacheKey] = $descendantMap;
        }

        return static::$scopedStructureDescendantMapCache[$cacheKey][$recordId] ?? [];
    }

    protected static function maxLevelFromScopedRecords(\Illuminate\Support\Collection $records): int
    {
        $depthCache = [];
        $parentMap = $records
            ->mapWithKeys(fn (StrukturOrganisasi $record): array => [(int) $record->getKey() => filled($record->parent_id) ? (int) $record->parent_id : null])
            ->all();

        $resolveDepth = function (int $id) use (&$resolveDepth, &$depthCache, $parentMap): int {
            if (array_key_exists($id, $depthCache)) {
                return $depthCache[$id];
            }

            $parentId = $parentMap[$id] ?? null;

            if (! $parentId || ! array_key_exists($parentId, $parentMap)) {
                return $depthCache[$id] = 0;
            }

            return $depthCache[$id] = $resolveDepth($parentId) + 1;
        };

        return $records
            ->reduce(fn (int $max, StrukturOrganisasi $record): int => max($max, $resolveDepth((int) $record->getKey()) + 1), 1);
    }

    protected static function scopedStructureCacheKey(?int $periodYear = null): string
    {
        return (static::structureCategory() ?: 'all').'|'.(filled($periodYear) ? (string) $periodYear : 'all');
    }
}
