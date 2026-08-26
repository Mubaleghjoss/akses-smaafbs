<?php

namespace App\Filament\Resources\DataSiswaResource\Pages;

use App\Filament\Resources\DataSiswaResource;
use App\Models\Rombel;
use App\Models\SpmbSyncRun;
use App\Support\SpmbSync\SpmbApiClient;
use App\Support\SpmbSync\SpmbStudentSyncService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Facades\Schema;
use Throwable;

class SyncSpmbStudents extends Page
{
    protected static string $resource = DataSiswaResource::class;

    protected static ?string $title = 'Sinkron Siswa Lulus SPMB';

    protected static ?string $breadcrumb = 'Sinkron SPMB';

    protected string $view = 'filament.resources.data-siswa-resource.pages.sync-spmb-students';

    /**
     * @var array<int, array<string, mixed>>
     */
    public array $rows = [];

    /**
     * @var array<string, int>
     */
    public array $stats = [
        'fetched' => 0,
        'new' => 0,
        'update' => 0,
        'unchanged' => 0,
        'conflict' => 0,
    ];

    /**
     * @var array<int, string>
     */
    public array $selected = [];

    /**
     * @var array<string, string>
     */
    public array $resolutions = [];

    /**
     * Pilihan rombel per baris: source_id => nama rombel.
     * Siswa baru diarahkan ke rombel angkatan baru, siswa pindahan ke rombel
     * yang sedang berjalan — daftar rombel diambil dari data lokal (app),
     * BUKAN dari SPMB, karena app yang memegang informasi rombel.
     *
     * @var array<string, string>
     */
    public array $rombelPilihan = [];

    public ?string $lastFetchedAt = null;

    public function mount(): void
    {
        abort_unless(DataSiswaResource::canCreate(), 403);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')
                ->label('Kembali ke Data Siswa')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(DataSiswaResource::getUrl('index')),
            Action::make('testConnection')
                ->label('Tes Koneksi')
                ->icon('heroicon-o-signal')
                ->color('gray')
                ->action(fn (): mixed => $this->testConnection()),
            Action::make('loadPreview')
                ->label('Ambil Preview')
                ->icon('heroicon-o-arrow-path')
                ->color('primary')
                ->action(fn (): mixed => $this->loadPreview()),
            Action::make('applySync')
                ->label('Terapkan Sinkronisasi')
                ->icon('heroicon-o-cloud-arrow-down')
                ->color('success')
                ->visible(fn (): bool => $this->rows !== [])
                ->requiresConfirmation()
                ->modalHeading('Terapkan data terpilih?')
                ->modalDescription('Data sumber akan diambil ulang sebelum disimpan. NIPD, billing, status, dan data operasional lokal tetap dipertahankan. Rombel hanya berubah bila Anda memilihnya pada baris yang bersangkutan.')
                ->action(fn (): mixed => $this->applySync()),
        ];
    }

    public function testConnection(): void
    {
        try {
            $result = app(SpmbApiClient::class)->testConnection();

            Notification::make()
                ->success()
                ->title('Koneksi API SPMB berhasil')
                ->body("Tersedia {$result['total']} siswa lulus. API versi ".($result['api_version'] ?: '-').'.')
                ->send();
        } catch (Throwable $exception) {
            $this->notifyFailure('Tes koneksi gagal', $exception);
        }
    }

    public function loadPreview(): void
    {
        try {
            $sources = app(SpmbApiClient::class)->fetchAll();
            $preview = app(SpmbStudentSyncService::class)->preview($sources);

            $this->rows = $preview['rows'];
            $this->stats = $preview['stats'];
            $this->selected = collect($this->rows)
                ->whereIn('status', ['baru', 'update'])
                ->pluck('source_id')
                ->map(fn ($id): string => (string) $id)
                ->all();
            $this->resolutions = collect($this->rows)
                ->where('status', 'konflik')
                ->mapWithKeys(fn (array $row): array => [(string) $row['source_id'] => 'skip'])
                ->all();

            // Pertahankan pilihan rombel yang sudah dibuat admin agar tidak
            // hilang setiap kali preview dimuat ulang.
            $this->rombelPilihan = collect($this->rows)
                ->mapWithKeys(fn (array $row): array => [
                    (string) $row['source_id'] => $this->rombelPilihan[(string) $row['source_id']]
                        ?? (string) ($row['target']['rombel'] ?? ''),
                ])
                ->all();

            $this->lastFetchedAt = now()->format('d M Y H:i:s');

            Notification::make()
                ->success()
                ->title('Preview SPMB dimuat')
                ->body("{$this->stats['fetched']} siswa lulus diperiksa.")
                ->send();
        } catch (Throwable $exception) {
            $this->notifyFailure('Gagal mengambil preview', $exception);
        }
    }

    public function applySync(): void
    {
        if ($this->rows === []) {
            Notification::make()
                ->warning()
                ->title('Ambil preview terlebih dahulu')
                ->send();

            return;
        }

        try {
            $sources = app(SpmbApiClient::class)->fetchAll();
            $result = app(SpmbStudentSyncService::class)->apply(
                $sources,
                $this->selected,
                $this->resolutions,
                auth()->id(),
                $this->rombelPilihan,
            );

            Notification::make()
                ->success()
                ->title('Sinkronisasi SPMB selesai')
                ->body(
                    "Dibuat {$result['created']}, diperbarui {$result['updated']}, ".
                    "tidak berubah {$result['unchanged']}, konflik {$result['conflict']}, ".
                    "dilewati {$result['skipped']}."
                )
                ->send();

            $this->loadPreview();
        } catch (Throwable $exception) {
            $this->notifyFailure('Sinkronisasi SPMB gagal', $exception);
        }
    }

    public function getViewData(): array
    {
        return [
            'recentRuns' => Schema::hasTable('spmb_sync_runs')
                ? SpmbSyncRun::query()->with('user:id,name,email')->latest('id')->limit(10)->get()
                : collect(),
            // Daftar rombel dari data LOKAL (app), dipisah aktif / non-aktif.
            // Dipakai admin untuk menentukan siswa masuk kelas mana.
            'rombelOptions' => $this->rombelOptions(),
        ];
    }

    /**
     * Rombel yang tersedia untuk penempatan.
     *
     * @return array<string, string>
     */
    private function rombelOptions(): array
    {
        if (! Schema::hasTable('rombels')) {
            return [];
        }

        return Rombel::query()
            ->where('is_active', true)
            ->orderBy('nama')
            ->pluck('nama', 'nama')
            ->all();
    }

    private function notifyFailure(string $title, Throwable $exception): void
    {
        report($exception);

        Notification::make()
            ->danger()
            ->title($title)
            ->body(str($exception->getMessage())->limit(300))
            ->send();
    }
}
