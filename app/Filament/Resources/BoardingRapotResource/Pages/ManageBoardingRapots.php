<?php

namespace App\Filament\Resources\BoardingRapotResource\Pages;

use App\Filament\Resources\BoardingRapotResource;
use App\Models\BoardingRapot;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Filament\Schemas\Components\Section;

class ManageBoardingRapots extends ManageRecords
{
    protected static string $resource = BoardingRapotResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('pengaturanDokumenRapot')
                ->label('Pengaturan Dokumen Rapot')
                ->icon('heroicon-o-cog-6-tooth')
                ->color('gray')
                ->modalHeading('Pengaturan dokumen rapot boarding')
                ->modalSubmitActionLabel('Simpan pengaturan')
                ->modalWidth('4xl')
                ->fillForm(fn (): array => BoardingRapot::documentSettings())
                ->form([
                    Section::make('Kop Surat Rapot')
                        ->description('Pengaturan ini hanya untuk dokumen rapot boarding dan tidak mengubah branding situs utama.')
                        ->columns(['default' => 1, 'md' => 2])
                        ->schema([
                            Forms\Components\FileUpload::make(BoardingRapot::SETTING_LOGO_PATH)
                                ->label('Logo Kiri / Sekolah')
                                ->disk('public')
                                ->directory('boarding-rapot/logo')
                                ->acceptedFileTypes([
                                    'image/png',
                                    'image/jpeg',
                                    'image/webp',
                                    'image/svg+xml',
                                ])
                                ->maxSize(4096)
                                ->downloadable()
                                ->openable()
                                ->helperText('Jika kosong, rapot memakai logo situs utama.'),
                            Forms\Components\FileUpload::make(BoardingRapot::SETTING_RIGHT_LOGO_PATH)
                                ->label('Logo Kanan / Yayasan')
                                ->disk('public')
                                ->directory('boarding-rapot/logo')
                                ->acceptedFileTypes([
                                    'image/png',
                                    'image/jpeg',
                                    'image/webp',
                                    'image/svg+xml',
                                ])
                                ->maxSize(4096)
                                ->downloadable()
                                ->openable()
                                ->helperText('Logo ini tampil di sisi kanan kop surat.'),
                            Forms\Components\TextInput::make(BoardingRapot::SETTING_LOGO_SIZE)
                                ->label('Ukuran Logo Kiri dan Kanan')
                                ->numeric()
                                ->minValue(34)
                                ->maxValue(150)
                                ->step(1)
                                ->suffix('px')
                                ->default(58)
                                ->helperText('Satu ukuran dipakai sama untuk logo kiri dan logo kanan.'),
                            Forms\Components\TextInput::make(BoardingRapot::SETTING_KOP_SITE_NAME)
                                ->label('Nama Kop Surat')
                                ->maxLength(160)
                                ->placeholder('SMA Al Furqon Boarding School'),
                            Forms\Components\TextInput::make(BoardingRapot::SETTING_KOP_SITE_NAME_FONT_SIZE)
                                ->label('Ukuran Teks Nama Kop')
                                ->numeric()
                                ->minValue(12)
                                ->maxValue(50)
                                ->step(0.5)
                                ->suffix('px')
                                ->default(18),
                            Forms\Components\TextInput::make(BoardingRapot::SETTING_KOP_SUBTITLE)
                                ->label('Teks Baris Kedua')
                                ->maxLength(160)
                                ->placeholder('Boarding School'),
                            Forms\Components\TextInput::make(BoardingRapot::SETTING_KOP_SUBTITLE_FONT_SIZE)
                                ->label('Ukuran Teks Baris Kedua')
                                ->numeric()
                                ->minValue(9)
                                ->maxValue(22)
                                ->step(0.5)
                                ->suffix('px')
                                ->default(13),
                            Forms\Components\TextInput::make(BoardingRapot::SETTING_KOP_CONTACT)
                                ->label('Kontak Kop Surat')
                                ->maxLength(220)
                                ->placeholder('Telepon | Email | Website'),
                            Forms\Components\TextInput::make(BoardingRapot::SETTING_KOP_INFO_FONT_SIZE)
                                ->label('Ukuran Teks Alamat dan Kontak')
                                ->numeric()
                                ->minValue(8)
                                ->maxValue(16)
                                ->step(0.5)
                                ->suffix('px')
                                ->default(10.5),
                            Forms\Components\Textarea::make(BoardingRapot::SETTING_KOP_ADDRESS)
                                ->label('Alamat Kop Surat')
                                ->rows(3)
                                ->maxLength(500)
                                ->columnSpanFull(),
                        ]),
                    Section::make('Prolog Rapot')
                        ->schema([
                            Forms\Components\Textarea::make(BoardingRapot::SETTING_PROLOG)
                                ->label('Kalimat Pengantar')
                                ->rows(6)
                                ->required()
                                ->maxLength(1500),
                        ]),
                    Section::make('Pengesahan')
                        ->columns(['default' => 1, 'md' => 3])
                        ->schema([
                            Forms\Components\TextInput::make(BoardingRapot::SETTING_KOTA)
                                ->label('Tempat Cetak')
                                ->maxLength(100)
                                ->default('Bogor'),
                            Forms\Components\TextInput::make(BoardingRapot::SETTING_WALI_LABEL)
                                ->label('Label Tanda Tangan 1')
                                ->maxLength(80)
                                ->default('Kepala Sekolah'),
                            Forms\Components\TextInput::make(BoardingRapot::SETTING_WALI_NAME)
                                ->label('Nama Tanda Tangan 1')
                                ->maxLength(100),
                            Forms\Components\TextInput::make(BoardingRapot::SETTING_KEPALA_LABEL)
                                ->label('Label Tanda Tangan 2')
                                ->maxLength(80)
                                ->default('Kepala Boarding'),
                            Forms\Components\TextInput::make(BoardingRapot::SETTING_KEPALA_NAME)
                                ->label('Nama Tanda Tangan 2')
                                ->maxLength(100),
                            Forms\Components\TextInput::make(BoardingRapot::SETTING_MUDIR_LABEL)
                                ->label('Label Tanda Tangan 3')
                                ->maxLength(80)
                                ->default('Pamong'),
                            Forms\Components\TextInput::make(BoardingRapot::SETTING_MUDIR_NAME)
                                ->label('Nama Tanda Tangan 3')
                                ->helperText('Untuk Pamong, isi di sini. Jika dikosongkan, sistem memakai pamong penanggung jawab rapot.')
                                ->maxLength(100),
                            Forms\Components\TextInput::make(BoardingRapot::SETTING_SIGNATURE_NAME_GAP)
                                ->label('Jarak Nama Tanda Tangan')
                                ->numeric()
                                ->minValue(18)
                                ->maxValue(120)
                                ->step(1)
                                ->suffix('px')
                                ->default(42)
                                ->helperText('Atur jarak vertikal antara label jabatan dan nama tanda tangan.'),
                        ]),
                ])
                ->action(function (array $data): void {
                    BoardingRapot::saveDocumentSettings($data);

                    Notification::make()
                        ->title('Pengaturan dokumen rapot tersimpan.')
                        ->body('Preview dan cetak rapot berikutnya akan memakai kop, prolog, dan pengesahan terbaru.')
                        ->success()
                        ->send();
                }),
            Actions\Action::make('buatRapotDariPencapaian')
                ->label('Buat/Sinkron dari Pencapaian')
                ->icon('heroicon-o-arrow-path')
                ->color('primary')
                ->modalHeading('Buat rapot semua murid dari pencapaian target')
                ->modalSubmitActionLabel('Buat dan sinkronkan')
                ->modalWidth('lg')
                ->form([
                    Forms\Components\TextInput::make('periode_tahun')
                        ->label('Periode Tahun')
                        ->default(fn (): string => BoardingRapot::defaultPeriodeTahun())
                        ->required()
                        ->maxLength(20),
                    Forms\Components\Select::make('semester')
                        ->label('Semester')
                        ->default(fn (): string => BoardingRapot::defaultSemester())
                        ->options([
                            'ganjil' => 'Ganjil',
                            'genap' => 'Genap',
                        ])
                        ->required(),
                    Forms\Components\DatePicker::make('tanggal_rapot')
                        ->label('Tanggal Rapot')
                        ->default(now())
                        ->required(),
                    Forms\Components\Select::make('status_rapot')
                        ->label('Status Rapot Baru')
                        ->default('draft')
                        ->options(BoardingRapot::statusOptions())
                        ->required(),
                    Forms\Components\Toggle::make('overwrite_narratives')
                        ->label('Tulis ulang ringkasan rapot dari data terbaru')
                        ->helperText('Aktifkan jika ringkasan, catatan, dan rekomendasi rapot perlu mengikuti pencapaian terbaru.')
                        ->default(true),
                ])
                ->action(function (array $data): void {
                    $result = BoardingRapot::syncFromFilledPencapaians(
                        user: auth()->user(),
                        periodeTahun: $data['periode_tahun'] ?? null,
                        semester: $data['semester'] ?? null,
                        tanggalRapot: $data['tanggal_rapot'] ?? null,
                        statusRapot: $data['status_rapot'] ?? 'draft',
                        overwriteNarratives: (bool) ($data['overwrite_narratives'] ?? true),
                    );

                    $this->resetTable();

                    Notification::make()
                        ->title('Rapot boarding sudah disinkronkan.')
                        ->body($result['created'].' rapot dibuat, '.$result['updated'].' rapot diperbarui.')
                        ->success()
                        ->send();
                }),
            Actions\Action::make('createRapotManual')
                ->label('New rapot boarding')
                ->icon('heroicon-o-plus')
                ->color('gray')
                ->url(fn (): string => BoardingRapotResource::getUrl('create')),
        ];
    }
}
