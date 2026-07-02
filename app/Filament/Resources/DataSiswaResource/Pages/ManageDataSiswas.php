<?php

namespace App\Filament\Resources\DataSiswaResource\Pages;

use App\Filament\Resources\DataSiswaResource;
use App\Filament\Widgets\DataSiswaGenderByRombelChart;
use App\Filament\Widgets\DataSiswaNonAktifReasonChart;
use App\Filament\Widgets\DataSiswaStatsOverview;
use App\Filament\Widgets\DataSiswaStatusChart;
use App\Filament\Widgets\DataSiswaWorkflowHelp;
use App\Filament\Widgets\PrestasiByRombelChart;
use App\Filament\Widgets\PrestasiStatsOverview;
use App\Models\DataSiswa;
use App\Support\DataSiswa\DataSiswaImportReviewShareSupport;
use App\Support\DataSiswa\DataSiswaProfileWorkbookImporter;
use App\Support\DataSiswa\DataSiswaWorkbookImporter;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Throwable;

class ManageDataSiswas extends ManageRecords
{
    protected static string $resource = DataSiswaResource::class;

    public bool $showSummaryWidgets = false;

    #[Url(as: 'chart_status')]
    public ?string $chartStatus = null;

    #[Url(as: 'chart_jk')]
    public ?string $chartJk = null;

    #[Url(as: 'chart_rombel')]
    public ?string $chartRombel = null;

    #[Url(as: 'chart_kategori_non_aktif')]
    public ?string $chartKategoriNonAktif = null;

    #[Url(as: 'data_tes_status')]
    public ?string $dataTesStatus = null;

    public function mount(): void
    {
        parent::mount();

        $this->applyPageFiltersFromQuery();
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('syncSpmb')
                ->label('Sinkron SPMB')
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->url(DataSiswaResource::getUrl('spmb-sync'))
                ->visible(fn (): bool => DataSiswaResource::canCreate()),
            Actions\Action::make('downloadTemplateDataSiswa')
                ->label('Download Template Data')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->url(route('admin.data-siswa.import-template', absolute: false))
                ->openUrlInNewTab()
                ->visible(fn (): bool => DataSiswaResource::canViewAny()),
            $this->importDataTesSiswaAction(),
            $this->importDataSiswaAction(),
            Actions\Action::make('exportDataSiswa')
                ->label('Export Data Siswa')
                ->icon('heroicon-o-document-arrow-down')
                ->color('success')
                ->url(route('admin.data-siswa.export', absolute: false))
                ->openUrlInNewTab()
                ->visible(fn (): bool => DataSiswaResource::canViewAny()),
            Actions\Action::make('exportProfilSiswa')
                ->label('Export Data Tes Siswa')
                ->icon('heroicon-o-identification')
                ->color('info')
                ->url(route('admin.data-siswa.export-profile', absolute: false))
                ->openUrlInNewTab()
                ->visible(fn (): bool => DataSiswaResource::canViewAny()),
            Actions\CreateAction::make()
                ->label('Tambah Data Siswa')
                ->icon('heroicon-o-plus')
                ->color('primary')
                ->visible(fn (): bool => DataSiswaResource::canCreate()),
            Actions\Action::make('toggleSummaryWidgets')
                ->label(fn (): string => $this->showSummaryWidgets ? 'Collapse Semua Ringkasan' : 'Muat Semua Ringkasan')
                ->icon(fn (): string => $this->showSummaryWidgets ? 'heroicon-o-eye-slash' : 'heroicon-o-bolt')
                ->color('gray')
                ->action(function (): void {
                    $this->showSummaryWidgets = ! $this->showSummaryWidgets;
                }),
            $this->resetDataTesSiswaAction(),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        $widgets = [
            DataSiswaWorkflowHelp::class,
        ];

        if (! $this->showSummaryWidgets) {
            return $widgets;
        }

        return [
            ...$widgets,
            DataSiswaStatsOverview::class,
            PrestasiStatsOverview::class,
            DataSiswaGenderByRombelChart::class,
            DataSiswaStatusChart::class,
            DataSiswaNonAktifReasonChart::class,
            PrestasiByRombelChart::class,
        ];
    }

