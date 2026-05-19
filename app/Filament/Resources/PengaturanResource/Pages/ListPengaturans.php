<?php

namespace App\Filament\Resources\PengaturanResource\Pages;

use App\Contracts\SiteSettingsAccessor;
use App\Filament\Resources\BerkasGuruResource;
use App\Filament\Resources\BerkasSiswaResource;
use App\Filament\Resources\DokumenKomiteResource;
use App\Filament\Resources\PengaturanResource;
use App\Filament\Resources\PrestasiResource;
use App\Models\BerkasGuru;
use App\Models\BerkasSiswa;
use App\Models\KomiteDocument;
use App\Models\Prestasi;
use App\Support\Admin\Dashboard\DashboardCacheSupport;
use App\Support\GoogleDrive\GoogleDriveService;
use App\Support\GoogleDrive\GoogleDriveSettings;
use App\Support\Security\EndpointProtectionPolicy;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use App\Models\Pengaturan;
use App\Support\SiteSettings\SiteSettingKeys;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Closure;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Schema as SchemaFacade;
use Throwable;
use Livewire\Features\SupportFileUploads\WithFileUploads;

/**
 * @property-read Schema $form
 */
class ListPengaturans extends Page implements HasForms
{
    use InteractsWithForms;
    use WithFileUploads;

    protected static string $resource = PengaturanResource::class;

    protected string $view = 'filament.resources.pengaturan-resource.pages.list-pengaturans';

    /**
     * @var array<string, mixed> | null
     */
    public ?array $data = [];

    public mixed $site_name = null;

    public mixed $topbar_badge = null;

    public mixed $topbar_text = null;

    public mixed $footer_primary_text = null;

    public mixed $footer_secondary_text = null;

    public mixed $default_seo_title = null;

    public mixed $default_seo_description = null;

    public mixed $default_og_title = null;

    public mixed $default_og_description = null;

    public mixed $default_og_image = null;

    public mixed $theme_color = null;

    public mixed $pwa_app_name = null;

    public mixed $pwa_short_name = null;

    public mixed $logo_upload = null;

    public mixed $favicon_upload = null;

    public bool $showGoogleDriveMonitoring = true;

    public bool $showGoogleDriveMonitoringDetails = true;

    /**
     * @var array<string, mixed>
     */
    protected array $legacyFieldSnapshot = [];

    /**
     * @var array<string, mixed>
     */
    protected array $googleDriveMonitoringMemo = [];

    /**
     * @var array<string, bool>
     */
    protected array $googleDriveMonitoringAvailability = [];

    /**
     * @var array<string, ?string>|null
     */
    protected ?array $settingsSnapshot = null;

    protected ?GoogleDriveSettings $googleDrivePreviewSnapshot = null;

    protected ?string $googleDrivePreviewSnapshotKey = null;

    public function mount(): void
    {
        $shouldSkipExpensiveWidgets = EndpointProtectionPolicy::shouldSkipExpensiveAdminDashboardWidgets();
        $this->showGoogleDriveMonitoring = ! $shouldSkipExpensiveWidgets;
        $this->showGoogleDriveMonitoringDetails = ! $shouldSkipExpensiveWidgets;
        $this->fillForm();
    }

    public function getTitle(): string|Htmlable
    {
        return 'Pengaturan Situs';
    }

