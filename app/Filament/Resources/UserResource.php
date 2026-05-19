<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasConfirmedDeleteActions;
use App\Filament\Concerns\HasModulePermissions;
use App\Filament\Resources\UserResource\Pages;
use App\Models\DataSiswa;
use App\Models\GuruTendik;
use App\Models\User;
use App\Support\Admin\AdminAccessChangeLogSupport;
use App\Support\Admin\AdminModuleAccess;
use App\Support\Admin\AdminNavigationSupport;
use App\Support\Admin\AdminRoleTemplateSupport;
use App\Support\Admin\AdminUserCredentialSupport;
use App\Support\Admin\AdminUserCredentialShareSupport;
use App\Support\DataSiswa\DataSiswaSupport;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class UserResource extends Resource
{
    use HasConfirmedDeleteActions;
    use HasModulePermissions;

    protected static ?Collection $moduleAccessDefinitionsCache = null;

    /**
     * @var array<string, array<string, string>>
     */
    protected static array $defaultModuleAccessLevelsCache = [];

    /**
     * @var array<string, string>|null
     */
    protected static ?array $advancedNavigationItemOptionsCache = null;

    /**
     * @var array<int, ?GuruTendik>
     */
    protected static array $guruTendikAccessSuggestionCache = [];

    /**
     * @var array<int, array<int, int|string>>
     */
    protected static array $userRoleIdsCache = [];

    /**
     * @var array<string, Collection<int, string>>
     */
    protected static array $selectedRoleNamesCache = [];

    protected static ?string $model = User::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-users';

    protected static string|\UnitEnum|null $navigationGroup = 'Pengaturan';

    protected static ?string $navigationLabel = 'Akun Admin';

    protected static ?string $modelLabel = 'akun admin';

    protected static ?string $pluralModelLabel = 'Akun Admin';

    protected static ?int $navigationSort = 5;

    protected static ?string $permissionPrefix = 'users';

    protected const GURU_COPYABLE_PERMISSION_PREFIXES = [
        'guru_tendik',
        'berkas_guru',
        'data_siswa',
        'prestasi',
    ];

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                static::accessTemplateSection(),
                Section::make('Akun Pengguna Admin')
                    ->description('Atur identitas akun, role, dan scope dasar. Level akses modul serta menu sidebar akan mengikuti pengaturan di bawah secara otomatis.')
                    ->columns(['default' => 1, 'md' => 2])
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Nama')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('username')
                            ->label('Username')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(50),
                        Forms\Components\TextInput::make('email')
                            ->label('Email')
                            ->email()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),
                        Forms\Components\TextInput::make('password')
                            ->label('Password')
                            ->password()
                            ->revealable()
                            ->required(fn (string $operation): bool => $operation === 'create')
                            ->dehydrated(fn ($state): bool => filled($state))
                            ->maxLength(255),
                        Forms\Components\Select::make('roles')
                            ->label('Role')
                            ->relationship('roles', 'name', fn (Builder $query) => $query->orderBy('name'))
                            ->getOptionLabelFromRecordUsing(fn (Role $record): string => str($record->name)->replace('_', ' ')->title()->toString())
                            ->multiple()
                            ->live()
                            ->default(fn (): array => static::defaultRoleState())
                            ->searchable()
                            ->required()
                            ->columnSpanFull(),
                        Forms\Components\Select::make('guru_tendik_id')
                            ->label('Tautkan ke Data Guru/Tendik')
                            ->relationship(
                                name: 'guruTendik',
                                titleAttribute: 'nama',
                                modifyQueryUsing: fn (Builder $query) => $query->orderBy('nama'),
                            )
                            ->searchable()
                            ->live()
                            ->default(fn (): ?string => request()->query('link_guru_id'))
                            ->helperText('Isi untuk akun guru agar login terhubung langsung ke profil guru/tendik yang sesuai.')
                            ->afterStateUpdated(function (Set $set, Get $get, mixed $state): void {
                                $suggestedTemplates = static::suggestedAccessTemplatesForGuruId($state);

                                if ($suggestedTemplates === []) {
                                    return;
                                }

                                $currentAddons = collect($get('access_template_addons') ?? [])
                                    ->map(fn ($value): string => trim((string) $value))
                                    ->filter()
                                    ->values();

                                $mergedAddons = $currentAddons
                                    ->merge($suggestedTemplates)
                                    ->unique()
                                    ->values()
                                    ->all();

                                $set('access_template_addons', $mergedAddons);
                                $set('module_access_levels', static::mergeAddonTemplatesIntoLevels(
                                    $get('module_access_levels') ?? static::createDefaultModuleAccessLevels(),
                                    $mergedAddons,
                                ));
                            })
                            ->columnSpanFull(),
                        Forms\Components\Placeholder::make('guru_access_suggestion')
                            ->label('Saran Akses dari Tugas Tambahan')
                            ->content(fn (Get $get): string => AdminRoleTemplateSupport::suggestionReasonSummary(
                                static::suggestedAccessTemplateReasonsForGuruId($get('guru_tendik_id'))
                            ))
                            ->columnSpanFull(),
                        Forms\Components\TextInput::make('guru_mapel_label')
                            ->label('Label Guru Mapel')
                            ->maxLength(150)
                            ->placeholder('Contoh: Matematika, Bahasa Arab, Tahfidz')
                            ->helperText('Label ini bersifat fleksibel dan bisa dipakai untuk identitas atau scope mapel guru.'),
                        Forms\Components\Select::make('boarding_rombel_scope')
                            ->label('Scope Kelas Pamong')
                            ->options(fn (): array => DataSiswaSupport::rombelOptions())
                            ->multiple()
                            ->searchable()
                            ->helperText('Isi untuk role pamong. Satu pamong bisa memegang banyak kelas, dan scope siswa boarding akan mengikuti daftar kelas ini.')
                            ->columnSpanFull(),
                        Forms\Components\Select::make('guru_walas_scope')
                            ->label('Scope Kelas Walas / Guru')
                            ->options(fn (): array => DataSiswaSupport::rombelOptions())
                            ->multiple()
                            ->searchable()
                            ->helperText('Jika akun guru diberi akses ke data siswa atau prestasi, data yang terlihat akan mengikuti daftar kelas walas ini.')
                            ->columnSpanFull(),
                    ]),
                ...static::moduleAccessSections(),
                static::advancedAccessSection(),
            ]);
    }

    /**
     * @return array<int, Section>
     */
    protected static function accessTemplateSection(): Section
    {
        return Section::make('Preset Akses')
            ->description('Pilih template untuk mengisi role dasar dan akses modul secara otomatis. Setelah dipilih, pengaturan tetap bisa disesuaikan manual.')
            ->compact()
            ->schema([
                Forms\Components\Select::make('access_template_preset')
                    ->label('Template Divisi')
                    ->options(AdminRoleTemplateSupport::options())
                    ->default(fn (): ?string => static::requestedAccessTemplate())
                    ->native(false)
                    ->searchable()
                    ->live()
                    ->dehydrated(false)
                    ->afterStateUpdated(function (Set $set, ?string $state): void {
                        $templateState = AdminRoleTemplateSupport::formState($state);

                        if (! $templateState) {
                            return;
                        }

                        $set('roles', $templateState['roles']);
                        $set('module_access_levels', $templateState['module_access_levels']);
                        $set('allowed_navigation_items', $templateState['allowed_navigation_items']);
                    }),
                Forms\Components\Placeholder::make('access_template_help')
                    ->label('Ringkasan Template')
                    ->content(fn (Get $get): string => AdminRoleTemplateSupport::description($get('access_template_preset'))
                        ?: 'Pilih template jika ingin mengisi role dan akses modul lebih cepat.')
                    ->columnSpanFull(),
                Forms\Components\CheckboxList::make('access_template_addons')
                    ->label('Akses Tambahan Divisi')
                    ->options(AdminRoleTemplateSupport::options())
                    ->columns(['default' => 1, 'md' => 2])
                    ->default(fn (): array => static::defaultAccessAddonState())
                    ->dehydrated(false)
                    ->live()
                    ->helperText('Cocok untuk akun guru yang mendapat tugas tambahan, misalnya guru sekaligus sarpras, BK, atau humas. Preset ini menambah akses modul tanpa harus mengganti role utama akun.')
                    ->afterStateUpdated(function (Set $set, Get $get, mixed $state): void {
                        $templateKeys = collect($state)
                            ->map(fn ($value): string => trim((string) $value))
                            ->filter(fn (string $value): bool => array_key_exists($value, AdminRoleTemplateSupport::definitions()))
                            ->values()
                            ->all();

                        if ($templateKeys === []) {
                            return;
                        }

                        $set('module_access_levels', static::mergeAddonTemplatesIntoLevels(
                            $get('module_access_levels') ?? static::createDefaultModuleAccessLevels(),
                            $templateKeys,
                        ));
                    })
                    ->columnSpanFull(),
                Forms\Components\Placeholder::make('access_template_addon_help')
                    ->label('Catatan Tugas Tambahan')
                    ->content('Untuk akun guru yang sudah ada, cukup tambah akses divisi yang dibutuhkan. Role guru tetap bisa dipakai, sementara sidebar dan modul tambahan akan mengikuti matrix akses yang sudah digabung.')
                    ->columnSpanFull(),
            ]);
    }

    /**
     * @return array<int, Section>
     */
    protected static function moduleAccessSections(): array
    {
        $defaultLevels = static::defaultModuleAccessLevelsSnapshot();

        return static::moduleAccessDefinitionsSnapshot()
            ->groupBy(fn (array $definition): string => implode('||', [
                $definition['group_label'],
                $definition['parent_label'] ?? '',
            ]))
            ->map(function (Collection $definitions, string $sectionKey) use ($defaultLevels): Section {
                [$groupLabel, $parentLabel] = array_pad(explode('||', $sectionKey, 2), 2, '');

                $title = collect(['Akses Modul', $groupLabel, filled($parentLabel) ? $parentLabel : null])
                    ->filter()
                    ->implode(' · ');

                return Section::make($title)
                    ->description('Pilih apakah modul disembunyikan, hanya bisa dilihat, atau boleh dikelola penuh. Menu sidebar untuk modul CRUD akan mengikuti pilihan ini secara otomatis.')
                    ->columns(['default' => 1, 'md' => 2])
                    ->schema([
                        ...$definitions->map(function (array $definition): Forms\Components\Select {
                            return Forms\Components\Select::make('module_access_levels.'.$definition['prefix'])
                                ->label($definition['label'])
                                ->options(AdminModuleAccess::levelOptions())
                                ->default($defaultLevels[$definition['prefix']] ?? AdminModuleAccess::NONE)
                                ->native(false)
                                ->helperText($definition['description']);
                        })->all(),
                    ]);
            })
            ->values()
            ->all();
    }

    protected static function advancedAccessSection(): Section
    {
        return Section::make('Lanjutan · Menu Khusus')
            ->description('Opsional. Gunakan hanya jika perlu menampilkan halaman khusus non-CRUD di sidebar. Untuk modul CRUD, gunakan matrix akses modul di atas.')
            ->compact()
            ->collapsible()
            ->collapsed()
            ->schema([
                Forms\Components\Placeholder::make('sidebar_preview')
                    ->label('Ringkasan Sidebar')
                    ->content(function (Get $get): string {
                        $moduleAccessLevels = AdminModuleAccess::normalizeLevels($get('module_access_levels') ?? static::defaultModuleAccessLevelsSnapshot());
                        $advancedItems = collect($get('allowed_navigation_items') ?? [])
                            ->map(fn ($item): string => trim((string) $item))
                            ->filter()
                            ->all();

                        $groups = AdminModuleAccess::deriveNavigationGroups(
                            AdminModuleAccess::deriveNavigationItems($moduleAccessLevels, $advancedItems),
                        );

                        return $groups === []
                            ? 'Sidebar akan kosong selain dashboard.'
                            : 'Sidebar akan menampilkan grup: '.collect($groups)->implode(', ');
                    })
                    ->columnSpanFull(),
                Forms\Components\CheckboxList::make('allowed_navigation_items')
                    ->label('Menu Khusus Non-CRUD')
                    ->options(fn (): array => static::advancedNavigationItemOptionsSnapshot())
                    ->columns(['default' => 1, 'md' => 2])
                    ->helperText('Biarkan kosong bila sidebar cukup mengikuti matrix akses modul.')
                    ->columnSpanFull(),
            ]);
    }

    protected static function moduleAccessDefinitionsSnapshot(): Collection
    {
        return static::$moduleAccessDefinitionsCache ??= AdminModuleAccess::definitions();
    }

    /**
     * @return array<string, string>
     */
    protected static function defaultModuleAccessLevelsSnapshot(): array
    {
        $cacheKey = implode('|', [
            static::requestedAccessTemplate() ?? '-',
            trim((string) request()->query('preset_role')) ?: '-',
            trim((string) request()->query('preset_addons')) ?: '-',
        ]);

        if (array_key_exists($cacheKey, static::$defaultModuleAccessLevelsCache)) {
            return static::$defaultModuleAccessLevelsCache[$cacheKey];
        }

        return static::$defaultModuleAccessLevelsCache[$cacheKey] = static::createDefaultModuleAccessLevels();
    }

    /**
     * @return array<string, string>
     */
    protected static function advancedNavigationItemOptionsSnapshot(): array
    {
        return static::$advancedNavigationItemOptionsCache ??= AdminModuleAccess::advancedNavigationItemOptions();
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nama')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('username')
                    ->label('Username')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('roles.name')
                    ->label('Role')
                    ->badge()
                    ->separator(',')
                    ->formatStateUsing(fn (?string $state): string => str($state ?: '-')->replace('_', ' ')->title()->toString()),
                Tables\Columns\TextColumn::make('division_access_labels')
                    ->label('Divisi')
                    ->state(function (User $record): ?string {
                        $labels = AdminRoleTemplateSupport::matchedTemplateLabelsForUser($record);

                        return $labels === [] ? null : implode(', ', $labels);
                    })
                    ->badge()
                    ->separator(',')
                    ->wrap()
                    ->placeholder('Belum ada badge divisi'),
                Tables\Columns\TextColumn::make('uses_default_password')
                    ->label('Status Password')
                    ->state(fn (User $record): string => $record->uses_default_password ? 'Default' : 'Sudah diganti')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'Default' ? 'warning' : 'success'),
                Tables\Columns\TextColumn::make('guruTendik.nama')
                    ->label('Guru/Tendik')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('guru_mapel_label')
                    ->label('Mapel')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('boarding_rombel_scope')
                    ->label('Scope Kelas')
                    ->state(fn (User $record): string => collect($record->boardingRombelScopes())->implode(', ') ?: '-')
                    ->wrap()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('guru_walas_scope')
                    ->label('Scope Walas')
                    ->state(fn (User $record): string => collect($record->guruWalasScopes())->implode(', ') ?: '-')
                    ->wrap()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('allowed_navigation_groups')
                    ->label('Menu')
                    ->state(function (User $record): string {
                        if ($record->hasRole('admin')) {
                            return 'Semua Menu';
                        }

                        return collect($record->allowed_navigation_groups ?? [])
                            ->map(fn (string $group): string => User::navigationGroupLabel($group))
                            ->implode(', ') ?: '-';
                    })
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('allowed_navigation_items')
                    ->label('Sub Menu')
                    ->state(function (User $record): string {
                        if ($record->hasRole('admin')) {
                            return 'Semua Sub Menu';
                        }

                        return collect($record->allowed_navigation_items ?? [])
                            ->map(fn (string $class): string => AdminNavigationSupport::availableNavigationItemOptions()[$class] ?? class_basename($class))
                            ->implode(', ') ?: '-';
                    })
                    ->wrap()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Diupdate')
                    ->since()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('roles')
                    ->label('Role')
                    ->relationship('roles', 'name'),
                Tables\Filters\SelectFilter::make('boarding_rombel_scope')
                    ->label('Scope Kelas')
                    ->options(fn (): array => DataSiswaSupport::rombelOptions(auth()->user()))
                    ->query(function (Builder $query, array $data): Builder {
                        $value = $data['value'] ?? null;

                        if (blank($value)) {
                            return $query;
                        }

                        return $query->whereJsonContains('boarding_rombel_scope', $value);
                    }),
                Tables\Filters\SelectFilter::make('guru_walas_scope')
                    ->label('Scope Walas')
                    ->options(fn (): array => DataSiswaSupport::rombelOptions(auth()->user()))
                    ->query(function (Builder $query, array $data): Builder {
                        $value = $data['value'] ?? null;

                        if (blank($value)) {
                            return $query;
                        }

                        return $query->whereJsonContains('guru_walas_scope', $value);
                    }),
                Tables\Filters\SelectFilter::make('division_template')
                    ->label('Divisi')
                    ->options(AdminRoleTemplateSupport::options())
                    ->multiple()
                    ->searchable()
                    ->query(function (Builder $query, array $data): Builder {
                        $values = collect($data['values'] ?? [])
                            ->map(fn ($value): string => trim((string) $value))
                            ->filter()
                            ->values()
                            ->all();

                        return AdminRoleTemplateSupport::applyTemplateFilterToQuery($query, $values);
                    }),
                Tables\Filters\SelectFilter::make('uses_default_password')
                    ->label('Status Password')
                    ->options([
                        'default' => 'Default',
                        'changed' => 'Sudah diganti',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $value = $data['value'] ?? null;

                        if ($value === null || $value === '') {
                            return $query;
                        }

                        return $query->where('uses_default_password', $value === 'default');
                    }),
                Tables\Filters\SelectFilter::make('guru_link_status')
                    ->label('Tautan Guru')
                    ->options([
                        'linked' => 'Tertaut ke Guru/Tendik',
                        'not_linked' => 'Belum tertaut',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $value = $data['value'] ?? null;

                        return match ($value) {
                            'linked' => $query->whereNotNull('guru_tendik_id'),
                            'not_linked' => $query->whereNull('guru_tendik_id'),
                            default => $query,
                        };
                    }),
            ])
            ->actions([
                Action::make('copyGuruAccess')
                    ->label('Copy Akses Guru')
                    ->icon('heroicon-o-document-duplicate')
                    ->color('info')
                    ->visible(fn (User $record): bool => auth()->user()?->hasRole('admin') && $record->hasRole('guru'))
                    ->schema([
                        Forms\Components\Select::make('target_user_id')
                            ->label('Salin ke akun guru')
                            ->options(fn (User $record): array => User::searchGuruAccountOptions((int) $record->getKey(), limit: 25))
                            ->getSearchResultsUsing(fn (User $record, string $search): array => User::searchGuruAccountOptions((int) $record->getKey(), $search))
                            ->getOptionLabelUsing(fn ($value): ?string => User::resolveNameOptionLabel($value))
                            ->searchable()
                            ->required()
                            ->helperText('Hanya akses yang disalin. Nama akun, username, email, dan tautan Guru/Tendik milik akun target tetap dipertahankan.'),
                    ])
                    ->modalHeading('Copy akses guru')
                    ->modalDescription('Salin pengaturan akses guru dari akun ini ke akun guru lain tanpa mengubah identitas akun target.')
                    ->modalSubmitActionLabel('Copy akses')
                    ->action(function (User $record, array $data): void {
                        /** @var ?User $target */
                        $target = User::query()->find($data['target_user_id'] ?? null);

                        if (! $target) {
                            throw ValidationException::withMessages([
                                'target_user_id' => 'Akun guru tujuan tidak ditemukan.',
                            ]);
                        }

                        static::copyGuruAccess($record, $target);
                    })
                    ->successNotificationTitle('Akses guru berhasil disalin.'),
                Action::make('assignDivisiTambahan')
                    ->label('Tugas Tambahan')
                    ->icon('heroicon-o-briefcase')
                    ->color('warning')
                    ->visible(fn (User $record): bool => auth()->user()?->hasRole('admin') && $record->hasRole('guru'))
                    ->schema([
                        Forms\Components\CheckboxList::make('template_keys')
                            ->label('Tambahkan akses divisi')
                            ->options(AdminRoleTemplateSupport::options())
                            ->columns(['default' => 1, 'md' => 2])
                            ->helperText('Preset ini menambah akses modul untuk akun guru tanpa mengganti role guru utama.')
                            ->required(),
                    ])
                    ->modalHeading('Tambahkan tugas divisi ke akun guru')
                    ->modalDescription('Gunakan ini jika guru juga menangani sarpras, BK, humas, proker, atau divisi lain.')
                    ->modalSubmitActionLabel('Tambahkan akses')
                    ->action(function (User $record, array $data): void {
                        static::applyDivisionTemplatesToUser($record, $data['template_keys'] ?? [], [
                            'source' => 'user_action',
                        ]);
                    })
                    ->successNotificationTitle('Akses divisi tambahan berhasil ditambahkan.'),
                Action::make('revokeDivisiTambahan')
                    ->label('Cabut Divisi')
                    ->icon('heroicon-o-no-symbol')
                    ->color('danger')
                    ->visible(fn (User $record): bool => auth()->user()?->hasRole('admin') && $record->hasRole('guru'))
                    ->fillForm(fn (User $record): array => [
                        'template_keys' => AdminRoleTemplateSupport::matchedTemplateKeysForUser($record),
                    ])
                    ->schema([
                        Forms\Components\CheckboxList::make('template_keys')
                            ->label('Cabut akses divisi')
                            ->options(AdminRoleTemplateSupport::options())
                            ->columns(['default' => 1, 'md' => 2])
                            ->helperText('Cabut hanya divisi yang memang ingin dilepas. Modul terkait akan diturunkan dari akses akun ini.'),
                    ])
                    ->requiresConfirmation()
                    ->modalHeading('Cabut akses divisi dari akun guru')
                    ->modalDescription('Gunakan dengan hati-hati. Jika sebelumnya ada set manual pada modul yang sama, cek ulang matrix akses modul setelah pencabutan.')
                    ->modalSubmitActionLabel('Cabut akses')
                    ->action(function (User $record, array $data): void {
                        static::removeDivisionTemplatesFromUser($record, $data['template_keys'] ?? [], [
                            'source' => 'user_action',
                        ]);
                    })
                    ->successNotificationTitle('Akses divisi berhasil dicabut.'),
                Action::make('resetPasswordDefault')
                    ->label('Reset Password')
                    ->icon('heroicon-o-key')
                    ->color('warning')
                    ->visible(fn (User $record): bool => auth()->user()?->hasRole('admin') && (int) $record->getKey() !== (int) auth()->id())
                    ->requiresConfirmation()
                    ->modalHeading('Reset password default akun ini?')
                    ->modalDescription('Sistem akan membuat password default baru lalu menampilkan username dan password baru untuk dibagikan.')
                    ->modalSubmitActionLabel('Reset password')
                    ->action(function (User $record): void {
                        $result = AdminUserCredentialSupport::resetDefaultPassword($record);
                        $token = AdminUserCredentialShareSupport::store([[
                            'name' => $result['name'],
                            'username' => $result['username'],
                            'password' => $result['password'],
                            'created' => false,
                        ]], [
                            'generated_by' => auth()->user()?->name,
                        ]);

                        Notification::make()
                            ->title('Password default berhasil direset.')
                            ->body(new HtmlString(AdminUserCredentialSupport::credentialsAsCopyableHtml([[
                                'name' => $result['name'],
                                'username' => $result['username'],
                                'password' => $result['password'],
                                'created' => false,
                            ]]).AdminUserCredentialShareSupport::actionsHtml($token)))
                            ->warning()
                            ->persistent()
                            ->send();
                    }),
                EditAction::make(),
                static::makeDeleteTableAction('akun admin')
                    ->visible(fn (User $record): bool => $record->username !== 'putra'),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    BulkAction::make('bulkAssignDivisiTambahan')
                        ->label('Tambah Divisi')
                        ->icon('heroicon-o-briefcase')
                        ->color('warning')
                        ->visible(fn (): bool => auth()->user()?->hasRole('admin'))
                        ->schema([
                            Forms\Components\CheckboxList::make('template_keys')
                                ->label('Tambahkan akses divisi')
                                ->options(AdminRoleTemplateSupport::options())
                                ->columns(['default' => 1, 'md' => 2])
                                ->helperText('Akan diterapkan ke semua akun guru yang dipilih.'),
                        ])
                        ->requiresConfirmation()
                        ->modalHeading('Tambahkan akses divisi ke akun terpilih')
                        ->modalDescription('Gunakan untuk memberi akses Sarpras, BK, Humas, dan divisi lain ke banyak akun guru sekaligus.')
                        ->modalSubmitActionLabel('Tambahkan akses')
                        ->deselectRecordsAfterCompletion()
                        ->action(function (Collection $records, array $data): void {
                            $templateKeys = static::normalizeDivisionTemplateKeys($data['template_keys'] ?? []);

                            if ($templateKeys === []) {
                                throw ValidationException::withMessages([
                                    'template_keys' => 'Pilih minimal satu divisi tambahan.',
                                ]);
                            }

                            $targets = $records->filter(fn (User $record): bool => $record->hasRole('guru'));

                            if ($targets->isEmpty()) {
                                throw ValidationException::withMessages([
                                    'template_keys' => 'Pilih minimal satu akun dengan role guru.',
                                ]);
                            }

                            $targets->each(fn (User $record): bool => static::applyDivisionTemplatesToUser($record, $templateKeys, [
                                'source' => 'user_bulk_action',
                            ]));
                        })
                        ->successNotificationTitle('Akses divisi massal berhasil ditambahkan.'),
                    BulkAction::make('bulkRevokeDivisiTambahan')
                        ->label('Cabut Divisi')
                        ->icon('heroicon-o-no-symbol')
                        ->color('danger')
                        ->visible(fn (): bool => auth()->user()?->hasRole('admin'))
                        ->schema([
                            Forms\Components\CheckboxList::make('template_keys')
                                ->label('Cabut akses divisi')
                                ->options(AdminRoleTemplateSupport::options())
                                ->columns(['default' => 1, 'md' => 2])
                                ->helperText('Akan dicabut dari semua akun guru yang dipilih.'),
                        ])
                        ->requiresConfirmation()
                        ->modalHeading('Cabut akses divisi dari akun terpilih')
                        ->modalDescription('Gunakan dengan hati-hati. Bulk action ini akan menurunkan modul divisi terkait dari akun guru terpilih.')
                        ->modalSubmitActionLabel('Cabut akses')
                        ->deselectRecordsAfterCompletion()
                        ->action(function (Collection $records, array $data): void {
                            $templateKeys = static::normalizeDivisionTemplateKeys($data['template_keys'] ?? []);

                            if ($templateKeys === []) {
                                throw ValidationException::withMessages([
                                    'template_keys' => 'Pilih minimal satu divisi untuk dicabut.',
                                ]);
                            }

                            $targets = $records->filter(fn (User $record): bool => $record->hasRole('guru'));

                            if ($targets->isEmpty()) {
                                throw ValidationException::withMessages([
                                    'template_keys' => 'Pilih minimal satu akun dengan role guru.',
                                ]);
                            }

                            $targets->each(fn (User $record): bool => static::removeDivisionTemplatesFromUser($record, $templateKeys, [
                                'source' => 'user_bulk_action',
                            ]));
                        })
                        ->successNotificationTitle('Akses divisi massal berhasil dicabut.'),
                    BulkAction::make('bulkResetPasswordDefault')
                        ->label('Reset Password')
                        ->icon('heroicon-o-key')
                        ->color('warning')
                        ->visible(fn (): bool => auth()->user()?->hasRole('admin'))
                        ->requiresConfirmation()
                        ->modalHeading('Reset password default akun terpilih?')
                        ->modalDescription('Sistem akan membuat password default baru untuk semua akun terpilih, lalu menampilkan username dan password baru masing-masing akun.')
                        ->modalSubmitActionLabel('Reset password')
                        ->deselectRecordsAfterCompletion()
                        ->action(function (Collection $records): void {
                            $summary = AdminUserCredentialSupport::resetDefaultPasswordsForUsers($records);
                            $token = AdminUserCredentialShareSupport::store($summary['credentials'], [
                                'generated_by' => auth()->user()?->name,
                            ]);

                            Notification::make()
                                ->title('Reset password massal selesai.')
                                ->body(new HtmlString(
                                    "{$summary['reset']} akun direset, {$summary['skipped']} akun dilewati.".
                                    '<div class="mt-2">'.AdminUserCredentialSupport::credentialsAsCopyableHtml($summary['credentials']).'</div>'.
                                    AdminUserCredentialShareSupport::actionsHtml($token)
                                ))
                                ->warning()
                                ->persistent()
                                ->send();
                        }),
                    static::makeDeleteBulkTableAction(),
                ]),
            ]);
    }

    public static function canDelete(Model $record): bool
    {
        return static::userCanModule('manage')
            && $record instanceof User
            && $record->username !== 'putra'
            && (int) $record->getKey() !== (int) auth()->id();
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['roles', 'guruTendik']);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }

    public static function moduleAccessLevelOptions(): array
    {
        return AdminModuleAccess::levelOptions();
    }

    /**
     * @return array<string, string>
     */
    protected static function createDefaultModuleAccessLevels(): array
    {
        $levels = AdminModuleAccess::normalizeLevels([]);
        $presetTemplate = static::requestedAccessTemplate();

        if ($presetTemplate !== null) {
            $levels = AdminRoleTemplateSupport::defaultLevelsForTemplate($presetTemplate);
        } else {
            $presetRole = trim((string) request()->query('preset_role'));

            if ($presetRole !== '') {
                $levels = AdminModuleAccess::defaultLevelsForRoleNames(collect([$presetRole]));
            }
        }

        return static::mergeAddonTemplatesIntoLevels($levels, static::defaultAccessAddonState());
    }

    /**
     * @return array<int, int|string>
     */
    protected static function defaultRoleState(): array
    {
        $presetTemplate = static::requestedAccessTemplate();

        if ($presetTemplate !== null) {
            return AdminRoleTemplateSupport::defaultRoleIdsForTemplate($presetTemplate);
        }

        $presetRole = trim((string) request()->query('preset_role'));

        if ($presetRole === '') {
            return [];
        }

        return Role::query()->where('name', $presetRole)->pluck('id')->all();
    }

    /**
     * @return array<int, string>
     */
    public static function defaultAccessAddonState(): array
    {
        $presetAddons = trim((string) request()->query('preset_addons'));

        if ($presetAddons === '') {
            return [];
        }

        return collect(explode(',', $presetAddons))
            ->map(fn ($value): string => trim((string) $value))
            ->filter(fn (string $value): bool => array_key_exists($value, AdminRoleTemplateSupport::definitions()))
            ->unique()
            ->values()
            ->all();
    }

    protected static function requestedAccessTemplate(): ?string
    {
        $presetTemplate = trim((string) request()->query('preset_template'));

        return $presetTemplate !== '' && array_key_exists($presetTemplate, AdminRoleTemplateSupport::definitions())
            ? $presetTemplate
            : null;
    }

    /**
     * @param  array<string, string>  $levels
     * @param  array<int, string>  $templateKeys
     * @return array<string, string>
     */
    public static function mergeAddonTemplatesIntoLevels(array $levels, array $templateKeys): array
    {
        if ($templateKeys === []) {
            return AdminModuleAccess::normalizeLevels($levels);
        }

        return AdminModuleAccess::normalizeLevels(array_merge(
            AdminModuleAccess::normalizeLevels($levels),
            AdminRoleTemplateSupport::mergedLevelsForTemplates($templateKeys),
        ));
    }

    /**
     * @param  array<int, string>  $templateKeys
     * @return array<int, string>
     */
    public static function normalizeDivisionTemplateKeys(array $templateKeys): array
    {
        return AdminRoleTemplateSupport::normalizeTemplateKeys($templateKeys);
    }

    /**
     * @param  array<int, string>  $templateKeys
     * @param  array{source?:string|null,note?:string|null,actor_user_id?:int|null}  $context
     */
    public static function applyDivisionTemplatesToUser(User $user, array $templateKeys, array $context = []): void
    {
        $templateKeys = static::normalizeDivisionTemplateKeys($templateKeys);

        if ($templateKeys === []) {
            throw ValidationException::withMessages([
                'template_keys' => 'Pilih minimal satu divisi tambahan.',
            ]);
        }

        $beforeLevels = AdminModuleAccess::effectiveLevels($user);
        $mergedLevels = AdminModuleAccess::normalizeLevels(array_merge(
            $beforeLevels,
            AdminRoleTemplateSupport::mergedLevelsForTemplates($templateKeys),
        ));
        $roleState = static::roleIdsForUser($user);
        $pamongRoleNames = AdminRoleTemplateSupport::boardingPamongRoleNamesForTemplates($templateKeys);

        if ($pamongRoleNames !== []) {
            $pamongRoleIds = Role::query()
                ->whereIn('name', $pamongRoleNames)
                ->pluck('id')
                ->all();

            $roleState = collect($roleState)
                ->merge($pamongRoleIds)
                ->unique()
                ->values()
                ->all();
        }

        static::syncScopedModuleConfiguration($user, [
            'roles' => $roleState,
            'module_access_levels' => $mergedLevels,
            'allowed_navigation_items' => $user->allowed_navigation_items ?? [],
        ]);

        AdminAccessChangeLogSupport::log(
            $user,
            'add',
            $templateKeys,
            $beforeLevels,
            $mergedLevels,
            $context,
        );
    }

    /**
     * @param  array<int, string>  $templateKeys
     * @param  array{source?:string|null,note?:string|null,actor_user_id?:int|null}  $context
     */
    public static function removeDivisionTemplatesFromUser(User $user, array $templateKeys, array $context = []): void
    {
        $templateKeys = static::normalizeDivisionTemplateKeys($templateKeys);

        if ($templateKeys === []) {
            throw ValidationException::withMessages([
                'template_keys' => 'Pilih minimal satu divisi untuk dicabut.',
            ]);
        }

        $beforeLevels = AdminModuleAccess::effectiveLevels($user);
        $reducedLevels = AdminRoleTemplateSupport::removeTemplatesFromLevels(
            $beforeLevels,
            $templateKeys,
        );

        static::syncScopedModuleConfiguration($user, [
            'roles' => static::roleIdsForUser($user),
            'module_access_levels' => $reducedLevels,
            'allowed_navigation_items' => $user->allowed_navigation_items ?? [],
        ]);

        AdminAccessChangeLogSupport::log(
            $user,
            'remove',
            $templateKeys,
            $beforeLevels,
            $reducedLevels,
            $context,
        );
    }

    /**
     * @return array<int, string>
     */
    public static function suggestedAccessTemplatesForGuruId(mixed $guruTendikId): array
    {
        return AdminRoleTemplateSupport::suggestedTemplatesForGuruTendik(
            static::guruTendikForAccessSuggestion($guruTendikId)
        );
    }

    /**
     * @return array<string, array<int, string>>
     */
    public static function suggestedAccessTemplateReasonsForGuruId(mixed $guruTendikId): array
    {
        return AdminRoleTemplateSupport::suggestedTemplateReasonsForGuruTendik(
            static::guruTendikForAccessSuggestion($guruTendikId)
        );
    }

    protected static function guruTendikForAccessSuggestion(mixed $guruTendikId): ?GuruTendik
    {
        $id = (int) $guruTendikId;

        if ($id <= 0) {
            return null;
        }

        if (array_key_exists($id, static::$guruTendikAccessSuggestionCache)) {
            return static::$guruTendikAccessSuggestionCache[$id];
        }

        return static::$guruTendikAccessSuggestionCache[$id] = GuruTendik::query()
            ->select(['id', 'nama'])
            ->with(['tugasTambahan:id,guru_tendik_id,tugas_tambahan,tmt'])
            ->find($id);
    }

    public static function selectedRoleNames(mixed $state): Collection
    {
        $values = collect($state)
            ->map(fn ($value): string => trim((string) $value))
            ->filter()
            ->values();

        if ($values->isEmpty()) {
            return collect();
        }

        $roleNames = $values
            ->filter(fn (string $value): bool => ! ctype_digit($value))
            ->values();

        if ($roleNames->isNotEmpty()) {
            return $roleNames;
        }

        $cacheKey = $values
            ->map(fn (string $value): string => (string) (int) $value)
            ->sort()
            ->implode(',');

        if (array_key_exists($cacheKey, static::$selectedRoleNamesCache)) {
            return static::$selectedRoleNamesCache[$cacheKey];
        }

        return static::$selectedRoleNamesCache[$cacheKey] = Role::query()
            ->whereIn('id', $values->all())
            ->pluck('name')
            ->map(fn (string $name): string => trim($name))
            ->filter()
            ->values();
    }

    public static function resolveAccessLevelFromPermissionNames(array $permissionNames, string $prefix): string
    {
        return AdminModuleAccess::levelFromPermissionNames($permissionNames, $prefix);
    }

    public static function managedScopedPermissionNames(): array
    {
        return AdminModuleAccess::managedPermissionNames();
    }

    public static function resolvePermissionNamesFromState(mixed $state): Collection
    {
        $values = collect($state)
            ->map(fn ($value): string => trim((string) $value))
            ->filter()
            ->values();

        if ($values->isEmpty()) {
            return collect();
        }

        $permissionNames = $values
            ->filter(fn (string $value): bool => ! ctype_digit($value))
            ->values();

        if ($permissionNames->isNotEmpty()) {
            return $permissionNames;
        }

        return Permission::query()
            ->whereIn('id', $values->all())
            ->pluck('name')
            ->map(fn (string $name): string => trim($name))
            ->filter()
            ->values();
    }

    public static function normalizeGuruScopedModuleState(User $user, array $state): array
    {
        $state['module_access_levels'] = AdminModuleAccess::normalizeLevels(
            $state['module_access_levels'] ?? $user->module_access_levels ?? [],
        );

        return $state;
    }

    public static function extractGuruAccessState(User $user): array
    {
        $moduleAccessLevels = collect(AdminModuleAccess::effectiveLevels($user))
            ->filter(fn (string $level, string $prefix): bool => in_array($prefix, static::GURU_COPYABLE_PERMISSION_PREFIXES, true))
            ->all();

        return [
            'module_access_levels' => $moduleAccessLevels,
        ];
    }

    public static function copyGuruAccess(User $source, User $target): void
    {
        if ((int) $source->getKey() === (int) $target->getKey()) {
            throw ValidationException::withMessages([
                'target_user_id' => 'Pilih akun guru tujuan yang berbeda.',
            ]);
        }

        if (! $source->hasRole('guru')) {
            throw ValidationException::withMessages([
                'target_user_id' => 'Sumber akses harus akun guru.',
            ]);
        }

        if (! $target->hasRole('guru')) {
            throw ValidationException::withMessages([
                'target_user_id' => 'Akun tujuan harus memiliki role guru.',
            ]);
        }

        DB::transaction(function () use ($source, $target): void {
            $state = static::extractGuruAccessState($source);
            $mergedLevels = AdminModuleAccess::normalizeLevels(array_merge(
                AdminModuleAccess::effectiveLevels($target),
                $state['module_access_levels'],
            ));

            static::syncScopedModuleConfiguration($target, [
                'roles' => static::roleIdsForUser($target),
                'module_access_levels' => $mergedLevels,
                'allowed_navigation_items' => $target->allowed_navigation_items ?? [],
            ]);
        });
    }

    protected static function isGuruCopyablePermissionName(string $permissionName): bool
    {
        foreach (static::GURU_COPYABLE_PERMISSION_PREFIXES as $prefix) {
            if (str_starts_with($permissionName, $prefix.'.')) {
                return true;
            }
        }

        return false;
    }

    public static function syncScopedModuleConfiguration(User $user, array $state): void
    {
        $roleState = $state['roles'] ?? static::roleIdsForUser($user);
        $roleNames = static::selectedRoleNames($roleState);

        if ($user->exists) {
            $user->syncRoles($roleNames->all());
            unset(static::$userRoleIdsCache[(int) $user->getKey()]);
            $user->load('roles');
        }

        $state = static::normalizeGuruScopedModuleState($user, $state);

        $baseLevels = $user->exists
            ? AdminModuleAccess::effectiveLevels($user)
            : AdminModuleAccess::defaultLevelsForRoleNames($roleNames);

        $managedLevels = AdminModuleAccess::normalizeLevels(array_merge(
            $baseLevels,
            $state['module_access_levels'] ?? [],
        ));
        $advancedItems = collect($state['allowed_navigation_items'] ?? [])
            ->map(fn ($item): string => trim((string) $item))
            ->filter()
            ->intersect(array_keys(AdminModuleAccess::advancedNavigationItemOptions()))
            ->values()
            ->all();

        $remainingPermissionNames = $user->permissions()
            ->pluck('name')
            ->reject(fn (string $name): bool => in_array($name, AdminModuleAccess::managedPermissionNames(), true))
            ->values()
            ->all();

        $user->syncPermissions($remainingPermissionNames);

        $resolvedItems = $roleNames->contains('admin')
            ? []
            : AdminModuleAccess::deriveNavigationItems($managedLevels, $advancedItems);
        $resolvedGroups = $roleNames->contains('admin')
            ? []
            : AdminModuleAccess::deriveNavigationGroups($resolvedItems);

        $user->forceFill([
            'module_access_levels' => $managedLevels,
            'allowed_navigation_groups' => $resolvedGroups,
            'allowed_navigation_items' => $advancedItems,
        ])->saveQuietly();
    }

    /**
     * @return array<int, int|string>
     */
    protected static function roleIdsForUser(User $user): array
    {
        $cacheKey = (int) $user->getKey();

        if ($cacheKey > 0 && array_key_exists($cacheKey, static::$userRoleIdsCache)) {
            return static::$userRoleIdsCache[$cacheKey];
        }

        $roleIds = $user->relationLoaded('roles')
            ? $user->roles->pluck('id')->all()
            : $user->roles()->pluck('id')->all();

        if ($cacheKey > 0) {
            static::$userRoleIdsCache[$cacheKey] = $roleIds;
        }

        return $roleIds;
    }
}
