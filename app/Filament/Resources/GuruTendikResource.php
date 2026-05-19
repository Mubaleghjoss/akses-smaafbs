<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasConfirmedDeleteActions;
use App\Filament\Concerns\HasModulePermissions;
use App\Filament\Concerns\HasOptimizedAdminTable;
use App\Filament\Resources\GuruTendikResource\Pages;
use App\Filament\Resources\GuruTendikResource\RelationManagers\BerkasGurusRelationManager;
use App\Filament\Resources\GuruTendikResource\RelationManagers\TugasTambahanRelationManager;
use App\Models\BerkasGuru;
use App\Models\GuruTendik;
use App\Support\Admin\AdminRoleTemplateSupport;
use App\Support\Admin\AdminUserCredentialShareSupport;
use App\Support\GuruTendik\GuruTendikAccountProvisioner;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;

class GuruTendikResource extends Resource
{
    use HasConfirmedDeleteActions;
    use HasModulePermissions;
    use HasOptimizedAdminTable;

    protected static ?string $model = GuruTendik::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-user-circle';

    protected static string|\UnitEnum|null $navigationGroup = 'Guru/Tendik';

    protected static ?string $navigationLabel = 'Guru Tendik';

    protected static ?string $modelLabel = 'guru/tendik';

    protected static ?string $pluralModelLabel = 'Guru/Tendik';

    protected static ?int $navigationSort = 10;

    protected static ?string $permissionPrefix = 'guru_tendik';

    /**
     * @var array<int, ?BerkasGuru>
     */
    protected static array $tugasTambahanGoogleDriveRecordCache = [];

    /**
     * @var array<int, Collection<int, BerkasGuru>>
     */
    protected static array $tugasTambahanGoogleDriveRecordsCache = [];

