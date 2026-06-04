<?php

namespace App\Filament\Resources\BoardingRapotResource\Pages;

use App\Filament\Resources\BoardingRapotResource;
use App\Models\BoardingRapot;
use App\Models\DataSiswa;
use App\Support\DataSiswa\DataSiswaSupport;
use App\Support\Boarding\BoardingRapotBulkPrintSupport;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Support\HtmlString;

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
            Actions\Action::make('printSemuaSiapCetak')
                ->label('Print Semua Siap Cetak')
                ->icon('heroicon-o-printer')
                ->color('success')
                ->requiresConfirmation(fn (): bool => ! $this->shouldShowBulkPrintForm() && $this->shouldConfirmDefaultBulkPrint())
                ->modalHeading(fn (): string => $this->shouldShowBulkPrintForm()
                    ? 'Print rapot siap cetak per kelas'
                    : ($this->shouldConfirmDefaultBulkPrint()
                    ? 'Konfirmasi print rapot belum lengkap'
                    : 'Print semua rapot siap cetak'))
                ->modalDescription(fn (): string => $this->defaultBulkPrintModalDescription())
                ->modalSubmitActionLabel(fn (): string => ! $this->shouldShowBulkPrintForm() && $this->shouldConfirmDefaultBulkPrint()
                    ? 'Ya, cetak yang siap'
                    : 'Buka Mode Cetak')
                ->modalWidth('lg')
                ->form(fn (): array => $this->bulkPrintActionForm())
                ->action(function (array $data) {
                    $filters = $this->bulkPrintFilters($data);
                    $summary = $this->bulkPrintSummary(
                        periodeTahun: $filters['periode_tahun'],
                        semester: $filters['semester'],
                        rombel: $filters['rombel'],
                        jenisKelamin: $filters['jenis_kelamin'],
                    );

                    if ((int) $summary['ready_rapots'] === 0) {
                        Notification::make()
                            ->title('Belum ada rapot siap cetak.')
                            ->body('Ubah minimal satu rapot pada scope ini menjadi Siap Cetak sebelum print gabungan.')
                            ->warning()
                            ->send();

                        return null;
                    }

                    return redirect()->route('admin.boarding-rapots.print-all', [
                        'periode_tahun' => $filters['periode_tahun'],
                        'semester' => $filters['semester'],
                        'rombel' => $filters['rombel'],
                        'jenis_kelamin' => $filters['jenis_kelamin'],
                    ]);
                }),
            Actions\Action::make('createRapotManual')
                ->label('New rapot boarding')
                ->icon('heroicon-o-plus')
                ->color('gray')
                ->url(fn (): string => BoardingRapotResource::getUrl('create')),
        ];
    }

    /**
     * @return array<int, mixed>
     */
    protected function bulkPrintActionForm(): array
    {
        $user = auth()->user();

        if (! $user) {
            return [];
        }

        if ($user->hasFullAdminAccess()) {
            return [
                Forms\Components\TextInput::make('periode_tahun')
                    ->label('Periode Tahun')
                    ->default(fn (): string => BoardingRapot::defaultPeriodeTahun())
                    ->live()
                    ->required()
                    ->maxLength(20),
                Forms\Components\Select::make('semester')
                    ->label('Semester')
                    ->default(fn (): string => BoardingRapot::defaultSemester())
                    ->options([
                        'ganjil' => 'Ganjil',
                        'genap' => 'Genap',
                    ])
                    ->live()
                    ->required(),
                Forms\Components\Select::make('rombel')
                    ->label('Kelas / Rombel')
                    ->placeholder('Semua kelas dalam scope')
                    ->options(fn (): array => DataSiswaSupport::rombelOptions(auth()->user()))
                    ->searchable()
                    ->native(false)
                    ->live(),
                Forms\Components\Select::make('jenis_kelamin')
                    ->label('Jenis Kelamin')
                    ->default('all')
                    ->options(['all' => 'Semua Jenis Kelamin'] + DataSiswa::jkOptions())
                    ->native(false)
                    ->live(),
                Forms\Components\Placeholder::make('print_summary')
                    ->label('Kesiapan print gabungan')
                    ->content(fn (Get $get): HtmlString => $this->bulkPrintSummaryHtml(
                        periodeTahun: (string) ($get('periode_tahun') ?: BoardingRapot::defaultPeriodeTahun()),
                        semester: (string) ($get('semester') ?: BoardingRapot::defaultSemester()),
                        rombel: $get('rombel') ?: null,
                        jenisKelamin: (string) ($get('jenis_kelamin') ?: 'all'),
                    )),
            ];
        }

        if (! $this->shouldShowBulkPrintForm()) {
            return [];
        }

        $jenisKelamin = BoardingRapotBulkPrintSupport::effectiveJenisKelamin($user, 'all');
        $jenisKelaminLabel = BoardingRapotBulkPrintSupport::jenisKelaminLabel($jenisKelamin) ?: 'semua jenis kelamin';

        return [
            Forms\Components\TextInput::make('periode_tahun')
                ->label('Periode Tahun')
                ->default(fn (): string => BoardingRapot::defaultPeriodeTahun())
                ->live()
                ->required()
                ->maxLength(20),
            Forms\Components\Select::make('semester')
                ->label('Semester')
                ->default(fn (): string => BoardingRapot::defaultSemester())
                ->options([
                    'ganjil' => 'Ganjil',
                    'genap' => 'Genap',
                ])
                ->live()
                ->required(),
            Forms\Components\Select::make('rombel')
                ->label('Kelas / Rombel')
                ->placeholder('Pilih kelas dalam scope pamong')
                ->default(fn (): ?string => $this->defaultBulkPrintRombel())
                ->options(fn (): array => DataSiswaSupport::rombelOptions(auth()->user()))
                ->searchable()
                ->native(false)
                ->required()
                ->live(),
            Forms\Components\Placeholder::make('jenis_kelamin_scope')
                ->label('Jenis Kelamin')
                ->content(new HtmlString('<span class="text-sm">'.e(ucfirst($jenisKelaminLabel)).' dari role pamong.</span>')),
            Forms\Components\Placeholder::make('print_summary')
                ->label('Kesiapan print gabungan')
                ->content(fn (Get $get): HtmlString => $this->bulkPrintSummaryHtml(
                    periodeTahun: (string) ($get('periode_tahun') ?: BoardingRapot::defaultPeriodeTahun()),
                    semester: (string) ($get('semester') ?: BoardingRapot::defaultSemester()),
                    rombel: $get('rombel') ?: null,
                    jenisKelamin: (string) ($get('jenis_kelamin') ?: 'all'),
                )),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{periode_tahun:string,semester:string,rombel:?string,jenis_kelamin:string}
     */
    protected function bulkPrintFilters(array $data): array
    {
        $user = auth()->user();
        $rombel = filled($data['rombel'] ?? null) ? (string) $data['rombel'] : null;
        $jenisKelamin = (string) ($data['jenis_kelamin'] ?? 'all');

        if ($user && ! $user->hasFullAdminAccess()) {
            $rombel ??= $this->defaultBulkPrintRombel();
            $jenisKelamin = BoardingRapotBulkPrintSupport::effectiveJenisKelamin($user, $jenisKelamin);
        }

        return [
            'periode_tahun' => (string) ($data['periode_tahun'] ?? BoardingRapot::defaultPeriodeTahun()),
            'semester' => (string) ($data['semester'] ?? BoardingRapot::defaultSemester()),
            'rombel' => $rombel,
            'jenis_kelamin' => $jenisKelamin,
        ];
    }

    protected function shouldShowBulkPrintForm(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        if ($user->hasFullAdminAccess()) {
            return true;
        }

        return count(DataSiswaSupport::rombelOptions($user)) > 1;
    }

    protected function defaultBulkPrintRombel(): ?string
    {
        $user = auth()->user();

        if (! $user) {
            return null;
        }

        return BoardingRapotBulkPrintSupport::defaultRombel($user);
    }

    protected function shouldConfirmDefaultBulkPrint(): bool
    {
        $user = auth()->user();

        if (! $user || $user->hasFullAdminAccess()) {
            return false;
        }

        $summary = $this->bulkPrintSummary();

        return ! (bool) $summary['is_complete'];
    }

    protected function defaultBulkPrintModalDescription(): string
    {
        $user = auth()->user();

        if (! $user || $user->hasFullAdminAccess()) {
            return 'Pilih periode, semester, kelas, dan jenis kelamin untuk membuka PDF gabungan rapot siap cetak.';
        }

        if ($this->shouldShowBulkPrintForm()) {
            $jenisKelamin = BoardingRapotBulkPrintSupport::effectiveJenisKelamin($user, 'all');
            $jenisKelaminLabel = BoardingRapotBulkPrintSupport::jenisKelaminLabel($jenisKelamin) ?: 'semua jenis kelamin';

            return 'Pilih kelas dalam scope pamong. Jenis kelamin otomatis mengikuti role pamong: '.ucfirst($jenisKelaminLabel).'.';
        }

        $summary = $this->bulkPrintSummary();

        if ((bool) $summary['is_complete']) {
            return 'Semua murid dalam scope pamong sudah siap cetak. PDF gabungan akan langsung dibuka.';
        }

        return BoardingRapotBulkPrintSupport::incompleteConfirmationText($summary);
    }

    protected function bulkPrintSummaryHtml(
        ?string $periodeTahun = null,
        ?string $semester = null,
        ?string $rombel = null,
        ?string $jenisKelamin = 'all',
    ): HtmlString {
        $summary = $this->bulkPrintSummary($periodeTahun, $semester, $rombel, $jenisKelamin);
        $ready = number_format((int) $summary['ready_rapots'], 0, ',', '.');
        $total = number_format((int) $summary['total_students'], 0, ',', '.');

        if ((bool) $summary['is_complete']) {
            return new HtmlString('<div class="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-800">Semua '.$total.' murid pada filter ini sudah siap cetak. PDF gabungan akan berisi '.$ready.' rapot.</div>');
        }

        $message = BoardingRapotBulkPrintSupport::incompleteConfirmationText($summary);

        return new HtmlString('<div class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800">'.e($message).'</div>');
    }

    /**
     * @return array<string, mixed>
     */
    protected function bulkPrintSummary(
        ?string $periodeTahun = null,
        ?string $semester = null,
        ?string $rombel = null,
        ?string $jenisKelamin = 'all',
    ): array {
        $user = auth()->user();

        if (! $user) {
            return [
                'ready_rapots' => 0,
                'total_students' => 0,
                'is_complete' => false,
                'scope_label' => 'scope ini',
                'not_ready_rapots' => 0,
                'missing_rapots' => 0,
            ];
        }

        if (! $user->hasFullAdminAccess()) {
            $rombel = filled($rombel) ? $rombel : $this->defaultBulkPrintRombel();
            $jenisKelamin = BoardingRapotBulkPrintSupport::effectiveJenisKelamin($user, $jenisKelamin);
        }

        return BoardingRapotBulkPrintSupport::summary(
            user: $user,
            periodeTahun: $periodeTahun,
            semester: $semester,
            rombel: $rombel,
            jenisKelamin: $jenisKelamin,
        );
    }
}
