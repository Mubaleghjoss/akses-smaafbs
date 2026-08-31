<?php

namespace App\Filament\Pages\Perpustakaan;

use App\Models\PerpustakaanLiterasiMaterial;
use App\Models\User;
use App\Support\Perpustakaan\LiteracyRespondentBase;
use App\Support\Perpustakaan\LiterasiAnalytics;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema as SchemaFacade;
use Livewire\Attributes\Url;

/**
 * Rincian pengisian harian satu kelas pada halaman tersendiri.
 *
 * Sebelumnya rincian ini berupa drill-down di dalam tabel Timeline: begitu
 * dibuka, daftar nama siswa memanjang ke bawah dan mendorong seluruh baris
 * kelas lain sehingga tabel jadi sulit dibaca. Rincian dipindah ke halaman ini
 * agar tiap hari tampil sebagai kartu dengan dua kolom sejajar — yang sudah
 * mengisi dan yang belum mengisi sampai hari itu.
 */
class RincianHarianKelasPage extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-calendar-days';

    protected static string|\UnitEnum|null $navigationGroup = 'Perpustakaan';

    protected static ?string $slug = 'rincian-harian-literasi';

    protected static ?string $title = 'Rincian Harian Pengisian Kelas';

    protected static ?string $permissionPrefix = 'perpustakaan_literasi';

    protected string $view = 'filament.pages.perpustakaan.rincian-harian-kelas';

    #[Url(as: 'kelas')]
    public ?string $kelas = null;

    #[Url(as: 'dari')]
    public ?string $dari = null;

    #[Url(as: 'sampai')]
    public ?string $sampai = null;

    #[Url(as: 'kategori')]
    public ?string $kategori = null;

    #[Url(as: 'materi')]
    public ?string $materi = null;

    protected ?array $rincianCache = null;

    /** Halaman ini selalu dibuka dari tautan pada Analisis Literasi. */
    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function canAccess(): bool
    {
        return static::hasRequiredTables() && static::userCanModule('view');
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);

        if (blank($this->dari)) {
            $this->dari = now()->startOfMonth()->toDateString();
        }

        if (blank($this->sampai)) {
            $this->sampai = now()->toDateString();
        }
    }

    public function getTitle(): string
    {
        return filled($this->kelas)
            ? 'Rincian Harian '.$this->kelas
            : 'Rincian Harian Pengisian Kelas';
    }

    /**
     * Tautan ke halaman ini dengan filter yang sedang aktif pada analisis.
     */
    public static function urlForClass(
        string $class,
        ?string $dari = null,
        ?string $sampai = null,
        ?string $kategori = null,
        ?string $materi = null,
    ): ?string {
        try {
            return static::getUrl(array_filter([
                'kelas' => $class,
                'dari' => $dari,
                'sampai' => $sampai,
                'kategori' => $kategori,
                'materi' => $materi,
            ], fn ($value): bool => filled($value)));
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function getRincianProperty(): array
    {
        if ($this->rincianCache !== null) {
            return $this->rincianCache;
        }

        if (blank($this->kelas)) {
            return $this->rincianCache = [];
        }

        [$start, $end] = $this->range();
        $material = $this->selectedMaterial();

        return $this->rincianCache = LiterasiAnalytics::classDailyBreakdown(
            $material,
            $start,
            $end,
            (string) $this->kelas,
            $material === null && filled($this->kategori) ? $this->kategori : null,
        );
    }

    public function getPeriodeLabelProperty(): string
    {
        [$start, $end] = $this->range();

        return $start->translatedFormat('d F Y').' s.d. '.$end->translatedFormat('d F Y');
    }

    public function getLingkupLabelProperty(): string
    {
        $material = $this->selectedMaterial();

        if ($material !== null) {
            return 'Materi: '.$material->title;
        }

        if (filled($this->kategori)) {
            return PerpustakaanLiterasiMaterial::programCategoryOptions()[$this->kategori] ?? 'Kategori terpilih';
        }

        return 'Semua kategori';
    }

    /**
     * @return array<string, string>
     */
    public function getKelasOptionsProperty(): array
    {
        return collect(LiteracyRespondentBase::activeClassNames())
            ->mapWithKeys(fn (string $class): array => [$class => $class])
            ->all();
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['kelas', 'dari', 'sampai', 'kategori', 'materi'], true)) {
            $this->rincianCache = null;
        }
    }

    protected function getViewData(): array
    {
        return [
            'rincian' => $this->rincian,
            'periodeLabel' => $this->periodeLabel,
            'lingkupLabel' => $this->lingkupLabel,
            'kelasOptions' => $this->kelasOptions,
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('kembaliAnalisis')
                ->label('Kembali ke Analisis')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(fn (): string => AnalisisLiterasiPage::getUrl(array_filter([
                    'dari' => $this->dari,
                    'sampai' => $this->sampai,
                    'kategori' => $this->kategori,
                    'materi' => $this->materi,
                ], fn ($value): bool => filled($value))))
                ->visible(fn (): bool => AnalisisLiterasiPage::canAccess()),
        ];
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    protected function range(): array
    {
        $start = filled($this->dari)
            ? Carbon::parse($this->dari)->startOfDay()
            : now()->startOfMonth();
        $end = filled($this->sampai)
            ? Carbon::parse($this->sampai)->endOfDay()
            : now()->endOfDay();

        return $start->greaterThan($end)
            ? [$end->copy()->startOfDay(), $start->copy()->endOfDay()]
            : [$start, $end];
    }

    protected function selectedMaterial(): ?PerpustakaanLiterasiMaterial
    {
        if (blank($this->materi)) {
            return null;
        }

        return PerpustakaanLiterasiMaterial::query()->find($this->materi);
    }

    protected static function hasRequiredTables(): bool
    {
        return SchemaFacade::hasTable('perpustakaan_literasi_materials')
            && SchemaFacade::hasTable('perpustakaan_literasi_responses')
            && SchemaFacade::hasTable('data_siswa');
    }

    protected static function userCanModule(string $ability): bool
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return false;
        }

        $user->loadMissing('roles');

        if ($user->hasFullAdminAccess()) {
            return true;
        }

        $prefix = static::$permissionPrefix;

        if (blank($prefix)) {
            return false;
        }

        return $ability === 'view'
            ? $user->canViewModule($prefix)
            : $user->canManageModule($prefix);
    }
}