    public function getHeaderWidgetsColumns(): int|array
    {
        return [
            'default' => 1,
            'md' => 2,
            'xl' => 4,
        ];
    }

    protected function applyPageFiltersFromQuery(): void
    {
        $filters = $this->resolveChartFilters([
            'chart_status' => $this->chartStatus,
            'chart_jk' => $this->chartJk,
            'chart_rombel' => $this->chartRombel,
            'chart_kategori_non_aktif' => $this->chartKategoriNonAktif,
        ]);

        $filters = [
            ...$filters,
            ...$this->resolveDataTesFilters($this->dataTesStatus),
        ];

        if ($filters === []) {
            return;
        }

        $this->tableFilters = $filters;
    }

    #[On('data-siswa-chart-filters-requested')]
    public function applyChartFiltersFromWidget(array $filters = [], array $query = []): void
    {
        $resolvedFilters = $filters !== [] ? $filters : $this->resolveChartFilters($query);

        $this->chartStatus = filled($query['chart_status'] ?? null) ? (string) $query['chart_status'] : null;
        $this->chartJk = filled($query['chart_jk'] ?? null) ? (string) $query['chart_jk'] : null;
        $this->chartRombel = filled($query['chart_rombel'] ?? null) ? (string) $query['chart_rombel'] : null;
        $this->chartKategoriNonAktif = filled($query['chart_kategori_non_aktif'] ?? null) ? (string) $query['chart_kategori_non_aktif'] : null;

        $this->tableFilters = $resolvedFilters !== [] ? $resolvedFilters : null;
        $this->updatedTableFilters();
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, array<string, string>>
     */
    protected function resolveChartFilters(array $query): array
    {
        $filters = [];

        if (filled($query['chart_status'] ?? null)) {
            $filters['status'] = ['value' => (string) $query['chart_status']];
        }

        if (filled($query['chart_jk'] ?? null)) {
            $filters['jk'] = ['value' => (string) $query['chart_jk']];
        }

        if (filled($query['chart_rombel'] ?? null)) {
            $filters['rombel_saat_ini'] = ['value' => (string) $query['chart_rombel']];
        }

        if (filled($query['chart_kategori_non_aktif'] ?? null)) {
            $filters['kategori_non_aktif'] = ['value' => (string) $query['chart_kategori_non_aktif']];
        }

        return $filters;
    }

    /**
     * @return array<string, array<string, bool>>
     */
    protected function resolveDataTesFilters(?string $status): array
    {
        return match ($status) {
            'filled' => ['data_tes_siswa' => ['value' => true]],
            'missing' => ['data_tes_siswa' => ['value' => false]],
            default => [],
        };
    }

    protected function getTableEmptyStateHeading(): ?string
    {
        return match ($this->dataTesFilterState()) {
            true => 'Belum ada siswa dengan Data Tes Siswa',
            false => 'Semua siswa sudah memiliki Data Tes Siswa',
            default => null,
        };
    }

    protected function getTableEmptyStateDescription(): ?string
    {
        return match ($this->dataTesFilterState()) {
            true => 'Gunakan Import Data Tes Siswa untuk mengisi KEPRIBADIAN, GAYA BELAJAR, PROFILING, dan MBTI.',
            false => 'Tidak ada siswa yang masih kosong pada empat field Data Tes Siswa di scope tabel ini.',
            default => null,
        };
    }

    protected function getTableEmptyStateActions(): array
    {
        if ($this->dataTesFilterState() !== true) {
            return [];
        }

        return [
            $this->importDataTesSiswaAction('emptyStateImportDataTes'),
            Actions\Action::make('emptyStateDownloadTemplate')
                ->label('Download Template Data')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->url(route('admin.data-siswa.import-template', absolute: false))
                ->openUrlInNewTab(),
        ];
    }

    protected function dataTesFilterState(): ?bool
    {
        $state = data_get($this->tableFilters, 'data_tes_siswa.value');

        return match (true) {
            $state === true,
            $state === 1,
            $state === '1',
            $state === 'true' => true,
            $state === false,
            $state === 0,
            $state === '0',
            $state === 'false' => false,
            default => null,
        };
    }

    protected function importDataSiswaAction(): Actions\Action
    {
        return Actions\Action::make('importDataSiswa')
            ->label('Import Data Lengkap')
            ->icon('heroicon-o-arrow-up-tray')
            ->color('primary')
            ->modalHeading('Import Data Siswa Lengkap')
            ->modalSubmitActionLabel('Proses Import')
            ->modalWidth('2xl')
            ->visible(fn (): bool => DataSiswaResource::canCreate())
            ->form([
                Forms\Components\Placeholder::make('download_format')
                    ->label('Template Import')
                    ->content(new HtmlString('<div class="space-y-2"><a href="'.route('admin.data-siswa.import-template', absolute: false).'" target="_blank" rel="noopener noreferrer" data-navigate="false" class="inline-flex items-center gap-1 text-primary-600 font-semibold underline">Download template data siswa</a><div class="text-sm text-gray-600">File berisi sheet data lengkap, sheet data tes siswa, dan panduan pengisian.</div></div>')),
                Forms\Components\FileUpload::make('berkas')
                    ->label('Upload File Excel')
                    ->disk('public')
                    ->directory('data-siswa/imports')
                    ->acceptedFileTypes([
                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        'application/vnd.ms-excel',
                    ])
                    ->helperText('Gunakan file template resmi. Jika hanya ingin mengubah 4 field tes siswa, gunakan menu Import Data Tes.')
                    ->required(),
            ])
            ->action(function (array $data): void {
                $disk = Storage::disk('public');
                $path = $data['berkas'] ?? null;

                if (! $path || ! $disk->exists($path)) {
                    Notification::make()
                        ->title('File import tidak ditemukan.')
                        ->danger()
                        ->send();

                    return;
                }

                try {
                    $result = app(DataSiswaWorkbookImporter::class)->import($disk->path($path));
                } catch (Throwable $exception) {
                    report($exception);

                    Notification::make()
                        ->title('Import data siswa gagal.')
                        ->body(filled($exception->getMessage()) ? $exception->getMessage() : 'Terjadi kesalahan saat membaca file Excel.')
                        ->danger()
                        ->send();

                    return;
                } finally {
                    if ($disk->exists($path)) {
                        $disk->delete($path);
                    }
                }

                Notification::make()
                    ->title('Import data siswa selesai.')
                    ->body("{$result['created']} data baru, {$result['updated']} data diperbarui, {$result['skipped']} baris dilewati.")
                    ->success()
                    ->send();
            });
    }

    protected function importDataTesSiswaAction(string $name = 'importDataTesSiswa'): Actions\Action
    {
        return Actions\Action::make($name)
            ->label('Import Data Tes Siswa')
            ->icon('heroicon-o-identification')
            ->color('info')
            ->modalHeading('Import Data Tes Siswa')
            ->modalSubmitActionLabel('Simpan Hasil Review')
            ->modalWidth('7xl')
            ->visible(fn (): bool => DataSiswaResource::canCreate())
            ->form([
                Forms\Components\Placeholder::make('download_format_data_tes')
                    ->label('Template Data Tes')
                    ->content(new HtmlString('<div class="space-y-2"><a href="'.route('admin.data-siswa.import-template', absolute: false).'" target="_blank" rel="noopener noreferrer" data-navigate="false" class="inline-flex items-center gap-1 text-primary-600 font-semibold underline">Download template data tes siswa</a><div class="text-sm text-gray-600">Gunakan sheet <strong>TEMPLATE_DATA_TES_SISWA</strong> atau file Excel dengan kolom: NO, NAMA, KEPRIBADIAN, GAYA BELAJAR, PROFILING, MBTI. Nama yang mirip akan diminta konfirmasi sebelum disimpan.</div></div>')),
                Forms\Components\FileUpload::make('berkas')
                    ->label('Upload File Data Tes')
                    ->disk('public')
                    ->directory('data-siswa/imports')
                    ->acceptedFileTypes([
                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        'application/vnd.ms-excel',
                    ])
                    ->helperText('Import ini tidak membuat siswa baru. Nama yang tidak pas akan diberi alasan, dan nama yang mirip bisa Anda konfirmasi dulu.')
                    ->live()
                    ->afterStateUpdated(function (Set $set, mixed $state): void {
                        $this->populateDataTesImportPreview($set, $state);
                    })
                    ->required(),
                Forms\Components\Hidden::make('preview_summary'),
                Forms\Components\Placeholder::make('preview_summary_display')
                    ->label('Ringkasan Preview')
                    ->content(fn (Get $get): HtmlString => $this->dataTesPreviewSummaryContent($get)),
                Forms\Components\Hidden::make('preview_report_url'),
                Forms\Components\Hidden::make('preview_rows_payload'),
                Forms\Components\Toggle::make('bulk_accept_review')
                    ->label('Bulk accept semua nama mirip')
                    ->helperText('Jika aktif, semua baris "Perlu konfirmasi" akan diterima memakai kandidat pertama yang direkomendasikan sistem.')
                    ->default(false)
                    ->inline(false),
                Forms\Components\Repeater::make('preview_rows')
                    ->label('Review Import Data Tes Siswa - Perlu Konfirmasi')
                    ->itemLabel(fn (?array $state): ?string => filled($state['source_name'] ?? null)
                        ? ((string) ($state['source_name'] ?? '-').' - '.(string) ($state['match_status_label'] ?? '-'))
                        : null)
                    ->schema([
                        Forms\Components\Hidden::make('row_number'),
                        Forms\Components\Hidden::make('source_name'),
                        Forms\Components\Hidden::make('nipd'),
                        Forms\Components\Hidden::make('nisn'),
                        Forms\Components\Hidden::make('kepribadian'),
                        Forms\Components\Hidden::make('gaya_belajar'),
                        Forms\Components\Hidden::make('profiling'),
                        Forms\Components\Hidden::make('mbti'),
                        Forms\Components\Hidden::make('match_status'),
                        Forms\Components\Hidden::make('reason'),
                        Forms\Components\Hidden::make('candidate_options_json'),
                        Forms\Components\Placeholder::make('review_card')
                            ->label(fn (Get $get): string => 'Baris '.($get('row_number') ?: '-'))
                            ->content(fn (Get $get): HtmlString => $this->dataTesPreviewRowContent($get))
                            ->columnSpanFull(),
                        Forms\Components\Select::make('selected_student_id')
                            ->label('Jika nama mirip, pilih siswa yang dimaksud')
                            ->options(fn (Get $get): array => $this->dataTesCandidateOptions($get))
                            ->searchable()
                            ->visible(fn (Get $get): bool => $this->dataTesCandidateOptions($get) !== []),
                        Forms\Components\Toggle::make('confirm_import')
                            ->label('Masukkan ke sistem')
                            ->inline(false)
                            ->visible(fn (Get $get): bool => in_array((string) $get('match_status'), ['ready', 'review'], true)),
                    ])
                    ->columns(1)
                    ->collapsible()
                    ->collapsed()
                    ->addable(false)
                    ->deletable(false)
                    ->reorderable(false)
                    ->default([]),
            ])
            ->action(function (array $data): void {
                $disk = Storage::disk('public');
                $path = $data['berkas'] ?? null;
                $filePath = $this->resolveDataTesImportFilePath($path, $disk);

                if ($filePath === null) {
                    Notification::make()
                        ->title('File data tes tidak ditemukan.')
                        ->body('Upload ulang file Excel, lalu tunggu sampai preview review muncul sebelum menyimpan.')
                        ->danger()
                        ->send();

                    return;
                }

                try {
                    $previewRows = $data['preview_rows'] ?? [];
                    $payloadRows = $this->decodeDataTesPreviewRows($data['preview_rows_payload'] ?? null);

                    if ($payloadRows === [] && (! is_array($previewRows) || $previewRows === [])) {
                        if ($filePath !== null) {
                            $analysis = app(DataSiswaProfileWorkbookImporter::class)->analyze($filePath);
                            $payloadRows = $analysis['rows'] ?? [];
                        }
                    }

                    if ($payloadRows === [] && is_array($previewRows)) {
                        $payloadRows = $previewRows;
                    }

                    if ($payloadRows === []) {
                        Notification::make()
                            ->title('Preview import belum tersedia.')
                            ->body('Upload file terlebih dahulu agar sistem bisa mengecek nama yang cocok, mirip, atau gagal.')
                            ->warning()
                            ->send();

                        return;
                    }

                    $previewRows = $this->mergeVisibleDataTesReviewRows(
                        $payloadRows,
                        is_array($previewRows) ? $previewRows : [],
                        (bool) ($data['bulk_accept_review'] ?? false),
                    );

                    $selectedRows = collect($previewRows)
                        ->filter(fn (mixed $row): bool => is_array($row) && (bool) ($row['confirm_import'] ?? false));

                    if ($selectedRows->isEmpty()) {
                        Notification::make()
                            ->title('Belum ada data yang dipilih untuk diimport.')
                            ->body('Cek bagian Review Import Data Tes Siswa. Untuk nama yang mirip, pilih siswa yang dimaksud lalu aktifkan "Masukkan ke sistem".')
                            ->warning()
                            ->duration(12000)
                            ->send();

                        return;
                    }

                    $result = app(DataSiswaProfileWorkbookImporter::class)->apply($previewRows);
                } catch (Throwable $exception) {
                    report($exception);
                    Log::error('Data Tes Siswa import action failed', [
                        'message' => $exception->getMessage(),
                        'file' => $exception->getFile(),
                        'line' => $exception->getLine(),
                    ]);

                    Notification::make()
                        ->title('Import data tes siswa gagal.')
                        ->body(filled($exception->getMessage()) ? $exception->getMessage() : 'Terjadi kesalahan saat memproses data tes siswa.')
                        ->danger()
                        ->send();

                    return;
                } finally {
                    if (is_string($path) && $disk->exists($path)) {
                        $disk->delete($path);
                    }
                }

                Notification::make()
                    ->title('Import data tes siswa selesai.')
                    ->body($this->formatDataTesImportResult($result))
                    ->success()
                    ->duration(12000)
                    ->send();
            });
    }

    protected function resetDataTesSiswaAction(): Actions\Action
    {
        return Actions\Action::make('resetDataTesSiswa')
            ->label('Reset Semua Data Tes')
            ->icon('heroicon-o-arrow-path')
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading('Reset Semua Data Tes Siswa')
            ->modalDescription('Semua kolom kepribadian, gaya belajar, profiling, dan MBTI pada siswa yang terlihat di tabel akan dikosongkan.')
            ->modalSubmitActionLabel('Reset Semua')
            ->visible(fn (): bool => DataSiswaResource::canCreate())
            ->action(function (): void {
                $affectedRows = DataSiswa::applyVisibleScope(DataSiswa::query(), auth()->user())
                    ->update([
                        'kepribadian' => null,
                        'gaya_belajar' => null,
                        'profiling' => null,
                        'mbti' => null,
                    ]);

                Notification::make()
                    ->title('Data tes siswa berhasil direset.')
                    ->body("{$affectedRows} data siswa diperbarui.")
                    ->success()
                    ->send();
            });
    }

    protected function populateDataTesImportPreview(Set $set, mixed $state): void
    {
        $disk = Storage::disk('public');
        $filePath = $this->resolveDataTesImportFilePath($state, $disk);

        if ($filePath === null) {
            $set('preview_summary', 'Upload file terlebih dahulu untuk melihat hasil review import.');
            $set('preview_report_url', null);
            $set('preview_rows_payload', null);
            $set('preview_rows', []);

            return;
        }

        try {
            $analysis = app(DataSiswaProfileWorkbookImporter::class)->analyze($filePath);
        } catch (Throwable $exception) {
            report($exception);

            $set('preview_summary', filled($exception->getMessage())
                ? 'Gagal membaca file: '.$exception->getMessage()
                : 'Gagal membaca file data tes siswa.');
            $set('preview_report_url', null);
            $set('preview_rows_payload', null);
            $set('preview_rows', []);

            return;
        }

        $reviewToken = DataSiswaImportReviewShareSupport::store(
            $analysis['rows'] ?? [],
            $analysis['summary'] ?? [],
        );

        $set('preview_summary', $this->formatDataTesPreviewSummary($analysis['summary'] ?? []));
        $set('preview_report_url', DataSiswaImportReviewShareSupport::exportUrl($reviewToken));
        $set('preview_rows_payload', json_encode($analysis['rows'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $set('preview_rows', $this->filterVisibleDataTesReviewRows($analysis['rows'] ?? []));
    }

    protected function resolveDataTesImportFilePath(mixed $state, mixed $disk): ?string
    {
        if (is_array($state)) {
            foreach ($state as $item) {
                $path = $this->resolveDataTesImportFilePath($item, $disk);

                if ($path !== null) {
                    return $path;
                }
            }

            return null;
        }

        if ($state instanceof TemporaryUploadedFile) {
            $path = $state->getRealPath();

            return is_file($path) ? $path : null;
        }

        if (is_object($state) && method_exists($state, 'getRealPath')) {
            $path = $state->getRealPath();

            return is_string($path) && is_file($path) ? $path : null;
        }

        if (! is_string($state) || trim($state) === '') {
            return null;
        }

        $path = trim($state);

        if (is_file($path)) {
            return $path;
        }

        return $disk->exists($path) ? $disk->path($path) : null;
    }

    /**
     * @param  array<string, mixed>  $summary
     */
    protected function formatDataTesPreviewSummary(array $summary): string
    {
        return implode("\n", [
            'Siap diimport: '.(int) ($summary['ready'] ?? 0),
            'Perlu konfirmasi nama mirip: '.(int) ($summary['review'] ?? 0),
            'Tidak ditemukan: '.(int) ($summary['not_found'] ?? 0),
            'Dilewati: '.(int) ($summary['skipped'] ?? 0),
            'Catatan: centang hanya data yang memang ingin dimasukkan ke sistem.',
        ]);
    }

    protected function dataTesPreviewRowContent(Get $get): HtmlString
    {
        $statusColor = match ((string) ($get('match_status') ?? '')) {
            'ready' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
            'review' => 'bg-amber-50 text-amber-700 border-amber-200',
            'not_found' => 'bg-rose-50 text-rose-700 border-rose-200',
            default => 'bg-slate-50 text-slate-600 border-slate-200',
        };

        $lines = [
            '<div class="space-y-1 text-sm">',
            '<div><span class="font-semibold">Nama upload:</span> '.e((string) ($get('source_name') ?? '-')).'</div>',
            '<div class="flex flex-wrap items-center gap-2"><span class="font-semibold">Status:</span><span class="inline-flex rounded-full border px-2.5 py-0.5 text-xs font-semibold '.$statusColor.'">'.e((string) ($get('match_status_label') ?? '-')).'</span></div>',
            '<div><span class="font-semibold">Alasan:</span> '.e((string) ($get('reason') ?? '-')).'</div>',
            '<div><span class="font-semibold">Data tes:</span> '.e(implode(' | ', array_filter([
                'KEPRIBADIAN: '.((string) ($get('kepribadian') ?? '')),
                'GAYA BELAJAR: '.((string) ($get('gaya_belajar') ?? '')),
                'PROFILING: '.((string) ($get('profiling') ?? '')),
                'MBTI: '.((string) ($get('mbti') ?? '')),
            ], fn (string $item): bool => ! Str::endsWith($item, ': ')))).'</div>',
            '</div>',
        ];

        return new HtmlString(implode('', $lines));
    }

    protected function dataTesPreviewSummaryContent(Get $get): HtmlString
    {
        $summary = nl2br(e((string) ($get('preview_summary') ?? 'Upload file terlebih dahulu untuk melihat hasil review import.')));
        $reportUrl = trim((string) ($get('preview_report_url') ?? ''));

        $linkHtml = filled($reportUrl)
            ? '<div class="mt-3"><a href="'.e($reportUrl).'" target="_blank" rel="noopener noreferrer" data-navigate="false" class="inline-flex items-center gap-1 rounded-lg border border-sky-300 bg-sky-50 px-3 py-2 text-xs font-semibold text-sky-800 hover:bg-sky-100">Download Laporan Gagal / Review</a></div>'
            : '';

        return new HtmlString('<div class="text-sm text-gray-700">'.$summary.'<div class="mt-2 text-xs text-gray-500">Daftar review di bawah otomatis hanya menampilkan baris yang perlu konfirmasi. Baris siap import tetap akan diproses otomatis.</div>'.$linkHtml.'</div>');
    }

    /**
     * @return array<string, string>
     */
    protected function dataTesCandidateOptions(Get $get): array
    {
        $options = json_decode((string) ($get('candidate_options_json') ?? '[]'), true);

        if (! is_array($options)) {
            return [];
        }

        return collect($options)
            ->filter(fn (mixed $item): bool => is_array($item) && isset($item['id'], $item['label']))
            ->mapWithKeys(fn (array $item): array => [(string) $item['id'] => (string) $item['label']])
            ->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    protected function filterVisibleDataTesReviewRows(array $rows): array
    {
        return collect($rows)
            ->filter(fn (mixed $row): bool => is_array($row) && ($row['match_status'] ?? null) === 'review')
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function decodeDataTesPreviewRows(mixed $payload): array
    {
        if (is_array($payload)) {
            return $payload;
        }

        if (! is_string($payload) || trim($payload) === '') {
            return [];
        }

        $rows = json_decode($payload, true);

        return is_array($rows) ? $rows : [];
    }

    /**
     * @param  array<int, array<string, mixed>>  $allRows
     * @param  array<int, array<string, mixed>>  $visibleRows
     * @return array<int, array<string, mixed>>
     */
    protected function mergeVisibleDataTesReviewRows(array $allRows, array $visibleRows, bool $bulkAcceptReview): array
    {
        $visibleByRowNumber = collect($visibleRows)
            ->filter(fn (mixed $row): bool => is_array($row) && filled($row['row_number'] ?? null))
            ->keyBy(fn (array $row): string => (string) $row['row_number']);

        return collect($allRows)
            ->filter(fn (mixed $row): bool => is_array($row))
            ->map(function (array $row) use ($visibleByRowNumber, $bulkAcceptReview): array {
                $rowNumber = (string) ($row['row_number'] ?? '');

                if ($rowNumber !== '' && $visibleByRowNumber->has($rowNumber)) {
                    $row = [
                        ...$row,
                        ...$visibleByRowNumber->get($rowNumber),
                    ];
                }

                if ($bulkAcceptReview && ($row['match_status'] ?? null) === 'review') {
                    $row['selected_student_id'] = filled($row['selected_student_id'] ?? null)
                        ? $row['selected_student_id']
                        : $this->firstDataTesCandidateId($row);
                    $row['confirm_import'] = filled($row['selected_student_id'] ?? null);
                }

                return $row;
            })
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $row
     */
    protected function firstDataTesCandidateId(array $row): ?int
    {
        $options = json_decode((string) ($row['candidate_options_json'] ?? '[]'), true);

        if (! is_array($options)) {
            return null;
        }

        $candidate = collect($options)
            ->first(fn (mixed $item): bool => is_array($item) && filled($item['id'] ?? null));

        return is_array($candidate) ? (int) $candidate['id'] : null;
    }

    /**
     * @param  array<string, mixed>  $result
     */
    protected function formatDataTesImportResult(array $result): string
    {
        $lines = [
            (int) ($result['updated'] ?? 0).' data diperbarui',
            (int) ($result['unchanged'] ?? 0).' data tidak berubah',
            (int) ($result['skipped'] ?? 0).' data dilewati',
            (int) ($result['failed'] ?? 0).' data gagal diproses',
        ];

        $details = collect($result['details'] ?? [])
            ->filter(fn (mixed $detail): bool => filled($detail))
            ->take(6)
            ->values();

        if ($details->isNotEmpty()) {
            $lines[] = 'Rincian: '.$details->implode(' | ');
        }

        return implode('. ', $lines).'.';
    }
}
