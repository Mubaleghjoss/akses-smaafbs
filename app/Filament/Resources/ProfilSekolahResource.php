<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasModulePermissions;
use App\Filament\Resources\ProfilSekolahResource\Pages;
use App\Models\ProfilSekolah;
use App\Support\GoogleDrive\GoogleDriveService;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Schema as SchemaFacade;

class ProfilSekolahResource extends Resource
{
    use HasModulePermissions;

    protected static ?string $model = ProfilSekolah::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-building-office';

    protected static string|\UnitEnum|null $navigationGroup = 'Manajemen Sekolah';

    protected static ?int $navigationSort = 10;

    protected static ?string $navigationLabel = 'Identitas Sekolah';

    protected static ?string $modelLabel = 'Identitas Sekolah';

    protected static ?string $pluralModelLabel = 'Identitas Sekolah';

    protected static ?string $permissionPrefix = 'profil_sekolah';

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public static function canAccess(): bool
    {
        return SchemaFacade::hasTable('profil_sekolahs') && parent::canAccess();
    }

    public static function canCreate(): bool
    {
        if (! SchemaFacade::hasTable('profil_sekolahs')) {
            return false;
        }

        return parent::canCreate() && ProfilSekolah::query()->count() === 0;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Identitas Resmi Sekolah')
                    ->description('Isi data inti identitas sekolah yang akan ditampilkan di frontend.')
                    ->columns(['default' => 1, 'md' => 2])
                    ->schema([
                        Forms\Components\TextInput::make('title')
                            ->label('Judul Bagian')
                            ->required()
                            ->maxLength(160)
                            ->default('Identitas Sekolah')
                            ->columnSpanFull(),
                        Forms\Components\TextInput::make('nama_sekolah')
                            ->label('Nama Sekolah')
                            ->required()
                            ->maxLength(180),
                        Forms\Components\TextInput::make('provinsi')
                            ->label('Provinsi')
                            ->maxLength(120),
                        Forms\Components\TextInput::make('desa_kelurahan')
                            ->label('Desa / Kelurahan')
                            ->maxLength(120),
                        Forms\Components\TextInput::make('kecamatan')
                            ->label('Kecamatan')
                            ->maxLength(120),
                        Forms\Components\Textarea::make('alamat')
                            ->label('Alamat')
                            ->rows(3)
                            ->columnSpanFull(),
                        Forms\Components\TextInput::make('kode_pos')
                            ->label('Kode Pos')
                            ->maxLength(20),
                        Forms\Components\TextInput::make('kontak_telepon')
                            ->label('Telepon')
                            ->maxLength(60),
                        Forms\Components\TextInput::make('kontak_email')
                            ->label('Email')
                            ->email()
                            ->maxLength(120),
                        Forms\Components\TextInput::make('website_url')
                            ->label('Website')
                            ->url()
                            ->maxLength(2048),
                        Forms\Components\TextInput::make('status_sekolah')
                            ->label('Status Sekolah')
                            ->maxLength(120),
                        Forms\Components\TextInput::make('kelompok_sekolah')
                            ->label('Kelompok Sekolah')
                            ->maxLength(120),
                        Forms\Components\TextInput::make('terakreditasi')
                            ->label('Terakreditasi')
                            ->maxLength(120),
                        Forms\Components\DatePicker::make('tanggal_identitas')
                            ->label('Tanggal Akreditasi Turun')
                            ->native(true)
                            ->displayFormat('d/m/Y'),
                        Forms\Components\DatePicker::make('tanggal_berdiri')
                            ->label('Tanggal Berdiri Sekolah')
                            ->native(true)
                            ->displayFormat('d/m/Y'),
                        Forms\Components\TextInput::make('kbm')
                            ->label('KBM')
                            ->maxLength(120),
                        Forms\Components\TextInput::make('bangunan_sekolah')
                            ->label('Bangunan Sekolah')
                            ->maxLength(160),
                        Forms\Components\TextInput::make('luas_bangunan')
                            ->label('Luas Bangunan')
                            ->maxLength(120),
                        Forms\Components\TextInput::make('organisasi_penyelenggara')
                            ->label('Organisasi Penyelenggara')
                            ->maxLength(180),
                    ]),
                Section::make('Dokumen Akreditasi')
                    ->description('Upload dokumen akreditasi sekolah. File tetap tersimpan lokal di project dan bisa disinkronkan ke Google Drive.')
                    ->columns(['default' => 1, 'md' => 2])
                    ->schema([
                        Forms\Components\FileUpload::make('file_akreditasi_path')
                            ->label('File Akreditasi')
                            ->disk('public')
                            ->directory('identitas-sekolah/akreditasi')
                            ->acceptedFileTypes([
                                'application/pdf',
                                'application/msword',
                                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                                'image/jpeg',
                                'image/png',
                            ])
                            ->downloadable()
                            ->openable()
                            ->columnSpanFull(),
                        Forms\Components\Placeholder::make('google_drive_status')
                            ->label('Status Upload')
                            ->content(fn (?ProfilSekolah $record): string => $record
                                ? ProfilSekolah::googleDriveStatusLabel($record->gdrive_upload_status)
                                : 'Akan muncul setelah data disimpan.'),
                        Forms\Components\Placeholder::make('google_drive_progress')
                            ->label('Progress')
                            ->content(fn (?ProfilSekolah $record): string => $record
                                ? ((int) ($record->gdrive_upload_progress ?? 0)).'%'
                                : '0%'),
                        Forms\Components\Placeholder::make('google_drive_sync_mode')
                            ->label('Hasil Sinkron Terakhir')
                            ->content(fn (?ProfilSekolah $record): string => $record
                                ? ProfilSekolah::googleDriveSyncModeLabel($record->gdrive_last_sync_mode)
                                : '-'),
                        Forms\Components\Placeholder::make('google_drive_link')
                            ->label('Link Google Drive')
                            ->content(fn (?ProfilSekolah $record): string => $record?->resolvedDriveUrl() ?: '-'),
                        Forms\Components\Placeholder::make('google_drive_message')
                            ->label('Pesan Terakhir')
                            ->content(fn (?ProfilSekolah $record): string => $record?->gdrive_upload_message ?: 'Belum ada proses sinkronisasi.')
                            ->columnSpanFull(),
                    ]),
                Section::make('Identitas Tambahan Manual')
                    ->description('Tambahkan pasangan label dan isi lain bila format resmi sekolah membutuhkan item tambahan.')
                    ->schema([
                        Forms\Components\Repeater::make('identitas_tambahan')
                            ->label('Daftar Identitas Manual')
                            ->columns(['default' => 1, 'md' => 3])
                            ->collapsed()
                            ->reorderableWithButtons()
                            ->addActionLabel('Tambah Identitas Manual')
                            ->schema([
                                Forms\Components\TextInput::make('label')
                                    ->label('Nama Field')
                                    ->required()
                                    ->maxLength(120),
                                Forms\Components\TextInput::make('value')
                                    ->label('Isi')
                                    ->required()
                                    ->maxLength(255),
                                Forms\Components\TextInput::make('url')
                                    ->label('Link Opsional')
                                    ->url()
                                    ->maxLength(2048),
                            ]),
                    ]),
                Section::make('Kontak, Maps, dan Sosial Media')
                    ->description('Lengkapi tautan kontak publik, peta lokasi, dan media sosial sekolah.')
                    ->columns(['default' => 1, 'md' => 2])
                    ->schema([
                        Forms\Components\TextInput::make('maps_url')
                            ->label('Link Maps')
                            ->url()
                            ->maxLength(2048)
                            ->columnSpanFull(),
                        Forms\Components\TextInput::make('youtube_url')
                            ->label('YouTube')
                            ->url()
                            ->maxLength(2048),
                        Forms\Components\TextInput::make('instagram_url')
                            ->label('Instagram')
                            ->url()
                            ->maxLength(2048),
                        Forms\Components\TextInput::make('facebook_url')
                            ->label('Facebook')
                            ->url()
                            ->maxLength(2048),
                        Forms\Components\TextInput::make('tiktok_url')
                            ->label('TikTok')
                            ->url()
                            ->maxLength(2048),
                    ]),
                Section::make('Fasilitas')
                    ->description('Tampilkan foto fasilitas dan keterangan singkat tiap tempat.')
                    ->schema([
                        Forms\Components\Repeater::make('fasilitas')
                            ->label('Daftar Fasilitas')
                            ->columns(['default' => 1, 'md' => 2])
                            ->collapsed()
                            ->reorderableWithButtons()
                            ->addActionLabel('Tambah Fasilitas')
                            ->schema([
                                Forms\Components\TextInput::make('nama')
                                    ->label('Nama Tempat')
                                    ->maxLength(120),
                                Forms\Components\FileUpload::make('foto')
                                    ->label('Foto')
                                    ->disk('public')
                                    ->directory('identitas-sekolah/fasilitas')
                                    ->image()
                                    ->imageEditor()
                                    ->downloadable()
                                    ->openable(),
                                Forms\Components\Textarea::make('keterangan')
                                    ->label('Keterangan')
                                    ->rows(3)
                                    ->columnSpanFull(),
                            ]),
                    ]),
                Section::make('Jadwal KBM')
                    ->description('Susun jadwal kegiatan belajar dalam tabel waktu dan nama kegiatan.')
                    ->schema([
                        Forms\Components\Repeater::make('jadwal_kbm')
                            ->label('Daftar Jadwal')
                            ->columns(['default' => 1, 'md' => 2])
                            ->collapsed()
                            ->reorderableWithButtons()
                            ->addActionLabel('Tambah Jadwal')
                            ->schema([
                                Forms\Components\TextInput::make('waktu')
                                    ->label('Waktu')
                                    ->maxLength(60)
                                    ->placeholder('07.00 - 07.30'),
                                Forms\Components\TextInput::make('kegiatan')
                                    ->label('Nama Kegiatan')
                                    ->maxLength(160)
                                    ->placeholder('Apel pagi'),
                            ]),
                    ]),
                Section::make('Menu Makan')
                    ->description('Tampilkan menu makan per hari dalam tabel yang mudah dibaca di frontend.')
                    ->schema([
                        Forms\Components\Repeater::make('menu_makan')
                            ->label('Daftar Menu')
                            ->columns(['default' => 1, 'md' => 2])
                            ->collapsed()
                            ->reorderableWithButtons()
                            ->addActionLabel('Tambah Menu')
                            ->schema([
                                Forms\Components\TextInput::make('hari')
                                    ->label('Hari')
                                    ->maxLength(60)
                                    ->placeholder('Senin'),
                                Forms\Components\TextInput::make('menu')
                                    ->label('Menu')
                                    ->maxLength(160)
                                    ->placeholder('Nasi, ayam, sayur bening'),
                            ]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('nama_sekolah')
                    ->label('Nama Sekolah')
                    ->searchable()
                    ->wrap(),
                Tables\Columns\TextColumn::make('terakreditasi')
                    ->label('Akreditasi')
                    ->badge(),
                Tables\Columns\TextColumn::make('tanggal_identitas')
                    ->label('Akreditasi Turun')
                    ->date('d/m/Y')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('gdrive_upload_status')
                    ->label('Google Drive')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => ProfilSekolah::googleDriveStatusLabel($state))
                    ->color(fn (?string $state): string => ProfilSekolah::googleDriveStatusColor($state))
                    ->description(fn (ProfilSekolah $record): string => ((int) ($record->gdrive_upload_progress ?? 0)).'%'),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Diupdate')
                    ->since()
                    ->sortable(),
            ])
            ->actions([
                Action::make('upload_google_drive_now')
                    ->label('Upload Sekarang')
                    ->icon('heroicon-o-cloud-arrow-up')
                    ->color('primary')
                    ->visible(fn (ProfilSekolah $record): bool => $record->hasUploadableFiles())
                    ->action(function (ProfilSekolah $record): void {
                        static::uploadGoogleDriveNow($record);
                    }),
                Action::make('buka_file')
                    ->label('Buka File')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->color('gray')
                    ->visible(fn (ProfilSekolah $record): bool => filled($record->file_akreditasi_path))
                    ->url(fn (ProfilSekolah $record): ?string => $record->resolvedAccreditationFileUrl())
                    ->openUrlInNewTab(),
                Action::make('buka_drive')
                    ->label('Buka Drive')
                    ->icon('heroicon-o-folder-open')
                    ->color('gray')
                    ->visible(fn (ProfilSekolah $record): bool => filled($record->resolvedDriveUrl()))
                    ->url(fn (ProfilSekolah $record): ?string => $record->resolvedDriveUrl())
                    ->openUrlInNewTab(),
                EditAction::make(),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProfilSekolahs::route('/'),
            'create' => Pages\CreateProfilSekolah::route('/create'),
            'edit' => Pages\EditProfilSekolah::route('/{record}/edit'),
        ];
    }

    public static function queueGoogleDriveSync(ProfilSekolah $record): string
    {
        $status = app(GoogleDriveService::class)->queueProfilSekolahSync($record);

        Notification::make()
            ->title(match ($status) {
                ProfilSekolah::GDRIVE_STATUS_QUEUED => 'File identitas masuk antrean Google Drive',
                ProfilSekolah::GDRIVE_STATUS_CONFIG_INCOMPLETE => 'Konfigurasi Google Drive belum lengkap',
                ProfilSekolah::GDRIVE_STATUS_INACTIVE => 'Sinkronisasi otomatis Google Drive nonaktif',
                ProfilSekolah::GDRIVE_STATUS_SKIPPED => 'Tidak ada file untuk diunggah',
                default => 'Status Google Drive diperbarui',
            })
            ->body($record->fresh()?->gdrive_upload_message)
            ->{ $status === ProfilSekolah::GDRIVE_STATUS_QUEUED ? 'success' : 'warning' }()
            ->send();

        return $status;
    }

    public static function uploadGoogleDriveNow(ProfilSekolah $record): string
    {
        $status = app(GoogleDriveService::class)->uploadProfilSekolahNow($record);

        Notification::make()
            ->title(match ($status) {
                ProfilSekolah::GDRIVE_STATUS_SYNCED => 'Upload / pemulihan Google Drive selesai',
                ProfilSekolah::GDRIVE_STATUS_CONFIG_INCOMPLETE => 'Konfigurasi Google Drive belum lengkap',
                ProfilSekolah::GDRIVE_STATUS_INACTIVE => 'Google Drive nonaktif',
                ProfilSekolah::GDRIVE_STATUS_SKIPPED => 'Tidak ada file untuk diunggah',
                ProfilSekolah::GDRIVE_STATUS_FAILED => 'Upload Google Drive gagal',
                default => 'Status Google Drive diperbarui',
            })
            ->body($record->fresh()?->gdrive_upload_message)
            ->{ $status === ProfilSekolah::GDRIVE_STATUS_SYNCED ? 'success' : ($status === ProfilSekolah::GDRIVE_STATUS_FAILED ? 'danger' : 'warning') }()
            ->send();

        return $status;
    }
}