    public function getHeading(): string|Htmlable
    {
        return 'Pengaturan Situs';
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'Kelola branding, metadata, PWA, dan integrasi Google Drive dari satu halaman admin.';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('toggleGoogleDriveMonitoring')
                ->label(fn (): string => $this->showGoogleDriveMonitoring ? 'Sembunyikan Monitor Drive' : 'Muat Monitor Drive')
                ->icon(fn (): string => $this->showGoogleDriveMonitoring ? 'heroicon-o-eye-slash' : 'heroicon-o-bolt')
                ->color('gray')
                ->action(function (): void {
                    $this->showGoogleDriveMonitoring = ! $this->showGoogleDriveMonitoring;

                    if ($this->showGoogleDriveMonitoring) {
                        $this->clearGoogleDriveMonitoringMemo();
                    } else {
                        $this->showGoogleDriveMonitoringDetails = false;
                    }
                }),
            Action::make('toggleGoogleDriveMonitoringDetails')
                ->label(fn (): string => $this->showGoogleDriveMonitoringDetails ? 'Sembunyikan Detail Monitor' : 'Muat Detail Monitor')
                ->icon(fn (): string => $this->showGoogleDriveMonitoringDetails ? 'heroicon-o-eye-slash' : 'heroicon-o-list-bullet')
                ->color('gray')
                ->visible(fn (): bool => $this->showGoogleDriveMonitoring)
                ->action(function (): void {
                    $this->showGoogleDriveMonitoringDetails = ! $this->showGoogleDriveMonitoringDetails;

                    if ($this->showGoogleDriveMonitoringDetails) {
                        $this->clearGoogleDriveMonitoringMemo();
                    }
                }),
        ];
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Grid::make(['default' => 1, 'xl' => 3])
                    ->schema([
                        Section::make('Identitas & Branding')
                            ->description('Atur nama situs, topbar, dan aset utama yang tampil di frontend.')
                            ->columnSpan(['xl' => 2])
                            ->columns(['default' => 1, 'md' => 2])
                            ->schema([
                                Forms\Components\TextInput::make('site_name')
                                    ->label('Nama Situs')
                                    ->required()
                                    ->maxLength(120)
                                    ->live(onBlur: true),
                                Forms\Components\TextInput::make('topbar_badge')
                                    ->label('Badge Topbar')
                                    ->required()
                                    ->maxLength(120),
                                Forms\Components\Textarea::make('topbar_text')
                                    ->label('Teks Topbar')
                                    ->required()
                                    ->rows(3)
                                    ->maxLength(500)
                                    ->columnSpanFull(),
                                Forms\Components\FileUpload::make('logo_path')
                                    ->label('Logo Frontend')
                                    ->disk('public')
                                    ->directory('site-branding/logo')
                                    ->acceptedFileTypes([
                                        'image/png',
                                        'image/jpeg',
                                        'image/webp',
                                        'image/svg+xml',
                                    ])
                                    ->maxSize(4096)
                                    ->downloadable()
                                    ->openable()
                                    ->helperText('Disarankan file ringan dengan latar transparan.'),
                                Forms\Components\FileUpload::make('favicon_path')
                                    ->label('Favicon')
                                    ->disk('public')
                                    ->directory('site-branding/favicon')
                                    ->acceptedFileTypes([
                                        'image/png',
                                        'image/x-icon',
                                        'image/vnd.microsoft.icon',
                                        'image/svg+xml',
                                    ])
                                    ->maxSize(1024)
                                    ->downloadable()
                                    ->openable()
                                    ->helperText('Gunakan ikon persegi agar rapi di browser dan perangkat mobile.'),
                            ]),
                        Section::make('Ringkasan')
                            ->columnSpan(['xl' => 1])
                            ->schema([
                                Forms\Components\Placeholder::make('summary_site_name')
                                    ->label('Nama Situs')
                                    ->content(fn (Get $get): string => (string) ($get('site_name') ?: '-')),
                                Forms\Components\Placeholder::make('summary_seo')
                                    ->label('SEO Default')
                                    ->content(fn (Get $get): string => (string) ($get('default_seo_title') ?: '-')),
                                Forms\Components\Placeholder::make('summary_pwa')
                                    ->label('PWA Pendek')
                                    ->content(fn (Get $get): string => (string) ($get('pwa_short_name') ?: '-')),
                            ]),
                        Section::make('Footer & Metadata')
                            ->description('Fokuskan metadata ke satu sumber. Judul dan deskripsi share otomatis memakai SEO default.')
                            ->columnSpan(['xl' => 2])
                            ->columns(['default' => 1, 'md' => 2])
                            ->schema([
                                Forms\Components\TextInput::make('footer_primary_text')
                                    ->label('Footer Utama')
                                    ->required()
                                    ->maxLength(160),
                                Forms\Components\Textarea::make('footer_secondary_text')
                                    ->label('Footer Sekunder')
                                    ->required()
                                    ->rows(3)
                                    ->maxLength(500),
                                Forms\Components\TextInput::make('default_seo_title')
                                    ->label('SEO Title Default')
                                    ->required()
                                    ->maxLength(160)
                                    ->live(onBlur: true),
                                Forms\Components\Textarea::make('default_seo_description')
                                    ->label('SEO Description Default')
                                    ->required()
                                    ->rows(3)
                                    ->maxLength(320),
                                Forms\Components\TextInput::make('default_og_image')
                                    ->label('OG Image Default')
                                    ->maxLength(2048)
                                    ->helperText('Isi jika butuh gambar share khusus. Judul dan deskripsi share akan mengikuti SEO default.')
                                    ->columnSpanFull(),
                            ]),
                        Section::make('Mengikuti Otomatis')
                            ->columnSpan(['xl' => 1])
                            ->schema([
                                Forms\Components\Placeholder::make('auto_og')
                                    ->label('Open Graph')
                                    ->content('Title dan description mengikuti SEO default agar tidak ada dua sumber teks yang berbeda.'),
                                Forms\Components\Placeholder::make('auto_pwa')
                                    ->label('Nama Aplikasi PWA')
                                    ->content(fn (Get $get): string => 'Mengikuti nama situs: '.((string) ($get('site_name') ?: '-'))),
                            ]),
                        Section::make('PWA & Tema')
                            ->description('Nama aplikasi mengikuti nama situs. Yang perlu dijaga manual hanya warna tema dan nama pendek.')
                            ->columnSpan(['xl' => 2])
                            ->columns(['default' => 1, 'md' => 2])
                            ->schema([
                                Forms\Components\TextInput::make('theme_color')
                                    ->label('Theme Color')
                                    ->required()
                                    ->maxLength(7)
                                    ->placeholder('#16A34A')
                                    ->helperText('Format wajib hex, misalnya #16A34A.')
                                    ->rule('regex:/^#[0-9A-Fa-f]{6}$/'),
                                Forms\Components\TextInput::make('pwa_short_name')
                                    ->label('Nama Pendek PWA')
                                    ->required()
                                    ->maxLength(30)
                                    ->helperText('Dipakai untuk ikon home screen dan manifest.')
                                    ->live(onBlur: true),
                            ]),
                        Section::make('Integrasi Google Drive')
                            ->description('Dokumen komite, berkas siswa, berkas guru, identitas sekolah, dan lampiran prestasi tetap disimpan lokal terlebih dahulu. Jika sinkron otomatis aktif, file masuk antrean background. Jika tidak, admin tetap bisa memakai tombol Upload Sekarang secara manual.')
                            ->columnSpan(['xl' => 2])
                            ->columns(['default' => 1, 'md' => 2])
                            ->schema([
                                Forms\Components\Toggle::make('google_drive_enabled')
                                    ->label('Aktifkan Google Drive')
                                    ->live(),
                                Forms\Components\Toggle::make('google_drive_auto_sync_komite_documents')
                                    ->label('Sinkron otomatis dokumen komite')
                                    ->helperText('Jika aktif, setiap file baru atau perubahan dokumen komite akan masuk antrean upload Google Drive.')
                                    ->default(true)
                                    ->visible(fn (Get $get): bool => (bool) ($get('google_drive_enabled') ?? false)),
                                Forms\Components\Toggle::make('google_drive_auto_sync_berkas_siswa')
                                    ->label('Sinkron otomatis berkas siswa')
                                    ->helperText('Jika aktif, file baru atau perubahan berkas siswa akan masuk antrean upload Google Drive.')
                                    ->default(true)
                                    ->visible(fn (Get $get): bool => (bool) ($get('google_drive_enabled') ?? false)),
                                Forms\Components\Toggle::make('google_drive_auto_sync_berkas_guru')
                                    ->label('Sinkron otomatis berkas guru')
                                    ->helperText('Jika aktif, file baru atau perubahan berkas guru akan masuk antrean upload Google Drive.')
                                    ->default(true)
                                    ->visible(fn (Get $get): bool => (bool) ($get('google_drive_enabled') ?? false)),
                                Forms\Components\Toggle::make('google_drive_auto_sync_prestasi')
                                    ->label('Sinkron otomatis prestasi siswa')
                                    ->helperText('Jika aktif, dokumentasi dan sertifikat prestasi akan masuk antrean upload Google Drive.')
                                    ->default(true)
                                    ->visible(fn (Get $get): bool => (bool) ($get('google_drive_enabled') ?? false)),
                                Forms\Components\Toggle::make('google_drive_auto_sync_identitas_sekolah')
                                    ->label('Sinkron otomatis identitas sekolah')
                                    ->helperText('Jika aktif, file akreditasi pada identitas sekolah akan masuk antrean upload Google Drive.')
                                    ->default(true)
                                    ->visible(fn (Get $get): bool => (bool) ($get('google_drive_enabled') ?? false)),
                                Forms\Components\TextInput::make('google_drive_root_folder_id')
                                    ->label('Folder ID Tujuan')
                                    ->maxLength(255)
                                    ->placeholder('ID atau URL folder Google Drive')
                                    ->helperText('Boleh tempel ID langsung atau URL penuh folder Google Drive. Sistem akan mengambil ID folder otomatis.')
                                    ->visible(fn (Get $get): bool => (bool) ($get('google_drive_enabled') ?? false)),
                                Forms\Components\TextInput::make('google_drive_shared_drive_id')
                                    ->label('Shared Drive ID')
                                    ->maxLength(255)
                                    ->placeholder('ID atau URL Shared Drive')
                                    ->helperText('Boleh tempel ID langsung atau URL penuh Shared Drive. Jika URL root Shared Drive memakai /folders/{id}, sistem tetap akan mengambil ID drive-nya.')
                                    ->visible(fn (Get $get): bool => (bool) ($get('google_drive_enabled') ?? false)),
                                Forms\Components\Textarea::make('google_drive_service_account_json')
                                    ->label('Service Account JSON')
                                    ->rows(10)
                                    ->placeholder("{\n  \"type\": \"service_account\",\n  \"client_email\": \"...\",\n  \"private_key\": \"-----BEGIN PRIVATE KEY-----...\"\n}")
                                    ->helperText('Tempel full JSON credential service account Google Cloud. Kunci ini dipakai server untuk upload otomatis.')
                                    ->columnSpanFull()
                                    ->visible(fn (Get $get): bool => (bool) ($get('google_drive_enabled') ?? false)),
                                Forms\Components\Placeholder::make('google_drive_folder_pattern')
                                    ->label('Struktur Folder Otomatis')
                                    ->content('Google Drive akan menyusun folder: Dokumen Komite = Arsip Tahun -> Jenis Dokumen -> Dokumen-{ID}. Berkas Siswa/Guru = Modul -> Pemilik Dokumen -> Berkas-{ID}. Prestasi = Prestasi Siswa -> Pemilik Dokumen -> Prestasi-{ID}.')
                                    ->columnSpanFull()
                                    ->visible(fn (Get $get): bool => (bool) ($get('google_drive_enabled') ?? false)),
                                Forms\Components\Placeholder::make('google_drive_steps')
                                    ->label('Checklist Setup')
                                    ->content(new HtmlString('<ol style="margin-left: 1rem; list-style: decimal; line-height: 1.6;"><li>Aktifkan Google Drive API di Google Cloud.</li><li>Buat service account dan unduh JSON credential.</li><li>Share folder Drive tujuan ke email service account sebagai Editor.</li><li>Tempel JSON dan Folder ID di sini, lalu uji koneksi.</li></ol>'))
                                    ->columnSpanFull()
                                    ->visible(fn (Get $get): bool => (bool) ($get('google_drive_enabled') ?? false)),
                            ]),
                        Section::make('Status Google Drive')
                            ->columnSpan(['xl' => 1])
                            ->schema([
                                Forms\Components\Placeholder::make('google_drive_ready_status')
                                    ->label('Kesiapan')
                                    ->content(fn (Get $get): string => $this->googleDrivePreviewFromState($this->formStateForGoogleDrivePreview($get))->readinessLabel()),
                                Forms\Components\Placeholder::make('google_drive_account_preview')
                                    ->label('Service Account')
                                    ->content(fn (Get $get): string => $this->googleDrivePreviewFromState($this->formStateForGoogleDrivePreview($get))->serviceAccountEmail() ?: '-'),
                                Forms\Components\Placeholder::make('google_drive_target_preview')
                                    ->label('Folder Tujuan')
                                    ->content(fn (Get $get): string => $this->googleDrivePreviewFromState($this->formStateForGoogleDrivePreview($get))->rootFolderId ?: '-'),
                                Forms\Components\Placeholder::make('google_drive_queue_preview')
                                    ->label('Mode Upload')
                                    ->content('Mode 1: otomatis lewat queue worker untuk dokumen komite, berkas siswa, berkas guru, identitas sekolah, dan prestasi. Mode 2: manual lewat tombol Upload Sekarang di masing-masing resource atau panel monitor.'),
                            ]),
                    ]),
            ]);
    }

    public function save(): void
    {
        $data = array_merge(
            $this->form->getState(),
            $this->legacyFieldStateOverrides(),
        );
        $googleDriveSettings = GoogleDriveSettings::fromFormData($data);

        if ($logoPath = $this->storeLegacyBrandUpload($this->logo_upload, 'site-branding/logo')) {
            $data['logo_path'] = $logoPath;
        }

        if ($faviconPath = $this->storeLegacyBrandUpload($this->favicon_upload, 'site-branding/favicon')) {
            $data['favicon_path'] = $faviconPath;
        }

        $this->upsertSetting(SiteSettingKeys::SITE_NAME, $this->normalizeText($data['site_name'] ?? null));
        $this->upsertSetting(SiteSettingKeys::TOPBAR_BADGE, $this->normalizeText($data['topbar_badge'] ?? null));
        $this->upsertSetting(SiteSettingKeys::TOPBAR_TEXT, $this->normalizeText($data['topbar_text'] ?? null));
        $this->upsertSetting(SiteSettingKeys::FOOTER_PRIMARY_TEXT, $this->normalizeText($data['footer_primary_text'] ?? null));
        $this->upsertSetting(SiteSettingKeys::FOOTER_SECONDARY_TEXT, $this->normalizeText($data['footer_secondary_text'] ?? null));
        $this->upsertSetting(SiteSettingKeys::DEFAULT_SEO_TITLE, $this->normalizeText($data['default_seo_title'] ?? null));
        $this->upsertSetting(SiteSettingKeys::DEFAULT_SEO_DESCRIPTION, $this->normalizeText($data['default_seo_description'] ?? null));
        $this->upsertSetting(SiteSettingKeys::DEFAULT_OG_TITLE, null);
        $this->upsertSetting(SiteSettingKeys::DEFAULT_OG_DESCRIPTION, null);
        $this->upsertSetting(SiteSettingKeys::DEFAULT_OG_IMAGE, $this->normalizeText($data['default_og_image'] ?? null));
        $this->upsertSetting(SiteSettingKeys::THEME_COLOR, strtoupper((string) ($data['theme_color'] ?? '')));
        $this->upsertSetting(SiteSettingKeys::PWA_APP_NAME, $this->normalizeText($data['site_name'] ?? null));
        $this->upsertSetting(SiteSettingKeys::PWA_SHORT_NAME, $this->normalizeText($data['pwa_short_name'] ?? null));
        $this->upsertSetting(SiteSettingKeys::LOGO_PATH, $this->normalizeText($data['logo_path'] ?? null));
        $this->upsertSetting(SiteSettingKeys::FAVICON_PATH, $this->normalizeText($data['favicon_path'] ?? null));
        $this->upsertSetting(SiteSettingKeys::GOOGLE_DRIVE_ENABLED, $this->normalizeBoolean($data['google_drive_enabled'] ?? false));
        $this->upsertSetting(SiteSettingKeys::GOOGLE_DRIVE_AUTO_SYNC_KOMITE_DOCUMENTS, $this->normalizeBoolean($data['google_drive_auto_sync_komite_documents'] ?? false));
        $this->upsertSetting(SiteSettingKeys::GOOGLE_DRIVE_AUTO_SYNC_BERKAS_SISWA, $this->normalizeBoolean($data['google_drive_auto_sync_berkas_siswa'] ?? false));
        $this->upsertSetting(SiteSettingKeys::GOOGLE_DRIVE_AUTO_SYNC_BERKAS_GURU, $this->normalizeBoolean($data['google_drive_auto_sync_berkas_guru'] ?? false));
        $this->upsertSetting(SiteSettingKeys::GOOGLE_DRIVE_AUTO_SYNC_PRESTASI, $this->normalizeBoolean($data['google_drive_auto_sync_prestasi'] ?? false));
        $this->upsertSetting(SiteSettingKeys::GOOGLE_DRIVE_AUTO_SYNC_IDENTITAS_SEKOLAH, $this->normalizeBoolean($data['google_drive_auto_sync_identitas_sekolah'] ?? false));
        $this->upsertSetting(SiteSettingKeys::GOOGLE_DRIVE_ROOT_FOLDER_ID, $googleDriveSettings->rootFolderId);
        $this->upsertSetting(SiteSettingKeys::GOOGLE_DRIVE_SHARED_DRIVE_ID, $googleDriveSettings->sharedDriveId);
        $this->upsertSetting(SiteSettingKeys::GOOGLE_DRIVE_SERVICE_ACCOUNT_JSON, $this->normalizeTextarea($data['google_drive_service_account_json'] ?? null));

        $this->settingsSnapshot = null;
        $this->googleDrivePreviewSnapshot = null;
        $this->googleDrivePreviewSnapshotKey = null;
        $this->clearGoogleDriveMonitoringMemo();
        $this->fillForm();

        Notification::make()
            ->title('Pengaturan situs tersimpan')
            ->success()
            ->send();
    }

    protected function upsertSetting(string $key, ?string $value): void
    {
        Pengaturan::query()->updateOrCreate(
            ['nama_pengaturan' => $key],
            ['nilai_pengaturan' => $value]
        );
    }

    protected function fillForm(): void
    {
        /** @var SiteSettingsAccessor $settings */
        $settings = app(SiteSettingsAccessor::class);
        $storedSettings = $this->settingsSnapshot();

        $state = [
            'site_name' => $settings->siteName(),
            'topbar_badge' => $settings->topbarBadge(),
            'topbar_text' => $settings->topbarText(),
            'footer_primary_text' => $settings->footerPrimaryText(),
            'footer_secondary_text' => $settings->footerSecondaryText(),
            'default_seo_title' => $settings->defaultSeoTitle(),
            'default_seo_description' => $settings->defaultSeoDescription(),
            'default_og_image' => $storedSettings[SiteSettingKeys::DEFAULT_OG_IMAGE] ?? null,
            'theme_color' => strtoupper($settings->themeColor()),
            'pwa_short_name' => $settings->pwaShortName(),
            'logo_path' => $storedSettings[SiteSettingKeys::LOGO_PATH] ?? null,
            'favicon_path' => $storedSettings[SiteSettingKeys::FAVICON_PATH] ?? null,
            'google_drive_enabled' => $this->booleanSetting(SiteSettingKeys::GOOGLE_DRIVE_ENABLED),
            'google_drive_auto_sync_komite_documents' => $this->booleanSetting(SiteSettingKeys::GOOGLE_DRIVE_AUTO_SYNC_KOMITE_DOCUMENTS, true),
            'google_drive_auto_sync_berkas_siswa' => $this->booleanSetting(SiteSettingKeys::GOOGLE_DRIVE_AUTO_SYNC_BERKAS_SISWA, true),
            'google_drive_auto_sync_berkas_guru' => $this->booleanSetting(SiteSettingKeys::GOOGLE_DRIVE_AUTO_SYNC_BERKAS_GURU, true),
            'google_drive_auto_sync_prestasi' => $this->booleanSetting(SiteSettingKeys::GOOGLE_DRIVE_AUTO_SYNC_PRESTASI, true),
            'google_drive_auto_sync_identitas_sekolah' => $this->booleanSetting(SiteSettingKeys::GOOGLE_DRIVE_AUTO_SYNC_IDENTITAS_SEKOLAH, true),
            'google_drive_root_folder_id' => $storedSettings[SiteSettingKeys::GOOGLE_DRIVE_ROOT_FOLDER_ID] ?? null,
            'google_drive_shared_drive_id' => $storedSettings[SiteSettingKeys::GOOGLE_DRIVE_SHARED_DRIVE_ID] ?? null,
            'google_drive_service_account_json' => $storedSettings[SiteSettingKeys::GOOGLE_DRIVE_SERVICE_ACCOUNT_JSON] ?? null,
        ];

        $this->form->fill($state);
        $this->fillLegacyFieldAliases($state);
    }

    public function testGoogleDriveConnection(): void
    {
        try {
            $result = app(GoogleDriveService::class)->testConnection(
                GoogleDriveSettings::fromFormData($this->data ?? [])
            );
        } catch (Throwable $exception) {
            Notification::make()
                ->title('Uji koneksi Google Drive gagal')
                ->body(trim($exception->getMessage()))
                ->danger()
                ->send();

            return;
        }

        Notification::make()
            ->title($result['ok'] ? 'Koneksi Google Drive berhasil' : 'Koneksi Google Drive belum siap')
            ->body(collect([
                $result['message'] ?? null,
                filled($result['email'] ?? null) ? 'Akun: '.$result['email'] : null,
                filled($result['folder_name'] ?? null) ? 'Folder: '.$result['folder_name'] : null,
            ])->filter()->implode("\n"))
            ->{$result['ok'] ? 'success' : 'warning'}()
            ->send();
    }

    public function googleDrivePreview(): GoogleDriveSettings
    {
        return $this->googleDrivePreviewFromState($this->data ?? []);
    }

    public function refreshGoogleDriveMonitoring(): void
    {
        $this->showGoogleDriveMonitoring = true;
        $this->showGoogleDriveMonitoringDetails = true;
        $this->clearGoogleDriveMonitoringMemo();
    }

    public function uploadGoogleDriveNow(int|string $source, int|string|null $recordId = null): void
    {
        $module = $recordId === null ? 'komite_documents' : (string) $source;
        $targetId = $recordId ?? $source;

        if ($module === 'komite_documents') {
            if (! $this->hasGoogleDriveDocumentMonitoring()) {
                return;
            }

            $record = KomiteDocument::query()->find($targetId);

            if (! $record) {
                Notification::make()
                    ->title('Dokumen komite tidak ditemukan')
                    ->warning()
                    ->send();

                return;
            }

            DokumenKomiteResource::uploadGoogleDriveNow($record);

            return;
        }

        if ($module === 'berkas_siswa') {
            if (! $this->hasGoogleDriveStudentFileMonitoring()) {
                return;
            }

            $record = BerkasSiswa::query()->find($targetId);

            if (! $record) {
                Notification::make()
                    ->title('Berkas siswa tidak ditemukan')
                    ->warning()
                    ->send();

                return;
            }

            BerkasSiswaResource::uploadGoogleDriveNow($record);

            return;
        }

        if ($module === 'berkas_guru') {
            if (! $this->hasGoogleDriveTeacherFileMonitoring()) {
                return;
            }

            $record = BerkasGuru::query()->find($targetId);

            if (! $record) {
                Notification::make()
                    ->title('Berkas guru tidak ditemukan')
                    ->warning()
                    ->send();

                return;
            }

            BerkasGuruResource::uploadGoogleDriveNow($record);

            return;
        }

        if ($module === 'prestasi') {
            if (! $this->hasGoogleDrivePrestasiMonitoring()) {
                return;
            }

            $record = Prestasi::query()->find($targetId);

            if (! $record) {
                Notification::make()
                    ->title('Prestasi tidak ditemukan')
                    ->warning()
                    ->send();

                return;
            }

            PrestasiResource::uploadGoogleDriveNow($record);
        }
    }

    public function hasGoogleDriveDocumentMonitoring(): bool
    {
        return $this->googleDriveMonitoringTableAvailable('komite_documents');
    }

    public function hasGoogleDriveStudentFileMonitoring(): bool
    {
        return $this->googleDriveMonitoringTableAvailable('berkas_siswa');
    }

    public function hasGoogleDriveTeacherFileMonitoring(): bool
    {
        return $this->googleDriveMonitoringTableAvailable('berkas_guru');
    }

    public function hasGoogleDrivePrestasiMonitoring(): bool
    {
        return $this->googleDriveMonitoringTableAvailable('prestasis');
    }

    public function hasAnyGoogleDriveMonitoring(): bool
    {
        return $this->hasGoogleDriveDocumentMonitoring()
            || $this->hasGoogleDriveStudentFileMonitoring()
            || $this->hasGoogleDriveTeacherFileMonitoring()
            || $this->hasGoogleDrivePrestasiMonitoring();
    }

    protected function googleDriveMonitoringTableAvailable(string $table): bool
    {
        return $this->googleDriveMonitoringAvailability[$table] ??= SchemaFacade::hasTable($table);
    }

    /**
     * @return array<int, array{label: string, count: int, color: string, description: string}>
     */
    public function googleDriveStatusCards(): array
    {
        if (! $this->shouldRenderGoogleDriveMonitoring()) {
            return [
                ['label' => 'Tersinkron', 'count' => 0, 'color' => 'success', 'description' => 'Belum ada data sinkron file.'],
                ['label' => 'Menunggu antrean', 'count' => 0, 'color' => 'warning', 'description' => 'Belum ada data sinkron file.'],
                ['label' => 'Sedang diproses', 'count' => 0, 'color' => 'info', 'description' => 'Belum ada data sinkron file.'],
                ['label' => 'Bermasalah', 'count' => 0, 'color' => 'danger', 'description' => 'Belum ada data sinkron file.'],
                ['label' => 'Belum terkirim', 'count' => 0, 'color' => 'gray', 'description' => 'Belum ada data sinkron file.'],
            ];
        }

        return [
            [
                'label' => 'Tersinkron',
                'count' => $this->googleDriveStatusCount(KomiteDocument::GDRIVE_STATUS_SYNCED),
                'color' => 'success',
                'description' => 'File yang sudah masuk ke Google Drive.',
            ],
            [
                'label' => 'Menunggu antrean',
                'count' => $this->googleDriveStatusCount(KomiteDocument::GDRIVE_STATUS_QUEUED),
                'color' => 'warning',
                'description' => 'Sudah masuk queue dan menunggu worker.',
            ],
            [
                'label' => 'Sedang diproses',
                'count' => $this->googleDriveStatusCount(KomiteDocument::GDRIVE_STATUS_UPLOADING),
                'color' => 'info',
                'description' => 'Worker sedang mengunggah file ke Google Drive.',
            ],
            [
                'label' => 'Bermasalah',
                'count' => $this->googleDriveStatusCountMany([
                    KomiteDocument::GDRIVE_STATUS_FAILED,
                    KomiteDocument::GDRIVE_STATUS_CONFIG_INCOMPLETE,
                ]),
                'color' => 'danger',
                'description' => 'Perlu dicek karena gagal upload atau konfigurasi belum lengkap.',
            ],
            [
                'label' => 'Belum terkirim',
                'count' => $this->googleDriveUnsentCount(),
                'color' => 'gray',
                'description' => 'Belum pernah berhasil dikirim atau sinkronisasi belum aktif.',
            ],
        ];
    }

    /**
     * @return array<int, array{label: string, count: int, color: string, description: string}>
     */
    public function googleDriveSyncModeCards(): array
    {
        if (! $this->shouldRenderGoogleDriveMonitoring()) {
            return [
                ['label' => 'Baru', 'count' => 0, 'color' => 'success', 'description' => 'Belum ada data sinkron file.'],
                ['label' => 'Diganti', 'count' => 0, 'color' => 'info', 'description' => 'Belum ada data sinkron file.'],
                ['label' => 'Dipulihkan', 'count' => 0, 'color' => 'warning', 'description' => 'Belum ada data sinkron file.'],
            ];
        }

        return [
            [
                'label' => 'Baru',
                'count' => $this->googleDriveSyncModeCount(KomiteDocument::GDRIVE_SYNC_MODE_CREATED),
                'color' => 'success',
                'description' => 'Upload pertama kali ke Google Drive.',
            ],
            [
                'label' => 'Diganti',
                'count' => $this->googleDriveSyncModeCount(KomiteDocument::GDRIVE_SYNC_MODE_REPLACED),
                'color' => 'info',
                'description' => 'File Drive lama diperbarui dari file lokal.',
            ],
            [
                'label' => 'Dipulihkan',
                'count' => $this->googleDriveSyncModeCount(KomiteDocument::GDRIVE_SYNC_MODE_RESTORED),
                'color' => 'warning',
                'description' => 'File Drive hilang lalu dibuat ulang dari website.',
            ],
        ];
    }

    /**
     * @return array<int, array{label: string, count: int, color: string, description: string}>
     */
    public function googleDriveModuleCards(): array
    {
        if (! $this->shouldRenderGoogleDriveMonitoring()) {
            return [];
        }

        return collect($this->googleDriveModuleStats())
            ->map(fn (array $stat): array => [
                'label' => $stat['label'],
                'count' => $stat['total'],
                'color' => $stat['color'],
                'description' => sprintf(
                    '%d tersinkron, %d antre/proses, %d perlu tindakan.',
                    $stat['synced'],
                    $stat['queued'],
                    $stat['attention'],
                ),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{label: string, count: int, color: string, description: string}>
     */
    public function prestasiAssetCards(): array
    {
        if (! $this->shouldRenderGoogleDriveMonitoring() || ! $this->hasGoogleDrivePrestasiMonitoring()) {
            return [];
        }

        return $this->rememberGoogleDriveMonitoring('prestasi-asset-cards', function (): array {
            $records = Prestasi::query()->get(['dokumentasi', 'sertifikat_files', 'gdrive_assets_payload']);
            $syncedCertificates = 0;
            $syncedDocumentation = 0;
            $localCertificates = 0;
            $localDocumentation = 0;

            foreach ($records as $record) {
                $localCertificates += count($record->certificateFiles());
                $localDocumentation += count($record->documentationFiles());

                foreach ((array) ($record->gdrive_assets_payload ?? []) as $asset) {
                    if (! is_array($asset)) {
                        continue;
                    }

                    $kind = (string) ($asset['kind'] ?? '');

                    if ($kind === 'certificate') {
                        $syncedCertificates++;
                    }

                    if ($kind === 'documentation') {
                        $syncedDocumentation++;
                    }
                }
            }

            return [
                [
                    'label' => 'Sertifikat tersinkron',
                    'count' => $syncedCertificates,
                    'color' => 'success',
                    'description' => $localCertificates > 0
                        ? 'Tersinkron '.$syncedCertificates.' dari '.$localCertificates.' file sertifikat.'
                        : 'Belum ada file sertifikat prestasi.',
                ],
                [
                    'label' => 'Dokumentasi tersinkron',
                    'count' => $syncedDocumentation,
                    'color' => 'info',
                    'description' => $localDocumentation > 0
                        ? 'Tersinkron '.$syncedDocumentation.' dari '.$localDocumentation.' file dokumentasi.'
                        : 'Belum ada file dokumentasi prestasi.',
                ],
                [
                    'label' => 'Total asset prestasi',
                    'count' => $localCertificates + $localDocumentation,
                    'color' => 'gray',
                    'description' => 'Total file lokal prestasi yang sedang dipantau sinkronisasinya.',
                ],
            ];
        });
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function googleDriveQueueRows(): array
    {
        if (! $this->shouldRenderGoogleDriveMonitoringDetails()) {
            return [];
        }

        $rows = array_merge(
            $this->googleDriveDocumentRowsForQueue(),
            $this->googleDriveStudentRowsForQueue(),
            $this->googleDriveTeacherRowsForQueue(),
            $this->googleDrivePrestasiRowsForQueue(),
        );

        return $this->sortGoogleDriveRows($rows);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function googleDriveAttentionRows(): array
    {
        if (! $this->shouldRenderGoogleDriveMonitoringDetails()) {
            return [];
        }

        $rows = array_merge(
            $this->googleDriveDocumentRowsForAttention(),
            $this->googleDriveStudentRowsForAttention(),
            $this->googleDriveTeacherRowsForAttention(),
            $this->googleDrivePrestasiRowsForAttention(),
        );

        return $this->sortGoogleDriveRows($rows);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function googleDriveSyncedRows(): array
    {
        if (! $this->shouldRenderGoogleDriveMonitoringDetails()) {
            return [];
        }

        $rows = array_merge(
            $this->googleDriveDocumentRowsForSynced(),
            $this->googleDriveStudentRowsForSynced(),
            $this->googleDriveTeacherRowsForSynced(),
            $this->googleDrivePrestasiRowsForSynced(),
        );

        return $this->sortGoogleDriveRows($rows);
    }

    public function dokumenKomiteIndexUrl(): ?string
    {
        if (! $this->hasGoogleDriveDocumentMonitoring() || ! DokumenKomiteResource::canAccess()) {
            return null;
        }

        return DokumenKomiteResource::getUrl('index');
    }

    public function dokumenKomiteCreateUrl(): ?string
    {
        if (! $this->hasGoogleDriveDocumentMonitoring() || ! DokumenKomiteResource::canAccess()) {
            return null;
        }

        return DokumenKomiteResource::getUrl('create');
    }

    public function berkasSiswaIndexUrl(): ?string
    {
        if (! $this->hasGoogleDriveStudentFileMonitoring() || ! BerkasSiswaResource::canAccess()) {
            return null;
        }

        return BerkasSiswaResource::getUrl('index');
    }

    public function berkasSiswaCreateUrl(): ?string
    {
        if (! $this->hasGoogleDriveStudentFileMonitoring() || ! BerkasSiswaResource::canAccess()) {
            return null;
        }

        return BerkasSiswaResource::getUrl('create');
    }

    public function berkasGuruIndexUrl(): ?string
    {
        if (! $this->hasGoogleDriveTeacherFileMonitoring() || ! BerkasGuruResource::canAccess()) {
            return null;
        }

        return BerkasGuruResource::getUrl('index');
    }

    public function berkasGuruCreateUrl(): ?string
    {
        if (! $this->hasGoogleDriveTeacherFileMonitoring() || ! BerkasGuruResource::canCreate()) {
            return null;
        }

        return BerkasGuruResource::getUrl('create');
    }

    public function prestasiIndexUrl(): ?string
    {
        if (! $this->hasGoogleDrivePrestasiMonitoring() || ! PrestasiResource::canAccess()) {
            return null;
        }

        return PrestasiResource::getUrl('index');
    }

    public function prestasiCreateUrl(): ?string
    {
        if (! $this->hasGoogleDrivePrestasiMonitoring() || ! PrestasiResource::canCreate()) {
            return null;
        }

        return PrestasiResource::getUrl('create');
    }

    /**
     * @return array<int, array{id: string, label: string}>
     */
    public function quickSectionTargets(): array
    {
        return [
            ['id' => 'form.identitas-branding::data::section', 'label' => 'Branding'],
            ['id' => 'form.footer-metadata::data::section', 'label' => 'Metadata'],
            ['id' => 'form.pwa-tema::data::section', 'label' => 'PWA & Tema'],
            ['id' => 'form.integrasi-google-drive::data::section', 'label' => 'Google Drive'],
        ];
    }

    /**
     * @return array<int, array{label: string, color: string, total: int, synced: int, queued: int, attention: int}>
     */
    protected function googleDriveModuleStats(): array
    {
        if (! $this->shouldRenderGoogleDriveMonitoring()) {
            return [];
        }

        return array_values($this->googleDriveAggregateSnapshot());
    }

    protected function googleDriveStatusCount(string $status): int
    {
        return collect($this->googleDriveAggregateSnapshot())
            ->sum(fn (array $stat): int => (int) ($stat['status_counts'][$status] ?? 0));
    }

    /**
     * @param  array<int, string>  $statuses
     */
    protected function googleDriveStatusCountMany(array $statuses): int
    {
        return collect($this->googleDriveAggregateSnapshot())
            ->sum(function (array $stat) use ($statuses): int {
                return collect($statuses)->sum(fn (string $status): int => (int) ($stat['status_counts'][$status] ?? 0));
            });
    }

    protected function googleDriveUnsentCount(): int
    {
        return collect($this->googleDriveAggregateSnapshot())
            ->sum(fn (array $stat): int => (int) ($stat['unsent'] ?? 0));
    }

    protected function googleDriveSyncModeCount(string $mode): int
    {
        return collect($this->googleDriveAggregateSnapshot())
            ->sum(fn (array $stat): int => (int) ($stat['sync_mode_counts'][$mode] ?? 0));
    }

    /**
     * @return array<int, string>
     */
    protected function googleDriveQueueStatuses(): array
    {
        return [
            KomiteDocument::GDRIVE_STATUS_QUEUED,
            KomiteDocument::GDRIVE_STATUS_UPLOADING,
        ];
    }

    /**
     * @return array<int, string>
     */
    protected function googleDriveAttentionStatuses(): array
    {
        return [
            KomiteDocument::GDRIVE_STATUS_INACTIVE,
            KomiteDocument::GDRIVE_STATUS_CONFIG_INCOMPLETE,
            KomiteDocument::GDRIVE_STATUS_FAILED,
            KomiteDocument::GDRIVE_STATUS_SKIPPED,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function googleDriveDocumentRowsForQueue(): array
    {
        if (! $this->hasGoogleDriveDocumentMonitoring()) {
            return [];
        }

        return $this->rememberGoogleDriveMonitoring('document-rows-queue', fn (): array => KomiteDocument::query()
            ->whereIn('gdrive_upload_status', $this->googleDriveQueueStatuses())
            ->orderByDesc('updated_at')
            ->limit(8)
            ->get()
            ->map(fn (KomiteDocument $record): array => $this->mapGoogleDriveDocumentRow($record))
            ->all());
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function googleDriveStudentRowsForQueue(): array
    {
        if (! $this->hasGoogleDriveStudentFileMonitoring()) {
            return [];
        }

        return $this->rememberGoogleDriveMonitoring('student-rows-queue', fn (): array => BerkasSiswa::query()
            ->select([
                'id',
                'siswa_id',
                'jenis_berkas_id',
                'file_name',
                'file_path',
                'uploaded_at',
                'updated_at',
                'gdrive_upload_status',
                'gdrive_upload_progress',
                'gdrive_upload_message',
                'gdrive_last_sync_mode',
                'gdrive_uploaded_at',
                'gdrive_folder_url',
                'gdrive_file_url',
            ])
            ->with(['siswa:id,nama,rombel_saat_ini', 'jenisBerkas:id,nama_berkas'])
            ->whereIn('gdrive_upload_status', $this->googleDriveQueueStatuses())
            ->orderByDesc('updated_at')
            ->limit(8)
            ->get()
            ->map(fn (BerkasSiswa $record): array => $this->mapGoogleDriveBerkasSiswaRow($record))
            ->all());
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function googleDriveTeacherRowsForQueue(): array
    {
        if (! $this->hasGoogleDriveTeacherFileMonitoring()) {
            return [];
        }

        return $this->rememberGoogleDriveMonitoring('teacher-rows-queue', fn (): array => BerkasGuru::query()
            ->select([
                'id',
                'guru_id',
                'jenis_berkas_id',
                'file_path',
                'uploaded_at',
                'gdrive_upload_status',
                'gdrive_upload_progress',
                'gdrive_upload_message',
                'gdrive_last_sync_mode',
                'gdrive_uploaded_at',
                'gdrive_folder_url',
                'gdrive_file_url',
            ])
            ->with(['guru:id,nama', 'jenisBerkas:id,nama_berkas', 'tugasTambahanHistory:id,berkas_guru_id,tugas_tambahan'])
            ->whereIn('gdrive_upload_status', $this->googleDriveQueueStatuses())
            ->orderByDesc('uploaded_at')
            ->limit(8)
            ->get()
            ->map(fn (BerkasGuru $record): array => $this->mapGoogleDriveBerkasGuruRow($record))
            ->all());
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function googleDrivePrestasiRowsForQueue(): array
    {
        if (! $this->hasGoogleDrivePrestasiMonitoring()) {
            return [];
        }

        return $this->rememberGoogleDriveMonitoring('prestasi-rows-queue', fn (): array => Prestasi::query()
            ->with(['siswa:id,nama,rombel_saat_ini'])
            ->whereIn('gdrive_upload_status', $this->googleDriveQueueStatuses())
            ->orderByDesc('updated_at')
            ->limit(8)
            ->get()
            ->map(fn (Prestasi $record): array => $this->mapGoogleDrivePrestasiRow($record))
            ->all());
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function googleDriveDocumentRowsForAttention(): array
    {
        if (! $this->hasGoogleDriveDocumentMonitoring()) {
            return [];
        }

        return $this->rememberGoogleDriveMonitoring('document-rows-attention', fn (): array => KomiteDocument::query()
            ->where(function ($builder): void {
                $builder
                    ->whereNull('gdrive_upload_status')
                    ->orWhereIn('gdrive_upload_status', $this->googleDriveAttentionStatuses());
            })
            ->orderByDesc('updated_at')
            ->limit(8)
            ->get()
            ->map(fn (KomiteDocument $record): array => $this->mapGoogleDriveDocumentRow($record))
            ->all());
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function googleDriveStudentRowsForAttention(): array
    {
        if (! $this->hasGoogleDriveStudentFileMonitoring()) {
            return [];
        }

        return $this->rememberGoogleDriveMonitoring('student-rows-attention', fn (): array => BerkasSiswa::query()
            ->select([
                'id',
                'siswa_id',
                'jenis_berkas_id',
                'file_name',
                'file_path',
                'uploaded_at',
                'updated_at',
                'gdrive_upload_status',
                'gdrive_upload_progress',
                'gdrive_upload_message',
                'gdrive_last_sync_mode',
                'gdrive_uploaded_at',
                'gdrive_folder_url',
                'gdrive_file_url',
            ])
            ->with(['siswa:id,nama,rombel_saat_ini', 'jenisBerkas:id,nama_berkas'])
            ->where(function ($builder): void {
                $builder
                    ->whereNull('gdrive_upload_status')
                    ->orWhereIn('gdrive_upload_status', $this->googleDriveAttentionStatuses());
            })
            ->orderByDesc('updated_at')
            ->limit(8)
            ->get()
            ->map(fn (BerkasSiswa $record): array => $this->mapGoogleDriveBerkasSiswaRow($record))
            ->all());
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function googleDriveTeacherRowsForAttention(): array
    {
        if (! $this->hasGoogleDriveTeacherFileMonitoring()) {
            return [];
        }

        return $this->rememberGoogleDriveMonitoring('teacher-rows-attention', fn (): array => BerkasGuru::query()
            ->select([
                'id',
                'guru_id',
                'jenis_berkas_id',
                'file_path',
                'uploaded_at',
                'gdrive_upload_status',
                'gdrive_upload_progress',
                'gdrive_upload_message',
                'gdrive_last_sync_mode',
                'gdrive_uploaded_at',
                'gdrive_folder_url',
                'gdrive_file_url',
            ])
            ->with(['guru:id,nama', 'jenisBerkas:id,nama_berkas', 'tugasTambahanHistory:id,berkas_guru_id,tugas_tambahan'])
            ->where(function ($builder): void {
                $builder
                    ->whereNull('gdrive_upload_status')
                    ->orWhereIn('gdrive_upload_status', $this->googleDriveAttentionStatuses());
            })
            ->orderByDesc('uploaded_at')
            ->limit(8)
            ->get()
            ->map(fn (BerkasGuru $record): array => $this->mapGoogleDriveBerkasGuruRow($record))
            ->all());
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function googleDrivePrestasiRowsForAttention(): array
    {
        if (! $this->hasGoogleDrivePrestasiMonitoring()) {
            return [];
        }

        return $this->rememberGoogleDriveMonitoring('prestasi-rows-attention', fn (): array => Prestasi::query()
            ->with(['siswa:id,nama,rombel_saat_ini'])
            ->where(function ($builder): void {
                $builder
                    ->whereNull('gdrive_upload_status')
                    ->orWhereIn('gdrive_upload_status', $this->googleDriveAttentionStatuses());
            })
            ->orderByDesc('updated_at')
            ->limit(8)
            ->get()
            ->map(fn (Prestasi $record): array => $this->mapGoogleDrivePrestasiRow($record))
            ->all());
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function googleDriveDocumentRowsForSynced(): array
    {
        if (! $this->hasGoogleDriveDocumentMonitoring()) {
            return [];
        }

        return $this->rememberGoogleDriveMonitoring('document-rows-synced', fn (): array => KomiteDocument::query()
            ->where('gdrive_upload_status', KomiteDocument::GDRIVE_STATUS_SYNCED)
            ->orderByDesc('gdrive_uploaded_at')
            ->orderByDesc('updated_at')
            ->limit(8)
            ->get()
            ->map(fn (KomiteDocument $record): array => $this->mapGoogleDriveDocumentRow($record, true))
            ->all());
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function googleDriveStudentRowsForSynced(): array
    {
        if (! $this->hasGoogleDriveStudentFileMonitoring()) {
            return [];
        }

        return $this->rememberGoogleDriveMonitoring('student-rows-synced', fn (): array => BerkasSiswa::query()
            ->select([
                'id',
                'siswa_id',
                'jenis_berkas_id',
                'file_name',
                'file_path',
                'uploaded_at',
                'updated_at',
                'gdrive_upload_status',
                'gdrive_upload_progress',
                'gdrive_upload_message',
                'gdrive_last_sync_mode',
                'gdrive_uploaded_at',
                'gdrive_folder_url',
                'gdrive_file_url',
            ])
            ->with(['siswa:id,nama,rombel_saat_ini', 'jenisBerkas:id,nama_berkas'])
            ->where('gdrive_upload_status', BerkasSiswa::GDRIVE_STATUS_SYNCED)
            ->orderByDesc('gdrive_uploaded_at')
            ->orderByDesc('updated_at')
            ->limit(8)
            ->get()
            ->map(fn (BerkasSiswa $record): array => $this->mapGoogleDriveBerkasSiswaRow($record, true))
            ->all());
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function googleDriveTeacherRowsForSynced(): array
    {
        if (! $this->hasGoogleDriveTeacherFileMonitoring()) {
            return [];
        }

        return $this->rememberGoogleDriveMonitoring('teacher-rows-synced', fn (): array => BerkasGuru::query()
            ->select([
                'id',
                'guru_id',
                'jenis_berkas_id',
                'file_path',
                'uploaded_at',
                'gdrive_upload_status',
                'gdrive_upload_progress',
                'gdrive_upload_message',
                'gdrive_last_sync_mode',
                'gdrive_uploaded_at',
                'gdrive_folder_url',
                'gdrive_file_url',
            ])
            ->with(['guru:id,nama', 'jenisBerkas:id,nama_berkas', 'tugasTambahanHistory:id,berkas_guru_id,tugas_tambahan'])
            ->where('gdrive_upload_status', BerkasGuru::GDRIVE_STATUS_SYNCED)
            ->orderByDesc('gdrive_uploaded_at')
            ->orderByDesc('uploaded_at')
            ->limit(8)
            ->get()
            ->map(fn (BerkasGuru $record): array => $this->mapGoogleDriveBerkasGuruRow($record, true))
            ->all());
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function googleDrivePrestasiRowsForSynced(): array
    {
        if (! $this->hasGoogleDrivePrestasiMonitoring()) {
            return [];
        }

        return $this->rememberGoogleDriveMonitoring('prestasi-rows-synced', fn (): array => Prestasi::query()
            ->with(['siswa:id,nama,rombel_saat_ini'])
            ->where('gdrive_upload_status', Prestasi::GDRIVE_STATUS_SYNCED)
            ->orderByDesc('gdrive_uploaded_at')
            ->orderByDesc('updated_at')
            ->limit(8)
            ->get()
            ->map(fn (Prestasi $record): array => $this->mapGoogleDrivePrestasiRow($record, true))
            ->all());
    }

    /**
     * @return array<string, array{label: string, color: string, total: int, synced: int, queued: int, attention: int, unsent: int, status_counts: array<string, int>, sync_mode_counts: array<string, int>}>
     */
    protected function googleDriveAggregateSnapshot(): array
    {
        if (! $this->shouldRenderGoogleDriveMonitoring()) {
            return [];
        }

        return $this->rememberGoogleDriveMonitoring('aggregate-snapshot', function (): array {
            $modules = [];

            if ($this->hasGoogleDriveDocumentMonitoring()) {
                $modules['komite_documents'] = $this->googleDriveAggregateForModel(KomiteDocument::class, 'Dokumen Komite', 'warning');
            }

            if ($this->hasGoogleDriveStudentFileMonitoring()) {
                $modules['berkas_siswa'] = $this->googleDriveAggregateForModel(BerkasSiswa::class, 'Berkas Siswa', 'primary');
            }

            if ($this->hasGoogleDriveTeacherFileMonitoring()) {
                $modules['berkas_guru'] = $this->googleDriveAggregateForModel(BerkasGuru::class, 'Berkas Guru', 'info');
            }

            if ($this->hasGoogleDrivePrestasiMonitoring()) {
                $modules['prestasi'] = $this->googleDriveAggregateForModel(Prestasi::class, 'Prestasi', 'success');
            }

            return $modules;
        });
    }

    /**
     * @param  class-string<\Illuminate\Database\Eloquent\Model>  $modelClass
     * @return array{label: string, color: string, total: int, synced: int, queued: int, attention: int, unsent: int, status_counts: array<string, int>, sync_mode_counts: array<string, int>}
     */
    protected function googleDriveAggregateForModel(string $modelClass, string $label, string $color): array
    {
        $total = $modelClass::query()->count();
        $statusCounts = $modelClass::query()
            ->selectRaw('gdrive_upload_status, COUNT(*) as aggregate')
            ->whereNotNull('gdrive_upload_status')
            ->groupBy('gdrive_upload_status')
            ->pluck('aggregate', 'gdrive_upload_status')
            ->map(fn (mixed $count): int => (int) $count)
            ->all();
        $syncModeCounts = $modelClass::query()
            ->selectRaw('gdrive_last_sync_mode, COUNT(*) as aggregate')
            ->whereNotNull('gdrive_last_sync_mode')
            ->groupBy('gdrive_last_sync_mode')
            ->pluck('aggregate', 'gdrive_last_sync_mode')
            ->map(fn (mixed $count): int => (int) $count)
            ->all();
        $nullStatusCount = max(0, $total - array_sum($statusCounts));
        $queued = collect($this->googleDriveQueueStatuses())
            ->sum(fn (string $status): int => (int) ($statusCounts[$status] ?? 0));
        $attention = $nullStatusCount + collect($this->googleDriveAttentionStatuses())
            ->sum(fn (string $status): int => (int) ($statusCounts[$status] ?? 0));
        $unsent = $nullStatusCount
            + (int) ($statusCounts[KomiteDocument::GDRIVE_STATUS_INACTIVE] ?? 0)
            + (int) ($statusCounts[KomiteDocument::GDRIVE_STATUS_SKIPPED] ?? 0);

        return [
            'label' => $label,
            'color' => $color,
            'total' => $total,
            'synced' => (int) ($statusCounts[KomiteDocument::GDRIVE_STATUS_SYNCED] ?? 0),
            'queued' => $queued,
            'attention' => $attention,
            'unsent' => $unsent,
            'status_counts' => $statusCounts,
            'sync_mode_counts' => $syncModeCounts,
        ];
    }

    protected function rememberGoogleDriveMonitoring(string $key, Closure $callback): mixed
    {
        if (array_key_exists($key, $this->googleDriveMonitoringMemo)) {
            return $this->googleDriveMonitoringMemo[$key];
        }

        return $this->googleDriveMonitoringMemo[$key] = DashboardCacheSupport::remember(
            'google_drive_monitor',
            $key,
            $callback,
        );
    }

    protected function clearGoogleDriveMonitoringMemo(): void
    {
        $this->googleDriveMonitoringMemo = [];
    }

    protected function shouldRenderGoogleDriveMonitoring(): bool
    {
        return $this->showGoogleDriveMonitoring && $this->hasAnyGoogleDriveMonitoring();
    }

    protected function shouldRenderGoogleDriveMonitoringDetails(): bool
    {
        return $this->showGoogleDriveMonitoringDetails && $this->shouldRenderGoogleDriveMonitoring();
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    protected function sortGoogleDriveRows(array $rows): array
    {
        return collect($rows)
            ->sort(function (array $left, array $right): int {
                if (($left['sort_priority'] ?? 99) !== ($right['sort_priority'] ?? 99)) {
                    return ($left['sort_priority'] ?? 99) <=> ($right['sort_priority'] ?? 99);
                }

                return ($right['sort_at'] ?? 0) <=> ($left['sort_at'] ?? 0);
            })
            ->take(8)
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    protected function formStateForGoogleDrivePreview(Get $get): array
    {
        return [
            'google_drive_enabled' => $get('google_drive_enabled'),
            'google_drive_auto_sync_komite_documents' => $get('google_drive_auto_sync_komite_documents'),
            'google_drive_auto_sync_berkas_siswa' => $get('google_drive_auto_sync_berkas_siswa'),
            'google_drive_auto_sync_berkas_guru' => $get('google_drive_auto_sync_berkas_guru'),
            'google_drive_auto_sync_prestasi' => $get('google_drive_auto_sync_prestasi'),
            'google_drive_auto_sync_identitas_sekolah' => $get('google_drive_auto_sync_identitas_sekolah'),
            'google_drive_root_folder_id' => $get('google_drive_root_folder_id'),
            'google_drive_shared_drive_id' => $get('google_drive_shared_drive_id'),
            'google_drive_service_account_json' => $get('google_drive_service_account_json'),
        ];
    }

    /**
     * @param  array<string, mixed>  $state
     */
    protected function googleDrivePreviewFromState(array $state): GoogleDriveSettings
    {
        $previewState = Arr::only($state, [
            'google_drive_enabled',
            'google_drive_auto_sync_komite_documents',
            'google_drive_auto_sync_berkas_siswa',
            'google_drive_auto_sync_berkas_guru',
            'google_drive_auto_sync_prestasi',
            'google_drive_auto_sync_identitas_sekolah',
            'google_drive_root_folder_id',
            'google_drive_shared_drive_id',
            'google_drive_service_account_json',
        ]);

        $snapshotKey = md5(json_encode($previewState));

        if ($this->googleDrivePreviewSnapshot !== null && $this->googleDrivePreviewSnapshotKey === $snapshotKey) {
            return $this->googleDrivePreviewSnapshot;
        }

        $this->googleDrivePreviewSnapshotKey = $snapshotKey;

        return $this->googleDrivePreviewSnapshot = GoogleDriveSettings::fromFormData($previewState);
    }

    protected function normalizeText(mixed $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        return trim((string) $value);
    }

    protected function normalizeTextarea(mixed $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        return trim((string) $value);
    }

    protected function normalizeBoolean(mixed $value): string
    {
        return filter_var($value, FILTER_VALIDATE_BOOL) ? '1' : '0';
    }

    protected function booleanSetting(string $key, bool $default = false): bool
    {
        $value = $this->settingsSnapshot()[$key] ?? null;

        if ($value === null) {
            return $default;
        }

        return filter_var($value, FILTER_VALIDATE_BOOL);
    }

    /**
     * @param  array<string, mixed>  $state
     */
    protected function fillLegacyFieldAliases(array $state): void
    {
        $this->site_name = $state['site_name'] ?? null;
        $this->topbar_badge = $state['topbar_badge'] ?? null;
        $this->topbar_text = $state['topbar_text'] ?? null;
        $this->footer_primary_text = $state['footer_primary_text'] ?? null;
        $this->footer_secondary_text = $state['footer_secondary_text'] ?? null;
        $this->default_seo_title = $state['default_seo_title'] ?? null;
        $this->default_seo_description = $state['default_seo_description'] ?? null;
        $this->default_og_title = $this->settingsSnapshot()[SiteSettingKeys::DEFAULT_OG_TITLE] ?? null;
        $this->default_og_description = $this->settingsSnapshot()[SiteSettingKeys::DEFAULT_OG_DESCRIPTION] ?? null;
        $this->default_og_image = $state['default_og_image'] ?? null;
        $this->theme_color = $state['theme_color'] ?? null;
        $this->pwa_app_name = $state['site_name'] ?? null;
        $this->pwa_short_name = $state['pwa_short_name'] ?? null;
        $this->logo_upload = null;
        $this->favicon_upload = null;
        $this->legacyFieldSnapshot = $this->normalizedLegacyAliasState();
    }

    /**
     * @return array<string, mixed>
     */
    protected function legacyFieldStateOverrides(): array
    {
        $current = $this->normalizedLegacyAliasState();
        $overrides = [];

        foreach ($current as $key => $value) {
            if (($this->legacyFieldSnapshot[$key] ?? null) === $value) {
                continue;
            }

            $overrides[$key] = $value;
        }

        return $overrides;
    }

    protected function storeLegacyBrandUpload(mixed $upload, string $directory): ?string
    {
        if (! $upload instanceof UploadedFile) {
            return null;
        }

        return Storage::disk('public')->putFile($directory, $upload);
    }

    /**
     * @return array<string, mixed>
     */
    protected function normalizedLegacyAliasState(): array
    {
        return array_filter([
            'site_name' => $this->normalizeText($this->site_name),
            'topbar_badge' => $this->normalizeText($this->topbar_badge),
            'topbar_text' => $this->normalizeText($this->topbar_text),
            'footer_primary_text' => $this->normalizeText($this->footer_primary_text),
            'footer_secondary_text' => $this->normalizeText($this->footer_secondary_text),
            'default_seo_title' => $this->normalizeText($this->default_seo_title),
            'default_seo_description' => $this->normalizeText($this->default_seo_description),
            'default_og_image' => $this->normalizeText($this->default_og_image),
            'theme_color' => $this->normalizeText($this->theme_color),
            'pwa_short_name' => $this->normalizeText($this->pwa_short_name),
        ], fn (mixed $value): bool => $value !== null);
    }

    /**
     * @return array<string, ?string>
     */
    protected function settingsSnapshot(): array
    {
        if ($this->settingsSnapshot !== null) {
            return $this->settingsSnapshot;
        }

        return $this->settingsSnapshot = Pengaturan::values([
            SiteSettingKeys::DEFAULT_OG_TITLE,
            SiteSettingKeys::DEFAULT_OG_DESCRIPTION,
            SiteSettingKeys::DEFAULT_OG_IMAGE,
            SiteSettingKeys::LOGO_PATH,
            SiteSettingKeys::FAVICON_PATH,
            SiteSettingKeys::GOOGLE_DRIVE_ENABLED,
            SiteSettingKeys::GOOGLE_DRIVE_AUTO_SYNC_KOMITE_DOCUMENTS,
            SiteSettingKeys::GOOGLE_DRIVE_AUTO_SYNC_BERKAS_SISWA,
            SiteSettingKeys::GOOGLE_DRIVE_AUTO_SYNC_BERKAS_GURU,
            SiteSettingKeys::GOOGLE_DRIVE_AUTO_SYNC_PRESTASI,
            SiteSettingKeys::GOOGLE_DRIVE_AUTO_SYNC_IDENTITAS_SEKOLAH,
            SiteSettingKeys::GOOGLE_DRIVE_ROOT_FOLDER_ID,
            SiteSettingKeys::GOOGLE_DRIVE_SHARED_DRIVE_ID,
            SiteSettingKeys::GOOGLE_DRIVE_SERVICE_ACCOUNT_JSON,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function mapGoogleDriveDocumentRow(KomiteDocument $record, bool $preferUploadedAt = false): array
    {
        $timestamp = $preferUploadedAt
            ? ($record->gdrive_uploaded_at ?? $record->updated_at)
            : $record->updated_at;
        $status = $record->gdrive_upload_status;

        return [
            'id' => $record->getKey(),
            'source' => 'komite_documents',
            'module_label' => 'Dokumen Komite',
            'module_color' => 'warning',
            'judul' => $record->judul ?: 'Dokumen tanpa judul',
            'jenis' => KomiteDocument::typeLabel($record->jenis_dokumen),
            'context' => 'Arsip '.$record->arsip_tahun,
            'status_label' => KomiteDocument::googleDriveStatusLabel($status),
            'status_color' => KomiteDocument::googleDriveStatusColor($status),
            'sync_mode_label' => KomiteDocument::googleDriveSyncModeLabel($record->gdrive_last_sync_mode),
            'sync_mode_color' => KomiteDocument::googleDriveSyncModeColor($record->gdrive_last_sync_mode),
            'progress' => (int) ($record->gdrive_upload_progress ?? 0),
            'message' => $record->gdrive_upload_message ?: 'Belum ada pesan sinkronisasi.',
            'timestamp_label' => $preferUploadedAt ? 'Tersinkron' : 'Update',
            'timestamp' => $timestamp?->format('d/m/Y H:i') ?: '-',
            'drive_url' => $record->resolvedDriveUrl(),
            'has_uploadable_files' => $record->hasUploadableFiles(),
            'sort_priority' => $status === KomiteDocument::GDRIVE_STATUS_UPLOADING
                ? 0
                : ($status === KomiteDocument::GDRIVE_STATUS_QUEUED
                    ? 1
                    : ($status === KomiteDocument::GDRIVE_STATUS_FAILED ? 2 : 3)),
            'sort_at' => $timestamp?->getTimestamp() ?? 0,
            'admin_url' => DokumenKomiteResource::canAccess()
                ? DokumenKomiteResource::getUrl('edit', ['record' => $record])
                : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function mapGoogleDriveBerkasSiswaRow(BerkasSiswa $record, bool $preferUploadedAt = false): array
    {
        $timestamp = $preferUploadedAt
            ? ($record->gdrive_uploaded_at ?? $record->updated_at ?? $record->uploaded_at)
            : ($record->updated_at ?? $record->uploaded_at);
        $status = $record->gdrive_upload_status;
        $fileTitle = trim((string) ($record->displayFileName() ?: ($record->file_name ?: 'Berkas siswa #'.$record->getKey())));
        $studentName = $record->siswa?->nama ?: 'Siswa tidak diketahui';
        $rombel = trim((string) ($record->siswa?->rombel_saat_ini ?? ''));

        return [
            'id' => $record->getKey(),
            'source' => 'berkas_siswa',
            'module_label' => 'Berkas Siswa',
            'module_color' => 'primary',
            'judul' => $fileTitle,
            'jenis' => $record->jenisBerkas?->nama_berkas ?: 'Jenis berkas',
            'context' => trim($studentName.($rombel !== '' ? ' • '.$rombel : '')),
            'status_label' => BerkasSiswa::googleDriveStatusLabel($status),
            'status_color' => BerkasSiswa::googleDriveStatusColor($status),
            'sync_mode_label' => BerkasSiswa::googleDriveSyncModeLabel($record->gdrive_last_sync_mode),
            'sync_mode_color' => BerkasSiswa::googleDriveSyncModeColor($record->gdrive_last_sync_mode),
            'progress' => (int) ($record->gdrive_upload_progress ?? 0),
            'message' => $record->gdrive_upload_message ?: 'Belum ada pesan sinkronisasi.',
            'timestamp_label' => $preferUploadedAt ? 'Tersinkron' : 'Update',
            'timestamp' => $timestamp?->format('d/m/Y H:i') ?: '-',
            'drive_url' => $record->resolvedDriveUrl(),
            'has_uploadable_files' => $record->hasUploadableFiles(),
            'sort_priority' => $status === BerkasSiswa::GDRIVE_STATUS_UPLOADING
                ? 0
                : ($status === BerkasSiswa::GDRIVE_STATUS_QUEUED
                    ? 1
                    : ($status === BerkasSiswa::GDRIVE_STATUS_FAILED ? 2 : 3)),
            'sort_at' => $timestamp?->getTimestamp() ?? 0,
            'admin_url' => BerkasSiswaResource::canAccess()
                ? BerkasSiswaResource::getUrl('edit', ['record' => $record])
                : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function mapGoogleDriveBerkasGuruRow(BerkasGuru $record, bool $preferUploadedAt = false): array
    {
        $timestamp = $preferUploadedAt
            ? ($record->gdrive_uploaded_at ?? $record->uploaded_at)
            : ($record->uploaded_at ?? $record->gdrive_uploaded_at);
        $status = $record->gdrive_upload_status;
        $fileTitle = trim((string) ($record->displayFileName() ?: 'Berkas guru #'.$record->getKey()));
        $teacherName = $record->guru?->nama ?: 'Guru / tendik tidak diketahui';
        $sourceLabel = $record->isManagedTugasTambahanSk() ? 'History tugas tambahan' : 'Berkas guru';

        return [
            'id' => $record->getKey(),
            'source' => 'berkas_guru',
            'module_label' => 'Berkas Guru',
            'module_color' => 'info',
            'judul' => $fileTitle,
            'jenis' => $record->jenisBerkas?->nama_berkas ?: 'Jenis berkas',
            'context' => trim($teacherName.' • '.$sourceLabel),
            'status_label' => BerkasGuru::googleDriveStatusLabel($status),
            'status_color' => BerkasGuru::googleDriveStatusColor($status),
            'sync_mode_label' => BerkasGuru::googleDriveSyncModeLabel($record->gdrive_last_sync_mode),
            'sync_mode_color' => BerkasGuru::googleDriveSyncModeColor($record->gdrive_last_sync_mode),
            'progress' => (int) ($record->gdrive_upload_progress ?? 0),
            'message' => $record->gdrive_upload_message ?: 'Belum ada pesan sinkronisasi.',
            'timestamp_label' => $preferUploadedAt ? 'Tersinkron' : 'Update',
            'timestamp' => $timestamp?->format('d/m/Y H:i') ?: '-',
            'drive_url' => $record->resolvedDriveUrl(),
            'has_uploadable_files' => $record->hasUploadableFiles(),
            'sort_priority' => $status === BerkasGuru::GDRIVE_STATUS_UPLOADING
                ? 0
                : ($status === BerkasGuru::GDRIVE_STATUS_QUEUED
                    ? 1
                    : ($status === BerkasGuru::GDRIVE_STATUS_FAILED ? 2 : 3)),
            'sort_at' => $timestamp?->getTimestamp() ?? 0,
            'admin_url' => (BerkasGuruResource::canAccess() && ! $record->isManagedTugasTambahanSk())
                ? BerkasGuruResource::getUrl('edit', ['record' => $record])
                : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function mapGoogleDrivePrestasiRow(Prestasi $record, bool $preferUploadedAt = false): array
    {
        $timestamp = $preferUploadedAt
            ? ($record->gdrive_uploaded_at ?? $record->updated_at ?? $record->created_at)
            : ($record->updated_at ?? $record->created_at);
        $status = $record->gdrive_upload_status;
        $studentName = $record->siswa?->nama ?: 'Murid tidak diketahui';
        $rombel = trim((string) ($record->siswa?->rombel_saat_ini ?? ''));
        $documentationCount = count($record->documentationFiles());
        $certificateCount = count($record->certificateFiles());
        $syncedAssetPayload = collect((array) ($record->gdrive_assets_payload ?? []))
            ->filter(fn (mixed $asset): bool => is_array($asset));
        $syncedCertificates = $syncedAssetPayload->where('kind', 'certificate')->count();
        $syncedDocumentation = $syncedAssetPayload->where('kind', 'documentation')->count();

        return [
            'id' => $record->getKey(),
            'source' => 'prestasi',
            'module_label' => 'Prestasi',
            'module_color' => 'success',
            'judul' => trim((string) ($record->nama_lomba ?: 'Prestasi #'.$record->getKey())),
            'jenis' => trim((string) ($record->juara ?: 'Prestasi siswa')),
            'context' => trim($studentName.($rombel !== '' ? ' • '.$rombel : '').' • Sertifikat '.$certificateCount.' • Dokumentasi '.$documentationCount),
            'status_label' => Prestasi::googleDriveStatusLabel($status),
            'status_color' => Prestasi::googleDriveStatusColor($status),
            'sync_mode_label' => Prestasi::googleDriveSyncModeLabel($record->gdrive_last_sync_mode),
            'sync_mode_color' => Prestasi::googleDriveSyncModeColor($record->gdrive_last_sync_mode),
            'progress' => (int) ($record->gdrive_upload_progress ?? 0),
            'message' => $record->gdrive_upload_message ?: 'Belum ada pesan sinkronisasi.',
            'timestamp_label' => $preferUploadedAt ? 'Tersinkron' : 'Update',
            'timestamp' => $timestamp?->format('d/m/Y H:i') ?: '-',
            'drive_url' => $record->resolvedDriveUrl(),
            'has_uploadable_files' => $record->hasUploadableFiles(),
            'asset_badges' => [
                $this->makePrestasiAssetBadge('Sertifikat', $syncedCertificates, $certificateCount),
                $this->makePrestasiAssetBadge('Dokumentasi', $syncedDocumentation, $documentationCount),
            ],
            'sort_priority' => $status === Prestasi::GDRIVE_STATUS_UPLOADING
                ? 0
                : ($status === Prestasi::GDRIVE_STATUS_QUEUED
                    ? 1
                    : ($status === Prestasi::GDRIVE_STATUS_FAILED ? 2 : 3)),
            'sort_at' => $timestamp?->getTimestamp() ?? 0,
            'admin_url' => PrestasiResource::canAccess()
                ? PrestasiResource::getUrl('edit', ['record' => $record])
                : null,
        ];
    }

    /**
     * @return array{label: string, color: string}
     */
    protected function makePrestasiAssetBadge(string $label, int $synced, int $total): array
    {
        $color = 'gray';

        if ($total > 0 && $synced >= $total) {
            $color = 'success';
        } elseif ($synced > 0) {
            $color = 'warning';
        }

        return [
            'label' => $label.' '.$synced.'/'.$total,
            'color' => $color,
        ];
    }
}










