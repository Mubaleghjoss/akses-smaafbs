<?php

namespace App\Filament\Pages\Perpustakaan;

use App\Filament\Resources\PerpustakaanLiterasiMaterialResource;
use App\Models\PerpustakaanLiterasiDispensation;
use App\Models\PerpustakaanLiterasiMaterial;
use App\Models\PerpustakaanLiterasiSimilarityMatch;
use App\Models\User;
use App\Support\Perpustakaan\LiteracyAnalysisShareText;
use App\Support\Perpustakaan\LiteracyRespondentBase;
use App\Support\Perpustakaan\LiterasiAnalytics;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema as SchemaFacade;
use Livewire\Attributes\Url;

/**
 * Halaman analisis literasi numerasi yang berdiri sendiri.
 *
 * Sebelumnya panel analitik menempel pada halaman daftar soal, sehingga rekap
 * dan pengelolaan soal saling berebut ruang. Halaman ini memisahkannya dan
 * menyimpan seluruh filter pada URL agar tautan hasil filter bisa dibagikan.
 */
class AnalisisLiterasiPage extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar-square';

    protected static string|\UnitEnum|null $navigationGroup = 'Perpustakaan';

    protected static ?string $navigationLabel = 'Analisis Literasi';

    protected static ?string $slug = 'analisis-literasi';

    protected static ?string $title = 'Analisis Literasi Numerasi';

    protected static ?int $navigationSort = 21;

    protected static ?string $permissionPrefix = 'perpustakaan_literasi';

    protected string $view = 'filament.pages.perpustakaan.analisis-literasi';

    #[Url(as: 'dari')]
    public ?string $dari = null;

    #[Url(as: 'sampai')]
    public ?string $sampai = null;

    #[Url(as: 'kategori')]
    public ?string $kategori = null;

    #[Url(as: 'materi')]
    public ?string $materi = null;

    #[Url(as: 'kelas')]
    public ?string $kelas = null;

    protected ?array $analyticsCache = null;

    protected ?array $baseCache = null;

    public static function shouldRegisterNavigation(): bool
    {
        return static::hasRequiredTables() && static::userCanModule('view');
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

    public function updated(string $property): void
    {
        if (in_array($property, ['dari', 'sampai', 'kategori', 'materi', 'kelas'], true)) {
            $this->analyticsCache = null;
            $this->baseCache = null;
        }

        // Materi terikat pada kategori: mengganti kategori membuat pilihan
        // materi sebelumnya tidak lagi relevan.
        if ($property === 'kategori') {
            $this->materi = null;
        }
    }

    public function terapkanBulanIni(): void
    {
        $this->dari = now()->startOfMonth()->toDateString();
        $this->sampai = now()->toDateString();
        $this->resetCaches();
    }

    public function terapkanSemesterIni(): void
    {
        $now = now();
        $this->dari = $now->month >= 7
            ? $now->copy()->setMonth(7)->startOfMonth()->toDateString()
            : $now->copy()->setMonth(1)->startOfMonth()->toDateString();
        $this->sampai = $now->toDateString();
        $this->resetCaches();
    }

    public function bersihkanFilter(): void
    {
        $this->kategori = null;
        $this->materi = null;
        $this->kelas = null;
        $this->resetCaches();
    }

    /**
     * Basis responden untuk filter yang aktif.
     *
     * @return array<string, mixed>
     */
    public function getBaseProperty(): array
    {
        if ($this->baseCache !== null) {
            return $this->baseCache;
        }

        [$start, $end] = $this->range();
        $material = $this->selectedMaterial();
        $classes = filled($this->kelas) ? [$this->kelas] : null;

        return $this->baseCache = LiterasiAnalytics::respondentBase(
            $material,
            $start,
            $end,
            $material === null && filled($this->kategori) ? $this->kategori : null,
            $classes,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function getAnalyticsProperty(): array
    {
        if ($this->analyticsCache !== null) {
            return $this->analyticsCache;
        }

        [$start, $end] = $this->range();
        $material = $this->selectedMaterial();
        $category = $material === null && filled($this->kategori) ? $this->kategori : null;
        $classes = filled($this->kelas) ? [$this->kelas] : null;

        return $this->analyticsCache = [
            'grading_summary' => LiterasiAnalytics::gradingSummary($material, $start, $end, $category, $classes),
            // 7 kelas dengan jawaban terbanyak sesuai permintaan operasional.
            'class_response_ranking' => LiterasiAnalytics::classResponseRanking($material, $start, $end, 7, $category, $classes),
            'least_class_response_ranking' => LiterasiAnalytics::leastClassResponseRanking($material, $start, $end, 5, $category, $classes),
            'class_submission_timeline' => LiterasiAnalytics::classSubmissionTimeline($material, $start, $end, $category, $classes),
            'class_correct_ranking' => LiterasiAnalytics::classCorrectRanking($material, $start, $end, 5, $category, $classes),
            // Peringkat jawaban benar untuk SELURUH kelas, termasuk kelas yang
            // masih menunggu penilaian, beserta penanda peringkat sementara.
            'class_correct_ranking_full' => LiterasiAnalytics::classCorrectRankingFull($material, $start, $end, $category, $classes),
            // Penjelas "kenapa akurasi belum 100%": materi yang jawabannya masih
            // menunggu penilaian, dipecah per kelas.
            'class_pending_grading' => LiterasiAnalytics::classPendingGrading($material, $start, $end, $category, $classes),
            'student_correct_ranking_by_class' => LiterasiAnalytics::studentCorrectRankingByClass($material, $start, $end, 5, $category, $classes),
            'student_wrong_ranking' => LiterasiAnalytics::studentWrongRanking($material, $start, $end, 10, $category, $classes),
            'frequent_missing_students' => LiterasiAnalytics::frequentMissingStudents($this->base, 10),
            'plagiarism_class_ranking' => LiterasiAnalytics::plagiarismClassRanking(
                $material,
                $start,
                $end,
                5,
                $category,
                [
                    PerpustakaanLiterasiSimilarityMatch::REVIEW_SUSPECTED,
                    PerpustakaanLiterasiSimilarityMatch::REVIEW_CONFIRMED,
                ],
                $classes,
            ),
            'plagiarism_student_ranking' => LiterasiAnalytics::plagiarismStudentRanking(
                $material,
                $start,
                $end,
                10,
                $category,
                [
                    PerpustakaanLiterasiSimilarityMatch::REVIEW_SUSPECTED,
                    PerpustakaanLiterasiSimilarityMatch::REVIEW_CONFIRMED,
                ],
                $classes,
            ),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function getKategoriOptionsProperty(): array
    {
        return PerpustakaanLiterasiMaterial::programCategoryOptions();
    }

    /**
     * @return array<string, string>
     */
    public function getMateriOptionsProperty(): array
    {
        [$start, $end] = $this->range();

        return PerpustakaanLiterasiMaterial::query()
            ->when(filled($this->kategori), fn ($query) => $query->where('program_category', $this->kategori))
            ->whereIn('id', LiteracyRespondentBase::materialIdsInScope($this->kategori, $start, $end))
            ->orderByDesc('opens_at')
            ->pluck('title', 'id')
            ->map(fn ($title): string => (string) $title)
            ->all();
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

    /**
     * @return array<string, string>
     */
    public function getAlasanLabelsProperty(): array
    {
        return PerpustakaanLiterasiDispensation::reasonOptions();
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
            return $this->kategoriOptions[$this->kategori] ?? 'Kategori terpilih';
        }

        return 'Semua kategori';
    }

    protected function getViewData(): array
    {
        return [
            'base' => $this->base,
            'analytics' => $this->analytics,
            'kategoriOptions' => $this->kategoriOptions,
            'materiOptions' => $this->materiOptions,
            'kelasOptions' => $this->kelasOptions,
            'alasanLabels' => $this->alasanLabels,
            'periodeLabel' => $this->periodeLabel,
            'lingkupLabel' => $this->lingkupLabel,
            'shareSections' => $this->shareSections(),
            // Nilai filter mentah diteruskan supaya partial bisa membangun
            // tautan ke halaman rincian dengan lingkup yang sama.
            'dari' => $this->dari,
            'sampai' => $this->sampai,
            'kategori' => $this->kategori,
            'materi' => $this->materi,
            'kelas' => $this->kelas,
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('salinRekap')
                ->label('Salin ke WhatsApp')
                ->icon('heroicon-o-clipboard-document-list')
                ->modalHeading('Salin hasil analisis ke WhatsApp')
                ->modalDescription('Pilih bagian yang ingin disalin. Semua teks mengikuti filter yang aktif di halaman ini.')
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Tutup')
                ->modalWidth('4xl')
                ->modalContent(fn () => view(
                    'filament.pages.perpustakaan.partials.rekap-share',
                    [
                        'sections' => $this->shareSections(),
                        'sectionLabels' => LiteracyAnalysisShareText::sectionLabels(),
                        'allText' => $this->shareText(),
                        'periodeLabel' => $this->periodeLabel,
                        'lingkupLabel' => $this->lingkupLabel,
                    ],
                )),
            Action::make('kelolaDispensasi')
                ->label('Kelola Dispensasi')
                ->icon('heroicon-o-user-minus')
                ->color('gray')
                ->url(fn (): string => KelolaDispensasiPage::getUrl())
                ->visible(fn (): bool => KelolaDispensasiPage::canAccess()),
            Action::make('kelolaSoal')
                ->label('Kelola Soal Literasi')
                ->icon('heroicon-o-queue-list')
                ->color('gray')
                ->url(fn (): string => PerpustakaanLiterasiMaterialResource::getUrl())
                ->visible(fn (): bool => PerpustakaanLiterasiMaterialResource::canViewAny()),
        ];
    }

    /**
     * Teks per bagian yang dibangun dari data yang sedang tampil, sehingga
     * angka pada modal salin selalu sama dengan angka pada halaman.
     *
     * @return array<string, string>
     */
    public function shareSections(): array
    {
        return LiteracyAnalysisShareText::sections(
            $this->base,
            $this->analytics,
            $this->periodeLabel,
            $this->lingkupLabel,
            filled($this->kelas) ? $this->kelas : null,
        );
    }

    /**
     * Gabungan semua bagian dari data yang sedang tampil. Berbeda dari rekap
     * bulanan lama, metode ini menghormati filter materi dan kelas juga.
     */
    public function shareText(): string
    {
        return collect($this->shareSections())->implode("\n\n");
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

        // Rentang terbalik akan mengosongkan seluruh panel tanpa penjelasan,
        // jadi urutannya dinormalkan lebih dulu.
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

    protected function resetCaches(): void
    {
        $this->analyticsCache = null;
        $this->baseCache = null;
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