    /**
     * @var array<int, string>
     */
    protected static array $tugasTambahanGoogleDriveSummaryLabelCache = [];

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Identitas Guru / Tendik')
                    ->columns(['default' => 1, 'md' => 2])
                    ->schema([
                        Forms\Components\TextInput::make('nama')
                            ->required()
                            ->maxLength(150),
                        Forms\Components\Select::make('jenis_ptk')
                            ->options(GuruTendik::jenisPtkOptions())
                            ->native(false)
                            ->required(),
                        Forms\Components\TextInput::make('nip')
                            ->label('NIY')
                            ->maxLength(50)
                            ->default(null),
                        Forms\Components\TextInput::make('nuptk')
                            ->maxLength(50)
                            ->default(null),
                        Forms\Components\TextInput::make('nik')
                            ->maxLength(50)
                            ->default(null),
                        Forms\Components\Select::make('jk')
                            ->label('Jenis Kelamin')
                            ->options(GuruTendik::jkOptions())
                            ->native(false)
                            ->required(),
                        Forms\Components\TextInput::make('tempat_lahir')
                            ->maxLength(100)
                            ->default(null),
                        Forms\Components\DatePicker::make('tanggal_lahir'),
                        Forms\Components\TextInput::make('status')
                            ->default('aktif'),
                    ]),
                Section::make('Profil Publik')
                    ->description('Informasi ini dipakai untuk halaman biografi publik saat data guru/tendik dihubungkan ke struktur organisasi.')
                    ->columns(['default' => 1, 'md' => 2])
                    ->schema([
                        Forms\Components\FileUpload::make('foto_profil')
                            ->label('Foto Profil Publik')
                            ->disk('public')
                            ->directory('guru-tendik/profil')
                            ->image()
                            ->imageEditor()
                            ->maxSize(4096)
                            ->helperText('Opsional. Jika kosong, halaman publik akan memakai foto dari struktur organisasi bila tersedia. Jika akun guru/tendik terhubung dan belum punya avatar custom, foto ini juga dipakai sebagai ikon akun admin.'),
                        Forms\Components\Textarea::make('bio_singkat')
                            ->label('Biografi Singkat')
                            ->rows(5)
                            ->maxLength(2000)
                            ->columnSpanFull()
                            ->helperText('Tulis ringkasan singkat profil untuk halaman biografi publik.'),
                    ]),
                Section::make('Sinkron Google Drive SK Tugas Tambahan')
                    ->description('Setelah form disimpan, setiap file SK akan dibuatkan atau diperbarui di Berkas Guru. Dari sana sistem akan menaruh file ke antrean Google Drive bila sinkron otomatis aktif.')
                    ->columns(['default' => 1, 'md' => 2])
                    ->schema([
                        Forms\Components\Placeholder::make('tugas_tambahan_gdrive_summary')
                            ->label('Ringkasan Status')
                            ->content(fn (?GuruTendik $record): string => static::tugasTambahanGoogleDriveSummaryLabel($record)),
                        Forms\Components\Placeholder::make('tugas_tambahan_gdrive_instruction')
                            ->label('Cara Pakai')
                            ->content(fn (?GuruTendik $record): string => $record
                                ? 'Simpan perubahan dahulu. Setelah itu status per file akan muncul di bawah setiap baris tugas tambahan. Jika perlu upload ulang manual, lanjutkan dari menu Berkas Guru.'
                                : 'Simpan data guru lebih dulu untuk melihat status sinkron Google Drive setiap file SK.'),
                    ]),
                Section::make('History Tugas Tambahan')
                    ->description('Isi tugas tambahan yang pernah atau sedang dipegang. Setiap baris menjadi histori penugasan tersendiri.')
                    ->schema([
                        Forms\Components\Repeater::make('tugasTambahan')
                            ->relationship('tugasTambahan')
                            ->label('Tugas Tambahan')
                            ->defaultItems(0)
                            ->addActionLabel('Tambahkan Tugas Tambahan')
                            ->collapsed()
                            ->itemLabel(fn (array $state): ?string => $state['tugas_tambahan'] ?? null)
                            ->schema([
                                Forms\Components\TextInput::make('tugas_tambahan')
                                    ->label('Tugas Tambahan')
                                    ->required()
                                    ->maxLength(150),
                                Forms\Components\TextInput::make('no_sk')
                                    ->label('No. SK')
                                    ->required()
                                    ->maxLength(100),
                                Forms\Components\DatePicker::make('tmt')
                                    ->label('TMT')
                                    ->required(),
                                Forms\Components\DatePicker::make('tst')
                                    ->label('TST')
                                    ->after('tmt'),
                                Forms\Components\FileUpload::make('sk_file_path')
                                    ->label('File SK (PDF)')
                                    ->disk('public')
                                    ->directory('guru-tendik/sk')
                                    ->acceptedFileTypes(['application/pdf'])
                                    ->openable()
                                    ->downloadable()
                                    ->helperText('Setelah data guru disimpan, file SK ini akan dibuatkan ke Berkas Guru lalu masuk antrean Google Drive sesuai pengaturan sinkron.'),
                                Forms\Components\Hidden::make('berkas_guru_id'),
                                Forms\Components\Placeholder::make('gdrive_sk_status')
                                    ->label('Status Google Drive')
                                    ->content(fn ($get): string => static::tugasTambahanGoogleDriveStatusLabel($get('berkas_guru_id'))),
                                Forms\Components\Placeholder::make('gdrive_sk_progress')
                                    ->label('Progress')
                                    ->content(fn ($get): string => static::tugasTambahanGoogleDriveProgressLabel($get('berkas_guru_id'))),
                                Forms\Components\Placeholder::make('gdrive_sk_link')
                                    ->label('Link Google Drive')
                                    ->content(fn ($get): HtmlString => static::tugasTambahanGoogleDriveLink($get('berkas_guru_id'))),
                                Forms\Components\Textarea::make('keterangan')
                                    ->rows(2)
                                    ->columnSpanFull(),
                                Forms\Components\Placeholder::make('gdrive_sk_message')
                                    ->label('Pesan Sinkron')
                                    ->content(fn ($get): string => static::tugasTambahanGoogleDriveMessage($get('berkas_guru_id')))
                                    ->columnSpanFull(),
                            ])
                            ->columns(['default' => 1, 'md' => 2])
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return static::optimizeAdminTable(
            $table,
            searchPlaceholder: 'Cari nama guru, NIY, NUPTK, NIK, mapel, atau akun login...',
            emptyStateHeading: 'Belum ada data guru/tendik',
            emptyStateDescription: 'Tambahkan data guru atau sinkronkan akun agar pengelolaan dokumen lebih mudah.'
        )
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->select([
                'id',
                'nama',
                'nip',
                'nuptk',
                'nik',
                'jenis_ptk',
                'jk',
                'tempat_lahir',
                'tanggal_lahir',
                'status',
                'created_at',
                'updated_at',
            ]))
            ->columns([
                Tables\Columns\TextColumn::make('nama')
                    ->label('Nama')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('nip')
                    ->label('NIY')
                    ->searchable(),
                Tables\Columns\TextColumn::make('nuptk')
                    ->searchable(),
                Tables\Columns\TextColumn::make('nik')
                    ->searchable(),
                Tables\Columns\TextColumn::make('jenis_ptk')
                    ->searchable()
                    ->badge(),
                Tables\Columns\TextColumn::make('jk')
                    ->label('JK')
                    ->badge(),
                Tables\Columns\TextColumn::make('tempat_lahir')
                    ->searchable(),
                Tables\Columns\TextColumn::make('tanggal_lahir')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge(),
                Tables\Columns\TextColumn::make('tugas_tambahan_aktif_count')
                    ->label('Tugas Aktif')
                    ->counts('tugasTambahanAktif')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('userAccount.username')
                    ->label('Akun Login')
                    ->placeholder('Belum ada akun')
                    ->searchable(),
                Tables\Columns\TextColumn::make('userAccount_division_access_labels')
                    ->label('Akses Divisi')
                    ->state(function (GuruTendik $record): ?string {
                        if (! $record->userAccount) {
                            return null;
                        }

                        $labels = AdminRoleTemplateSupport::matchedTemplateLabelsForUser($record->userAccount);

                        return $labels === [] ? null : implode(', ', $labels);
                    })
                    ->badge()
                    ->separator(',')
                    ->wrap()
                    ->placeholder('Belum ada badge divisi'),
                Tables\Columns\TextColumn::make('userAccount.uses_default_password')
                    ->label('Status Password')
                    ->state(function (GuruTendik $record): ?string {
                        if (! $record->userAccount) {
                            return null;
                        }

                        return $record->userAccount->uses_default_password ? 'Default' : 'Sudah diganti';
                    })
                    ->badge()
                    ->color(fn (?string $state): string => $state === 'Default' ? 'warning' : 'success')
                    ->placeholder('Belum ada akun')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('userAccount.guru_mapel_label')
                    ->label('Mapel')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('userAccount.guru_walas_scope')
                    ->label('Scope Walas')
                    ->state(fn (GuruTendik $record): string => collect($record->userAccount?->guruWalasScopes() ?? [])->implode(', ') ?: '-')
                    ->wrap()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('jenis_ptk')
                    ->label('Jenis PTK')
                    ->options(GuruTendik::jenisPtkOptions()),
                Tables\Filters\SelectFilter::make('jk')
                    ->label('Jenis Kelamin')
                    ->options(GuruTendik::jkOptions()),
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options(fn (): array => GuruTendik::statusOptions()),
                Tables\Filters\SelectFilter::make('division_template')
                    ->label('Akses Divisi')
                    ->options(AdminRoleTemplateSupport::options())
                    ->multiple()
                    ->searchable()
                    ->query(function (Builder $query, array $data): Builder {
                        $values = collect($data['values'] ?? [])
                            ->map(fn ($value): string => trim((string) $value))
                            ->filter()
                            ->values()
                            ->all();

                        if ($values === []) {
                            return $query;
                        }

                        return $query->whereHas('userAccount', function (Builder $userQuery) use ($values): void {
                            AdminRoleTemplateSupport::applyTemplateFilterToQuery($userQuery, $values);
                        });
                    }),
                Tables\Filters\SelectFilter::make('user_account_status')
                    ->label('Status Akun')
                    ->options([
                        'has_account' => 'Punya akun',
                        'no_account' => 'Belum ada akun',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $value = $data['value'] ?? null;

                        return match ($value) {
                            'has_account' => $query->whereHas('userAccount'),
                            'no_account' => $query->whereDoesntHave('userAccount'),
                            default => $query,
                        };
                    }),
                Tables\Filters\SelectFilter::make('user_password_status')
                    ->label('Status Password')
                    ->options([
                        'default' => 'Default',
                        'changed' => 'Sudah diganti',
                        'no_account' => 'Belum ada akun',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $value = $data['value'] ?? null;

                        return match ($value) {
                            'default' => $query->whereHas('userAccount', fn (Builder $userQuery): Builder => $userQuery->where('uses_default_password', true)),
                            'changed' => $query->whereHas('userAccount', fn (Builder $userQuery): Builder => $userQuery->where('uses_default_password', false)),
                            'no_account' => $query->whereDoesntHave('userAccount'),
                            default => $query,
                        };
                    }),
            ])
            ->actions([
                Action::make('kelolaAkun')
                    ->label(fn (GuruTendik $record): string => $record->userAccount ? 'Kelola Akun' : 'Buat Akun')
                    ->icon('heroicon-o-user-circle')
                    ->color('info')
                    ->url(fn (GuruTendik $record): string => $record->userAccount
                        ? UserResource::getUrl('edit', array_merge(
                            ['record' => $record->userAccount],
                            static::guruUserAccessRouteParams($record),
                        ))
                        : UserResource::getUrl('create', array_merge(
                            static::guruUserCreateRouteParams($record),
                            [
                                'link_guru_id' => $record->getKey(),
                            ],
                            static::guruUserAccessRouteParams($record),
                        )))
                    ->tooltip(fn (GuruTendik $record): string => AdminRoleTemplateSupport::suggestedTemplateReasonSummaryForGuruTendik($record))
                    ->visible(fn (): bool => auth()->user()?->hasRole('admin')),
                Action::make('aksesDivisiAdmin')
                    ->label('Akses Divisi')
                    ->icon('heroicon-o-briefcase')
                    ->color('warning')
                    ->tooltip(fn (GuruTendik $record): string => $record->userAccount
                        ? AdminRoleTemplateSupport::suggestedTemplateReasonSummaryForGuruTendik($record)
                        : 'Buat akun login guru terlebih dahulu sebelum menambahkan akses divisi admin.')
                    ->visible(fn (): bool => auth()->user()?->hasRole('admin'))
                    ->fillForm(fn (GuruTendik $record): array => [
                        'template_keys' => static::suggestedAdminAccessTemplates($record),
                    ])
                    ->form([
                        Forms\Components\Placeholder::make('akses_divisi_info')
                            ->label('Saran Otomatis')
                            ->content(fn (GuruTendik $record): string => AdminRoleTemplateSupport::suggestedTemplateReasonSummaryForGuruTendik($record)),
                        Forms\Components\CheckboxList::make('template_keys')
                            ->label('Template Divisi')
                            ->options(AdminRoleTemplateSupport::options())
                            ->columns(['default' => 1, 'md' => 2])
                            ->helperText('Template terpilih akan ditambahkan ke akun guru yang sudah ada tanpa mengganti role utama guru.'),
                    ])
                    ->modalHeading('Tambahkan Akses Divisi')
                    ->modalDescription('Gunakan untuk memberi tugas tambahan admin seperti Sarpras, BK, Humas, atau divisi lain pada akun guru yang sudah terhubung.')
                    ->modalSubmitActionLabel('Simpan Akses')
                    ->action(function (GuruTendik $record, array $data): void {
                        if (! $record->userAccount) {
                            Notification::make()
                                ->title('Akun login guru belum tersedia.')
                                ->body('Gunakan aksi Kelola Akun untuk membuat akun guru terlebih dahulu.')
                                ->warning()
                                ->send();

                            return;
                        }

                        $templateKeys = collect($data['template_keys'] ?? [])
                            ->map(fn ($value): string => trim((string) $value))
                            ->filter(fn (string $value): bool => array_key_exists($value, AdminRoleTemplateSupport::definitions()))
                            ->values()
                            ->all();

                        if ($templateKeys === []) {
                            Notification::make()
                                ->title('Pilih minimal satu divisi.')
                                ->warning()
                                ->send();

                            return;
                        }

                        UserResource::applyDivisionTemplatesToUser($record->userAccount, $templateKeys, [
                            'source' => 'guru_action',
                            'note' => 'Perubahan akses dilakukan dari tabel Guru/Tendik.',
                        ]);

                        Notification::make()
                            ->title('Akses divisi berhasil ditambahkan.')
                            ->body(AdminRoleTemplateSupport::suggestionSummary($templateKeys))
                            ->success()
                            ->send();
                    }),
                Action::make('cabutAksesDivisiAdmin')
                    ->label('Cabut Divisi')
                    ->icon('heroicon-o-no-symbol')
                    ->color('danger')
                    ->tooltip(fn (GuruTendik $record): string => $record->userAccount
                        ? 'Cabut akses divisi dari akun guru yang terhubung.'
                        : 'Belum ada akun login yang bisa dicabut akses divisinya.')
                    ->visible(fn (): bool => auth()->user()?->hasRole('admin'))
                    ->fillForm(fn (GuruTendik $record): array => [
                        'template_keys' => $record->userAccount
                            ? AdminRoleTemplateSupport::matchedTemplateKeysForUser($record->userAccount)
                            : [],
                    ])
                    ->form([
                        Forms\Components\CheckboxList::make('template_keys')
                            ->label('Cabut akses divisi')
                            ->options(AdminRoleTemplateSupport::options())
                            ->columns(['default' => 1, 'md' => 2])
                            ->helperText('Pilih divisi yang ingin dicabut dari akun guru ini.'),
                    ])
                    ->requiresConfirmation()
                    ->modalHeading('Cabut akses divisi')
                    ->modalDescription('Gunakan dengan hati-hati. Setelah pencabutan, cek ulang jika akun ini sebelumnya memakai pengaturan manual pada modul yang sama.')
                    ->modalSubmitActionLabel('Cabut akses')
                    ->action(function (GuruTendik $record, array $data): void {
                        if (! $record->userAccount) {
                            Notification::make()
                                ->title('Akun login guru belum tersedia.')
                                ->warning()
                                ->send();

                            return;
                        }

                        UserResource::removeDivisionTemplatesFromUser($record->userAccount, $data['template_keys'] ?? [], [
                            'source' => 'guru_action',
                            'note' => 'Pencabutan akses dilakukan dari tabel Guru/Tendik.',
                        ]);

                        Notification::make()
                            ->title('Akses divisi berhasil dicabut.')
                            ->success()
                            ->send();
                    }),
                Action::make('resetPasswordDefault')
                    ->label('Reset Password Default')
                    ->icon('heroicon-o-key')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Reset password akun ini?')
                    ->modalDescription('Sistem akan membuat password default baru untuk akun guru/tendik ini. Setelah reset, salin kredensial dan kirimkan ke yang bersangkutan.')
                    ->modalSubmitActionLabel('Reset sekarang')
                    ->visible(fn (GuruTendik $record): bool => auth()->user()?->hasRole('admin') && (bool) $record->userAccount)
                    ->action(function (GuruTendik $record): void {
                        $record->loadMissing('userAccount');

                        if (! $record->userAccount) {
                            Notification::make()
                                ->title('Akun login belum tersedia.')
                                ->danger()
                                ->send();

                            return;
                        }

                        $result = app(GuruTendikAccountProvisioner::class)->resetDefaultPassword($record->userAccount, $record);
                        $credentials = [[
                            'name' => $record->nama,
                            'username' => $result['username'],
                            'password' => $result['password'],
                            'created' => false,
                        ]];
                        $token = AdminUserCredentialShareSupport::store($credentials, [
                            'generated_by' => auth()->user()?->name,
                        ]);

                        Notification::make()
                            ->title('Password default berhasil direset.')
                            ->body(new HtmlString(GuruTendikAccountProvisioner::credentialsAsCopyableHtml([
                                [
                                    'guru_tendik' => $record->nama,
                                    'username' => $result['username'],
                                    'password' => $result['password'],
                                    'created' => false,
                                ],
                            ]).AdminUserCredentialShareSupport::actionsHtml($token)))
                            ->warning()
                            ->persistent()
                            ->send();
                    }),
                EditAction::make(),
                static::makeDeleteTableAction('guru / tendik')
                    ->visible(fn (GuruTendik $record): bool => static::canDelete($record)),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    BulkAction::make('bulkProvisionAccounts')
                        ->label('Buat/Reset Akun Login')
                        ->icon('heroicon-o-user-plus')
                        ->color('info')
                        ->visible(fn (): bool => auth()->user()?->hasRole('admin'))
                        ->requiresConfirmation()
                        ->modalHeading('Buat/reset akun login guru/tendik terpilih?')
                        ->modalDescription('Data yang belum punya akun akan dibuatkan akun guru. Data yang sudah punya akun akan direset ke password default baru.')
                        ->modalSubmitActionLabel('Proses akun')
                        ->deselectRecordsAfterCompletion()
                        ->action(function (Collection $records): void {
                            $summary = app(GuruTendikAccountProvisioner::class)->provisionForCollection($records);
                            $credentials = collect($summary['credentials'])
                                ->map(fn (array $item): array => [
                                    'name' => $item['guru_tendik'] ?? '-',
                                    'username' => $item['username'] ?? '-',
                                    'password' => $item['password'] ?? '-',
                                    'created' => (bool) ($item['created'] ?? false),
                                ])
                                ->values()
                                ->all();
                            $token = AdminUserCredentialShareSupport::store($credentials, [
                                'generated_by' => auth()->user()?->name,
                            ]);

                            Notification::make()
                                ->title('Provisioning akun selesai.')
                                ->body(new HtmlString(
                                    "{$summary['created']} akun dibuat, {$summary['reset']} akun direset, {$summary['skipped']} data dilewati.".
                                    '<div class="mt-2">'.GuruTendikAccountProvisioner::credentialsAsCopyableHtml($summary['credentials']).'</div>'.
                                    AdminUserCredentialShareSupport::actionsHtml($token)
                                ))
                                ->success()
                                ->persistent()
                                ->send();
                        }),
                    BulkAction::make('bulkAssignDivisionAccess')
                        ->label('Tambah Akses Divisi')
                        ->icon('heroicon-o-briefcase')
                        ->color('warning')
                        ->visible(fn (): bool => auth()->user()?->hasRole('admin'))
                        ->schema([
                            Forms\Components\CheckboxList::make('template_keys')
                                ->label('Template Divisi')
                                ->options(AdminRoleTemplateSupport::options())
                                ->columns(['default' => 1, 'md' => 2])
                                ->helperText('Akses akan ditambahkan ke semua akun guru yang sudah terhubung pada data terpilih.'),
                        ])
                        ->requiresConfirmation()
                        ->modalHeading('Tambahkan akses divisi ke guru terpilih')
                        ->modalDescription('Gunakan bulk action ini untuk memberi akses Sarpras, BK, Humas, dan divisi lain ke beberapa guru sekaligus.')
                        ->modalSubmitActionLabel('Tambahkan akses')
                        ->deselectRecordsAfterCompletion()
                        ->action(function (Collection $records, array $data): void {
                            $templateKeys = UserResource::normalizeDivisionTemplateKeys($data['template_keys'] ?? []);

                            if ($templateKeys === []) {
                                Notification::make()
                                    ->title('Pilih minimal satu divisi.')
                                    ->warning()
                                    ->send();

                                return;
                            }

                            $updated = 0;
                            $skipped = 0;
                            $records->loadMissing('userAccount.roles');

                            $records->each(function (GuruTendik $record) use ($templateKeys, &$updated, &$skipped): void {
                                if (! $record->userAccount) {
                                    $skipped++;

                                    return;
                                }

                                UserResource::applyDivisionTemplatesToUser($record->userAccount, $templateKeys, [
                                    'source' => 'guru_bulk_action',
                                    'note' => 'Perubahan akses massal dari tabel Guru/Tendik.',
                                ]);
                                $updated++;
                            });

                            Notification::make()
                                ->title('Akses divisi massal selesai.')
                                ->body("{$updated} akun diperbarui, {$skipped} data dilewati karena belum punya akun login.")
                                ->success()
                                ->send();
                        }),
                    BulkAction::make('bulkRevokeDivisionAccess')
                        ->label('Cabut Akses Divisi')
                        ->icon('heroicon-o-no-symbol')
                        ->color('danger')
                        ->visible(fn (): bool => auth()->user()?->hasRole('admin'))
                        ->schema([
                            Forms\Components\CheckboxList::make('template_keys')
                                ->label('Cabut akses divisi')
                                ->options(AdminRoleTemplateSupport::options())
                                ->columns(['default' => 1, 'md' => 2])
                                ->helperText('Akses akan dicabut dari semua akun guru yang sudah terhubung pada data terpilih.'),
                        ])
                        ->requiresConfirmation()
                        ->modalHeading('Cabut akses divisi dari guru terpilih')
                        ->modalDescription('Gunakan dengan hati-hati. Data tanpa akun login akan dilewati otomatis.')
                        ->modalSubmitActionLabel('Cabut akses')
                        ->deselectRecordsAfterCompletion()
                        ->action(function (Collection $records, array $data): void {
                            $templateKeys = UserResource::normalizeDivisionTemplateKeys($data['template_keys'] ?? []);

                            if ($templateKeys === []) {
                                Notification::make()
                                    ->title('Pilih minimal satu divisi.')
                                    ->warning()
                                    ->send();

                                return;
                            }

                            $updated = 0;
                            $skipped = 0;
                            $records->loadMissing('userAccount.roles');

                            $records->each(function (GuruTendik $record) use ($templateKeys, &$updated, &$skipped): void {
                                if (! $record->userAccount) {
                                    $skipped++;

                                    return;
                                }

                                UserResource::removeDivisionTemplatesFromUser($record->userAccount, $templateKeys, [
                                    'source' => 'guru_bulk_action',
                                    'note' => 'Pencabutan akses massal dari tabel Guru/Tendik.',
                                ]);
                                $updated++;
                            });

                            Notification::make()
                                ->title('Pencabutan akses divisi massal selesai.')
                                ->body("{$updated} akun diperbarui, {$skipped} data dilewati karena belum punya akun login.")
                                ->success()
                                ->send();
                        }),
                    static::makeDeleteBulkTableAction(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            TugasTambahanRelationManager::class,
            BerkasGurusRelationManager::class,
        ];
    }

    public static function canCreate(): bool
    {
        return static::userCanModule('manage') && ! auth()->user()?->isGuru();
    }

    public static function canDelete($record): bool
    {
        return static::userCanModule('manage') && ! auth()->user()?->isGuru();
    }

    public static function canDeleteAny(): bool
    {
        return static::userCanModule('manage') && ! auth()->user()?->isGuru();
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with([
                'userAccount' => fn ($query) => $query
                    ->select([
                        'id',
                        'username',
                        'guru_tendik_id',
                        'guru_mapel_label',
                        'guru_walas_scope',
                        'module_access_levels',
                        'uses_default_password',
                        'default_password_reset_at',
                    ])
                    ->with('roles:id,name'),
                'tugasTambahan:id,guru_tendik_id,tugas_tambahan,tmt,berkas_guru_id',
            ])
            ->visibleToUser(auth()->user());
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListGuruTendiks::route('/'),
            'create' => Pages\CreateGuruTendik::route('/create'),
            'edit' => Pages\EditGuruTendik::route('/{record}/edit'),
        ];
    }

    public static function tugasTambahanGoogleDriveStatusLabel(mixed $berkasGuruId): string
    {
        $record = static::tugasTambahanGoogleDriveRecord($berkasGuruId);

        return $record
            ? BerkasGuru::googleDriveStatusLabel($record->gdrive_upload_status)
            : 'Akan diproses setelah file SK disimpan.';
    }

    public static function tugasTambahanGoogleDriveProgressLabel(mixed $berkasGuruId): string
    {
        $record = static::tugasTambahanGoogleDriveRecord($berkasGuruId);

        return $record
            ? ((int) ($record->gdrive_upload_progress ?? 0)).'%'
            : '0%';
    }

    public static function tugasTambahanGoogleDriveMessage(mixed $berkasGuruId): string
    {
        $record = static::tugasTambahanGoogleDriveRecord($berkasGuruId);

        return $record?->gdrive_upload_message ?: 'Belum ada proses sinkronisasi. Simpan data guru untuk memulai antrean Google Drive.';
    }

    public static function tugasTambahanGoogleDriveLink(mixed $berkasGuruId): HtmlString
    {
        $record = static::tugasTambahanGoogleDriveRecord($berkasGuruId);
        $url = $record?->resolvedDriveUrl();

        if (blank($url)) {
            return new HtmlString('<span class="text-gray-500">Belum ada link Google Drive.</span>');
        }

        return new HtmlString('<a href="'.e($url).'" target="_blank" rel="noopener noreferrer" class="text-primary-600 underline">Buka Drive</a>');
    }

    public static function tugasTambahanGoogleDriveSummaryLabel(?GuruTendik $record): string
    {
        if (! $record?->exists) {
            return 'Simpan data guru lebih dulu untuk mulai memantau sinkron Google Drive file SK.';
        }

        $cacheKey = (int) $record->getKey();

        if (array_key_exists($cacheKey, static::$tugasTambahanGoogleDriveSummaryLabelCache)) {
            return static::$tugasTambahanGoogleDriveSummaryLabelCache[$cacheKey];
        }

        $berkasRecords = static::tugasTambahanGoogleDriveRecords($record);

        if ($berkasRecords->isEmpty()) {
            return static::$tugasTambahanGoogleDriveSummaryLabelCache[$cacheKey] = 'Belum ada file SK tugas tambahan yang terhubung ke Berkas Guru.';
        }

        $counts = [
            BerkasGuru::GDRIVE_STATUS_QUEUED => 0,
            BerkasGuru::GDRIVE_STATUS_UPLOADING => 0,
            BerkasGuru::GDRIVE_STATUS_SYNCED => 0,
            BerkasGuru::GDRIVE_STATUS_FAILED => 0,
            BerkasGuru::GDRIVE_STATUS_INACTIVE => 0,
            BerkasGuru::GDRIVE_STATUS_CONFIG_INCOMPLETE => 0,
        ];

        foreach ($berkasRecords as $item) {
            $status = $item->gdrive_upload_status;

            if (isset($counts[$status])) {
                $counts[$status]++;
            }
        }

        $parts = [count($berkasRecords).' file SK terhubung'];

        foreach ([
            BerkasGuru::GDRIVE_STATUS_QUEUED => 'menunggu antrean',
            BerkasGuru::GDRIVE_STATUS_UPLOADING => 'sedang diproses',
            BerkasGuru::GDRIVE_STATUS_SYNCED => 'sudah tersinkron',
            BerkasGuru::GDRIVE_STATUS_FAILED => 'bermasalah',
            BerkasGuru::GDRIVE_STATUS_INACTIVE => 'sinkron nonaktif',
            BerkasGuru::GDRIVE_STATUS_CONFIG_INCOMPLETE => 'konfigurasi belum lengkap',
        ] as $status => $label) {
            if ($counts[$status] > 0) {
                $parts[] = $counts[$status].' '.$label;
            }
        }

        return static::$tugasTambahanGoogleDriveSummaryLabelCache[$cacheKey] = implode(', ', $parts).'.';
    }

    public static function notifyTugasTambahanGoogleDriveSummary(GuruTendik $record): void
    {
        $berkasRecords = static::tugasTambahanGoogleDriveRecords($record);
        $body = static::tugasTambahanGoogleDriveSummaryLabel($record);

        $notification = Notification::make()
            ->title($berkasRecords->isEmpty()
                ? 'Data guru tersimpan'
                : 'Status Google Drive SK tugas tambahan diperbarui')
            ->body($body);

        if ($berkasRecords->contains(fn (BerkasGuru $item): bool => $item->gdrive_upload_status === BerkasGuru::GDRIVE_STATUS_FAILED)) {
            $notification->danger()->send();

            return;
        }

        $notification->success()->send();
    }

    public static function flushTugasTambahanGoogleDriveCaches(GuruTendik|int|null $record = null): void
    {
        if ($record === null) {
            static::$tugasTambahanGoogleDriveRecordCache = [];
            static::$tugasTambahanGoogleDriveRecordsCache = [];
            static::$tugasTambahanGoogleDriveSummaryLabelCache = [];

            return;
        }

        $id = $record instanceof GuruTendik ? (int) $record->getKey() : (int) $record;

        if ($id > 0) {
            unset(static::$tugasTambahanGoogleDriveRecordsCache[$id], static::$tugasTambahanGoogleDriveSummaryLabelCache[$id]);
        }
    }

    protected static function tugasTambahanGoogleDriveRecord(mixed $berkasGuruId): ?BerkasGuru
    {
        $id = (int) $berkasGuruId;

        if ($id <= 0) {
            return null;
        }

        if (array_key_exists($id, static::$tugasTambahanGoogleDriveRecordCache)) {
            return static::$tugasTambahanGoogleDriveRecordCache[$id];
        }

        return static::$tugasTambahanGoogleDriveRecordCache[$id] = BerkasGuru::query()
            ->select([
                'id',
                'gdrive_upload_status',
                'gdrive_upload_progress',
                'gdrive_upload_message',
                'gdrive_folder_url',
                'gdrive_file_url',
            ])
            ->find($id);
    }

    /**
     * @return array<int, string>
     */
    protected static function suggestedAdminAccessTemplates(GuruTendik $record): array
    {
        return AdminRoleTemplateSupport::suggestedTemplatesForGuruTendik($record);
    }

    /**
     * @return array<string, array<int, string>>
     */
    protected static function suggestedAdminAccessTemplateReasons(GuruTendik $record): array
    {
        return AdminRoleTemplateSupport::suggestedTemplateReasonsForGuruTendik($record);
    }

    /**
     * @return array<string, string>
     */
    protected static function guruUserAccessRouteParams(GuruTendik $record): array
    {
        $suggestedTemplates = static::suggestedAdminAccessTemplates($record);

        if ($suggestedTemplates === []) {
            return [];
        }

        return [
            'preset_addons' => implode(',', $suggestedTemplates),
        ];
    }

    /**
     * @return array<string, string>
     */
    protected static function guruUserCreateRouteParams(GuruTendik $record): array
    {
        $pamongTemplate = AdminRoleTemplateSupport::pamongTemplateKeyForGuruTendik($record);

        if ($pamongTemplate !== null) {
            return [
                'preset_template' => $pamongTemplate,
            ];
        }

        return [
            'preset_role' => 'guru',
        ];
    }

    protected static function tugasTambahanGoogleDriveRecords(GuruTendik $record): Collection
    {
        $cacheKey = (int) $record->getKey();

        if ($cacheKey > 0 && array_key_exists($cacheKey, static::$tugasTambahanGoogleDriveRecordsCache)) {
            return static::$tugasTambahanGoogleDriveRecordsCache[$cacheKey];
        }

        $tugasTambahan = $record->relationLoaded('tugasTambahan')
            ? $record->tugasTambahan
            : $record->tugasTambahan()->get(['berkas_guru_id']);

        $berkasIds = $tugasTambahan
            ->pluck('berkas_guru_id')
            ->filter(fn (mixed $value): bool => (int) $value > 0)
            ->map(fn (mixed $value): int => (int) $value)
            ->unique()
            ->values()
            ->all();

        if ($berkasIds === []) {
            return $cacheKey > 0
                ? static::$tugasTambahanGoogleDriveRecordsCache[$cacheKey] = collect()
                : collect();
        }

        $records = BerkasGuru::query()
            ->select([
                'id',
                'gdrive_upload_status',
                'gdrive_upload_progress',
                'gdrive_upload_message',
                'gdrive_folder_url',
                'gdrive_file_url',
            ])
            ->whereIn('id', $berkasIds)
            ->get();

        if ($cacheKey > 0) {
            static::$tugasTambahanGoogleDriveRecordsCache[$cacheKey] = $records;
        }

        return $records;
    }
}




