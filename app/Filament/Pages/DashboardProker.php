<?php

namespace App\Filament\Pages;

use App\Filament\Resources\ProkerBidangResource;
use App\Filament\Resources\ProkerResource;
use App\Filament\Widgets\ProkerIndicatorByBidangChart;
use App\Filament\Widgets\ProkerStatsOverview;
use App\Filament\Widgets\ProkerStatusChart;
use App\Models\Proker;
use App\Models\ProkerBidang;
use App\Models\ProkerIndikator;
use App\Models\ProkerUpdate;
use App\Models\User;
use App\Support\Proker\ProkerWorkbookImporter;
use App\Support\Security\EndpointProtectionPolicy;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema as SchemaFacade;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
use Throwable;

class DashboardProker extends Page
{
    protected static ?bool $requiredTablesAvailable = null;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar-square';

    protected static string|\UnitEnum|null $navigationGroup = 'Manajemen Sekolah';

    protected static ?int $navigationSort = 5;

    protected static ?string $navigationLabel = 'Dashboard Proker';

    protected static ?string $title = 'Dashboard Proker';

    protected static ?string $permissionPrefix = 'proker_dashboard';

    protected string $view = 'filament.pages.dashboard-proker';

    public ?string $indicatorPeriodYear = null;

    public string $indicatorProkerSearch = '';

    public ?string $quickChecklistPeriodYear = null;

    public string $quickChecklistProkerSearch = '';

    public bool $showSummaryWidgets = true;

    public bool $dashboardDataReady = true;

    protected ?array $analysisItemsCache = null;

    protected ?array $dashboardMetricsCache = null;

    protected ?array $decisionRecommendationsCache = null;

    protected ?array $quickFilterChipsCache = null;

    protected ?string $summaryTextCache = null;

    protected ?Collection $recentUpdatesCache = null;

    protected ?Collection $attentionProkersCache = null;

    public static function shouldRegisterNavigation(): bool
    {
        return static::hasRequiredTables() && static::userCanModule('view');
    }

    public static function canAccess(): bool
    {
        return static::hasRequiredTables() && static::userCanModule('view');
    }

    /** @var array<string, array<int, array<string, mixed>>> */
    protected array $indicatorSummaryRowsCache = [];

    /** @var array<string, array<string, int|string>> */
    protected array $indicatorSummaryMetaCache = [];

    /** @var array<string, array<string, int|string>> */
    protected array $quickChecklistMetaCache = [];

    /** @var array<string, Collection<int, Proker>> */
    protected array $quickChecklistProkersCache = [];

    public function mount(): void
    {
        $defaultPeriod = $this->resolveDefaultIndicatorPeriodYear();

        $this->indicatorPeriodYear = $defaultPeriod;
        $this->quickChecklistPeriodYear = $defaultPeriod;
        $this->showSummaryWidgets = ! $this->isDegradedDashboardMode();
        $this->dashboardDataReady = $this->showSummaryWidgets;
    }

    public function getHeaderWidgets(): array
    {
        if (! $this->showSummaryWidgets || ! $this->dashboardDataReady) {
            return [];
        }

        return [
            ProkerStatsOverview::class,
            ProkerStatusChart::class,
            ProkerIndicatorByBidangChart::class,
        ];
    }

    public function loadDashboardData(): void
    {
        $this->showSummaryWidgets = true;
        $this->dashboardDataReady = true;
    }

    public function getHeaderActions(): array
    {
        $actions = [
            Action::make('toggleSummaryWidgets')
                ->label(fn (): string => $this->showSummaryWidgets ? 'Collapse Semua Ringkasan' : 'Muat Semua Ringkasan')
                ->icon(fn (): string => $this->showSummaryWidgets ? 'heroicon-o-eye-slash' : 'heroicon-o-bolt')
                ->color('gray')
                ->action(function (): void {
                    $this->showSummaryWidgets = ! $this->showSummaryWidgets;
                    $this->dashboardDataReady = $this->showSummaryWidgets;
                }),
        ];

        if (static::userCanModule('manage')) {
            array_unshift(
                $actions,
                Action::make('kelolaBidang')
                    ->label('Kelola Bidang')
                    ->icon('heroicon-o-building-office-2')
                    ->url(ProkerBidangResource::getUrl('index'))
                    ->hidden(fn (): bool => ! ProkerBidangResource::canAccess()),
                Action::make('buatProker')
                    ->label('Tambah Proker')
                    ->icon('heroicon-o-plus')
                    ->url(ProkerResource::getUrl('create'))
                    ->hidden(fn (): bool => ! ProkerResource::canCreate()),
                $this->importProkerAction(),
            );
        }

        return $actions;
    }

    public function canManageDashboard(): bool
    {
        return static::userCanModule('manage');
    }

    protected function getProkerIndexUrl(array $filters = [], ?string $search = null): string
    {
        $parameters = [];

        foreach ($filters as $name => $value) {
            if (blank($value) && $value !== 0 && $value !== '0') {
                continue;
            }

            $parameters["filters[{$name}][value]"] = $value;
            $parameters["tableFilters[{$name}][value]"] = $value;
        }

        if (filled($search)) {
            $parameters['tableSearch'] = trim($search);
        }

        return $this->getProkerResourceIndexUrl($parameters);
    }

    protected function getProkerResourceIndexUrl(array $parameters = []): string
    {
        return ProkerResource::canAccess()
            ? ProkerResource::getUrl('index', $parameters)
            : static::getUrl();
    }

    protected function getProkerBidangIndexUrl(array $parameters = []): string
    {
        return ProkerBidangResource::canAccess()
            ? ProkerBidangResource::getUrl('index', $parameters)
            : static::getUrl();
    }

    protected function getProkerEditUrl(?Proker $record): string
    {
        if (! $record || ! ProkerResource::canEdit($record)) {
            return $this->getProkerIndexUrl();
        }

        return ProkerResource::getUrl('edit', ['record' => $record]);
    }

    public function getQuickFilterChips(): array
    {
        if ($this->shouldDeferDashboardData()) {
            return [
                [
                    'label' => 'Terkendala',
                    'count' => '...',
                    'tone' => 'danger',
                    'icon' => 'heroicon-o-exclamation-triangle',
                    'hint' => 'Memuat proker terkendala...',
                    'action_label' => 'Buka filter',
                    'url' => $this->getProkerIndexUrl(),
                ],
                [
                    'label' => 'Tanpa Tanggal',
                    'count' => '...',
                    'tone' => 'warning',
                    'icon' => 'heroicon-o-calendar-days',
                    'hint' => 'Memuat proker tanpa target...',
                    'action_label' => 'Buka filter',
                    'url' => $this->getProkerIndexUrl(),
                ],
                [
                    'label' => 'Lewat Target',
                    'count' => '...',
                    'tone' => 'danger',
                    'icon' => 'heroicon-o-clock',
                    'hint' => 'Memuat proker lewat target...',
                    'action_label' => 'Buka filter',
                    'url' => $this->getProkerIndexUrl(),
                ],
                [
                    'label' => 'Belum Ada Update',
                    'count' => '...',
                    'tone' => 'warning',
                    'icon' => 'heroicon-o-document-minus',
                    'hint' => 'Memuat proker tanpa monitoring...',
                    'action_label' => 'Buka filter',
                    'url' => $this->getProkerIndexUrl(),
                ],
            ];
        }

        if ($this->quickFilterChipsCache !== null) {
            return $this->quickFilterChipsCache;
        }

        $metrics = $this->getDashboardMetrics();

        return $this->quickFilterChipsCache = [
            [
                'label' => 'Terkendala',
                'count' => (string) $metrics['terkendala'],
                'tone' => $metrics['terkendala'] > 0 ? 'danger' : 'success',
                'icon' => 'heroicon-o-exclamation-triangle',
                'hint' => $metrics['terkendala'] > 0
                    ? "{$metrics['terkendala']} proker perlu penanganan segera."
                    : 'Tidak ada proker terkendala saat ini.',
                'action_label' => 'Lihat terkendala',
                'url' => $this->getProkerIndexUrl(['status' => 'terkendala']),
            ],
            [
                'label' => 'Tanpa Tanggal',
                'count' => (string) $metrics['missing_target_count'],
                'tone' => $metrics['missing_target_count'] > 0 ? 'warning' : 'success',
                'icon' => 'heroicon-o-calendar-days',
                'hint' => $metrics['missing_target_count'] > 0
                    ? "{$metrics['missing_target_count']} proker belum punya target selesai."
                    : 'Semua proker sudah punya target selesai.',
                'action_label' => 'Lihat tanpa target',
                'url' => $this->getProkerIndexUrl(['target_status' => 'missing']),
            ],
            [
                'label' => 'Lewat Target',
                'count' => (string) $metrics['overdue_count'],
                'tone' => $metrics['overdue_count'] > 0 ? 'danger' : 'success',
                'icon' => 'heroicon-o-clock',
                'hint' => $metrics['overdue_count'] > 0
                    ? "{$metrics['overdue_count']} proker sudah melewati target selesai."
                    : 'Belum ada proker yang melewati target selesai.',
                'action_label' => 'Lihat lewat target',
                'url' => $this->getProkerIndexUrl(['target_status' => 'overdue']),
            ],
            [
                'label' => 'Belum Ada Update',
                'count' => (string) $metrics['no_update_count'],
                'tone' => $metrics['no_update_count'] > 0 ? 'warning' : 'success',
                'icon' => 'heroicon-o-document-minus',
                'hint' => $metrics['no_update_count'] > 0
                    ? "{$metrics['no_update_count']} proker belum pernah punya catatan monitoring."
                    : 'Semua proker sudah punya minimal satu catatan monitoring.',
                'action_label' => 'Lihat tanpa update',
                'url' => $this->getProkerIndexUrl(['monitoring_status' => 'no_update']),
            ],
        ];
    }

    public function getAnalysisItems(): array
    {
        if ($this->shouldDeferDashboardData()) {
            return [
                [
                    'label' => 'Capaian Proker',
                    'value' => '...',
                    'description' => 'Memuat ringkasan proker...',
                    'tone' => 'gray',
                    'action_label' => 'Buka detail',
                    'url' => $this->getProkerIndexUrl(),
                ],
                [
                    'label' => 'Indikator Berjalan',
                    'value' => '...',
                    'description' => 'Memuat ringkasan indikator...',
                    'tone' => 'gray',
                    'action_label' => 'Buka detail',
                    'url' => $this->getProkerIndexUrl(),
                ],
                [
                    'label' => 'Perlu Perhatian',
                    'value' => '...',
                    'description' => 'Memuat status proker...',
                    'tone' => 'gray',
                    'action_label' => 'Buka detail',
                    'url' => $this->getProkerIndexUrl(),
                ],
                [
                    'label' => 'Update Terlambat',
                    'value' => '...',
                    'description' => 'Memuat histori monitoring...',
                    'tone' => 'gray',
                    'action_label' => 'Buka detail',
                    'url' => $this->getProkerIndexUrl(),
                ],
                [
                    'label' => 'Tanggal Target',
                    'value' => '...',
                    'description' => 'Memuat kelengkapan target selesai...',
                    'tone' => 'gray',
                    'action_label' => 'Buka detail',
                    'url' => $this->getProkerIndexUrl(),
                ],
                [
                    'label' => 'Bidang Terkuat',
                    'value' => '...',
                    'description' => 'Memuat analisa bidang...',
                    'tone' => 'gray',
                    'action_label' => 'Buka detail',
                    'url' => $this->getProkerBidangIndexUrl(),
                ],
            ];
        }

        if ($this->analysisItemsCache !== null) {
            return $this->analysisItemsCache;
        }

        $metrics = $this->getDashboardMetrics();
        $totalProkers = $metrics['total_prokers'];
        $selesai = $metrics['selesai'];
        $terkendala = $metrics['terkendala'];
        $staleCount = $metrics['stale_count'];
        $missingTargetCount = $metrics['missing_target_count'];
        $totalIndikator = $metrics['total_indikator'];
        $indikatorSelesai = $metrics['indikator_selesai'];
        $indikatorRate = $metrics['indikator_rate'];
        $topBidang = $metrics['top_bidang'];

        return $this->analysisItemsCache = [
            [
                'label' => 'Capaian Proker',
                'value' => $totalProkers === 0 ? '0' : "{$selesai}/{$totalProkers}",
                'description' => $totalProkers === 0
                    ? 'Belum ada proker yang tercatat.'
                    : "{$selesai} proker selesai dari {$totalProkers} proker aktif.",
                'tone' => $totalProkers === 0 ? 'gray' : ($selesai === $totalProkers ? 'success' : 'warning'),
                'action_label' => 'Lihat proker',
                'url' => $this->getProkerIndexUrl(['status' => 'selesai']),
            ],
            [
                'label' => 'Indikator Berjalan',
                'value' => "{$indikatorRate}%",
                'description' => $totalIndikator === 0
                    ? 'Belum ada indikator yang tercatat.'
                    : "{$indikatorSelesai} dari {$totalIndikator} indikator sudah diceklis.",
                'tone' => $totalIndikator === 0 ? 'gray' : ($indikatorRate >= 75 ? 'success' : ($indikatorRate >= 40 ? 'warning' : 'danger')),
                'action_label' => 'Lihat indikator',
                'url' => $this->getProkerIndexUrl(),
            ],
            [
                'label' => 'Perlu Perhatian',
                'value' => (string) $terkendala,
                'description' => $terkendala === 0
                    ? 'Belum ada proker berstatus terkendala.'
                    : "{$terkendala} proker saat ini berstatus terkendala.",
                'tone' => $terkendala > 0 ? 'danger' : 'success',
                'action_label' => 'Lihat prioritas',
                'url' => $this->getProkerIndexUrl(['status' => 'terkendala']),
            ],
            [
                'label' => 'Update Terlambat',
                'value' => (string) $staleCount,
                'description' => $staleCount === 0
                    ? 'Semua proker punya update dalam 30 hari terakhir.'
                    : "{$staleCount} proker belum punya update 30 hari terakhir.",
                'tone' => $staleCount > 0 ? 'danger' : 'success',
                'action_label' => 'Lihat monitoring',
                'url' => $this->getProkerIndexUrl(),
            ],
            [
                'label' => 'Tanggal Target',
                'value' => $missingTargetCount === 0 ? 'Lengkap' : (string) $missingTargetCount,
                'description' => $missingTargetCount === 0
                    ? 'Semua proker sudah memiliki target selesai.'
                    : "{$missingTargetCount} proker belum memiliki tanggal target selesai.",
                'tone' => $missingTargetCount > 0 ? 'warning' : 'success',
                'action_label' => $missingTargetCount > 0 ? 'Filter proker' : 'Lihat daftar',
                'url' => $missingTargetCount > 0
                    ? $this->getProkerIndexUrl(['target_status' => 'missing'])
                    : $this->getProkerIndexUrl(),
            ],
            [
                'label' => 'Bidang Terkuat',
                'value' => $topBidang['bidang'] ?? '-',
                'description' => $topBidang
                    ? "Capaian indikator {$topBidang['persen_indikator']}% dengan rata-rata progress {$topBidang['avg_progress']}%."
                    : 'Belum ada data indikator per bidang untuk dianalisis.',
                'tone' => $topBidang ? 'primary' : 'gray',
                'action_label' => 'Buka bidang',
                'url' => $topBidang
                    ? $this->getProkerIndexUrl(['bidang' => $topBidang['bidang_id']])
                    : $this->getProkerIndexUrl(),
            ],
        ];
    }

    public function getDecisionRecommendations(): array
    {
        if ($this->shouldDeferDashboardData()) {
            return [
                [
                    'label' => 'Fokus hari ini',
                    'value' => '...',
                    'summary' => 'Memuat prioritas tindak lanjut...',
                    'tone' => 'gray',
                    'action_label' => 'Buka detail',
                    'url' => $this->getProkerIndexUrl(),
                ],
                [
                    'label' => 'Bidang yang perlu dorongan',
                    'value' => '...',
                    'summary' => 'Memuat perbandingan bidang...',
                    'tone' => 'gray',
                    'action_label' => 'Buka detail',
                    'url' => $this->getProkerIndexUrl(),
                ],
                [
                    'label' => 'Ritme monitoring',
                    'value' => '...',
                    'summary' => 'Memuat kedisiplinan update...',
                    'tone' => 'gray',
                    'action_label' => 'Buka detail',
                    'url' => $this->getProkerIndexUrl(),
                ],
                [
                    'label' => 'Kelengkapan tanggal target',
                    'value' => '...',
                    'summary' => 'Memuat proker yang belum punya target selesai...',
                    'tone' => 'gray',
                    'action_label' => 'Buka detail',
                    'url' => $this->getProkerIndexUrl(),
                ],
            ];
        }

        if ($this->decisionRecommendationsCache !== null) {
            return $this->decisionRecommendationsCache;
        }

        $metrics = $this->getDashboardMetrics();
        $attentionProkers = $this->getAttentionProkers();
        $topPriority = $attentionProkers->first();
        $weakBidang = $metrics['weak_bidang'];
        $missingTargetCount = $metrics['missing_target_count'];
        $latestUpdate = $this->getRecentUpdates()->first();
        $latestUpdateDate = $latestUpdate?->tanggal_update instanceof Carbon
            ? $latestUpdate->tanggal_update->translatedFormat('d M Y')
            : null;

        return $this->decisionRecommendationsCache = [
            [
                'label' => 'Fokus hari ini',
                'value' => $topPriority?->nama ?? 'Stabil',
                'summary' => $topPriority
                    ? ($topPriority->attention_summary ?: 'Proker ini paling layak dibuka lebih dulu untuk tindak lanjut.')
                    : 'Belum ada proker prioritas tinggi. Tim bisa lanjut ke monitoring rutin dan penyelesaian backlog.',
                'tone' => match ($topPriority?->attention_level) {
                    'tinggi' => 'danger',
                    'sedang' => 'warning',
                    'rendah' => 'primary',
                    default => 'success',
                },
                'action_label' => $topPriority ? 'Buka proker' : 'Lihat daftar',
                'url' => $topPriority
                    ? $this->getProkerEditUrl($topPriority)
                    : $this->getProkerIndexUrl(),
            ],
            [
                'label' => 'Bidang yang perlu dorongan',
                'value' => $weakBidang['bidang'] ?? 'Belum ada data',
                'summary' => $weakBidang
                    ? "Checklist {$weakBidang['persen_indikator']}%, rata-rata progress {$weakBidang['avg_progress']}%, {$weakBidang['terkendala_count']} proker terkendala."
                    : 'Belum ada indikator yang cukup untuk membandingkan performa bidang.',
                'tone' => ! $weakBidang
                    ? 'gray'
                    : ($weakBidang['persen_indikator'] < 50 || $weakBidang['terkendala_count'] > 0 ? 'warning' : 'success'),
                'action_label' => 'Buka bidang',
                'url' => $weakBidang
                    ? $this->getProkerIndexUrl(['bidang' => $weakBidang['bidang_id']])
                    : $this->getProkerBidangIndexUrl(),
            ],
            [
                'label' => 'Ritme monitoring',
                'value' => $metrics['stale_count'] === 0 ? 'Sehat' : "{$metrics['stale_count']} tertunda",
                'summary' => $metrics['stale_count'] === 0
                    ? ($latestUpdateDate
                        ? "Update terbaru tercatat {$latestUpdateDate} dan seluruh proker masih dalam siklus monitoring yang aman."
                        : 'Belum ada histori update terbaru, tetapi belum ada keterlambatan monitoring yang terdeteksi.')
                    : "{$metrics['stale_count']} proker belum punya update 30 hari terakhir".($metrics['overdue_count'] > 0
                        ? " dan {$metrics['overdue_count']} sudah melewati target selesai."
                        : '.'),
                'tone' => $metrics['stale_count'] > 0 || $metrics['overdue_count'] > 0 ? 'danger' : 'success',
                'action_label' => 'Lihat monitoring',
                'url' => $this->getProkerIndexUrl(),
            ],
            [
                'label' => 'Kelengkapan tanggal target',
                'value' => $missingTargetCount === 0 ? 'Lengkap' : "{$missingTargetCount} belum ada",
                'summary' => $missingTargetCount === 0
                    ? 'Semua proker sudah memiliki target selesai, sehingga prioritas dan monitoring lebih mudah dibaca.'
                    : "{$missingTargetCount} proker belum memiliki target selesai. Lengkapi tanggalnya agar keputusan monitoring dan prioritas lebih presisi.",
                'tone' => $missingTargetCount > 0 ? 'warning' : 'success',
                'action_label' => $missingTargetCount > 0 ? 'Filter proker' : 'Lihat daftar',
                'url' => $missingTargetCount > 0
                    ? $this->getProkerIndexUrl(['target_status' => 'missing'])
                    : $this->getProkerIndexUrl(),
            ],
        ];
    }

    public function getIndicatorPeriodOptions(): array
    {
        return ProkerResource::getPeriodYearOptions(withAll: true);
    }

    public function resetIndicatorFilters(): void
    {
        $this->indicatorPeriodYear = $this->resolveDefaultIndicatorPeriodYear();
        $this->indicatorProkerSearch = '';
    }

    public function resetQuickChecklistFilters(): void
    {
        $this->quickChecklistPeriodYear = $this->resolveDefaultIndicatorPeriodYear();
        $this->quickChecklistProkerSearch = '';
    }

    public function getIndicatorSummaryMeta(): array
    {
        if ($this->shouldDeferDashboardData()) {
            return [
                'matched_bidangs' => 0,
                'matched_prokers' => 0,
                'active_period_label' => $this->resolvePeriodLabel($this->indicatorPeriodYear),
            ];
        }

        $cacheKey = $this->makeFilterCacheKey($this->indicatorPeriodYear, $this->indicatorProkerSearch);

        if (array_key_exists($cacheKey, $this->indicatorSummaryMetaCache)) {
            return $this->indicatorSummaryMetaCache[$cacheKey];
        }

        $rows = collect($this->getIndicatorSummaryRows());

        return $this->indicatorSummaryMetaCache[$cacheKey] = [
            'matched_bidangs' => $rows->count(),
            'matched_prokers' => (int) $rows->sum('proker_count'),
            'active_period_label' => $this->resolvePeriodLabel($this->indicatorPeriodYear),
        ];
    }

    public function getQuickChecklistMeta(): array
    {
        if ($this->shouldDeferDashboardData()) {
            return [
                'matched_prokers' => 0,
                'active_period_label' => $this->resolvePeriodLabel($this->quickChecklistPeriodYear),
            ];
        }

        $cacheKey = $this->makeFilterCacheKey($this->quickChecklistPeriodYear, $this->quickChecklistProkerSearch);

        if (array_key_exists($cacheKey, $this->quickChecklistMetaCache)) {
            return $this->quickChecklistMetaCache[$cacheKey];
        }

        return $this->quickChecklistMetaCache[$cacheKey] = [
            'matched_prokers' => $this->applyQuickChecklistFilters(Proker::query())->count(),
            'active_period_label' => $this->resolvePeriodLabel($this->quickChecklistPeriodYear),
        ];
    }

    public function getIndicatorSummaryByBidang(bool $useFilters = true): Collection
    {
        if ($this->shouldDeferDashboardData()) {
            return collect();
        }

        return collect($this->getIndicatorSummaryRows($useFilters))
            ->map(function (array $row) use ($useFilters): array {
                $manageUrlParameters = [
                    'filters[bidang][value]' => $row['bidang_id'],
                    'tableFilters[bidang][value]' => $row['bidang_id'],
                ];

                if ($useFilters && filled($this->indicatorPeriodYear)) {
                    $manageUrlParameters['filters[periode_tahun][value]'] = (int) $this->indicatorPeriodYear;
                    $manageUrlParameters['tableFilters[periode_tahun][value]'] = (int) $this->indicatorPeriodYear;
                }

                if ($useFilters && filled($this->indicatorProkerSearch)) {
                    $manageUrlParameters['tableSearch'] = trim($this->indicatorProkerSearch);
                }

                return [
                    'bidang_id' => $row['bidang_id'],
                    'bidang' => $row['bidang'],
                    'proker_count' => $row['proker_count'],
                    'total_indikator' => $row['total_indikator'],
                    'indikator_selesai' => $row['indikator_selesai'],
                    'persen_indikator' => $row['persen_indikator'],
                    'avg_progress' => $row['avg_progress'],
                    'terkendala_count' => $row['terkendala_count'],
                    'manage_url' => $this->getProkerResourceIndexUrl($manageUrlParameters),
                ];
            });
    }

    protected function applyIndicatorSummaryFilters($query)
    {
        return $this->applyProkerPeriodAndSearchFilters(
            $query,
            $this->indicatorPeriodYear,
            $this->indicatorProkerSearch,
        );
    }

    protected function applyQuickChecklistFilters($query)
    {
        return $this->applyProkerPeriodAndSearchFilters(
            $query,
            $this->quickChecklistPeriodYear,
            $this->quickChecklistProkerSearch,
        );
    }

    protected function applyProkerPeriodAndSearchFilters($query, ?string $periodYear, string $search)
    {
        return $query
            ->when(
                filled($periodYear),
                fn (Builder $builder) => $builder->where('periode_tahun', (int) $periodYear)
            )
            ->when(
                filled($search),
                fn (Builder $builder) => $builder->where('nama', 'like', '%'.trim($search).'%')
            );
    }

    protected function resolveDefaultIndicatorPeriodYear(): ?string
    {
        $latestPeriod = Proker::query()->max('periode_tahun');

        return $latestPeriod ? (string) $latestPeriod : null;
    }

    protected function resolvePeriodLabel(?string $periodYear): string
    {
        if (! filled($periodYear)) {
            return 'Semua periode';
        }

        $periodOptions = $this->getIndicatorPeriodOptions();

        return $periodOptions[$periodYear] ?? $periodYear;
    }

    public function getRecentUpdates(): Collection
    {
        if ($this->shouldDeferDashboardData()) {
            return collect();
        }

        if ($this->recentUpdatesCache !== null) {
            return $this->recentUpdatesCache;
        }

        return $this->recentUpdatesCache = ProkerUpdate::query()
            ->select([
                'id',
                'proker_id',
                'created_by',
                'tanggal_update',
                'status_snapshot',
                'progress_persen',
                'ringkasan',
                'tindak_lanjut',
                'dokumentasi',
            ])
            ->with([
                'proker:id,bidang_id,nama',
                'proker.bidang:id,nama',
                'creator:id,name',
            ])
            ->orderByDesc('tanggal_update')
            ->orderByDesc('id')
            ->limit(8)
            ->get();
    }

    public function getQuickChecklistProkers(): Collection
    {
        if ($this->shouldDeferDashboardData()) {
            return collect();
        }

        $cacheKey = $this->makeFilterCacheKey($this->quickChecklistPeriodYear, $this->quickChecklistProkerSearch);

        if (array_key_exists($cacheKey, $this->quickChecklistProkersCache)) {
            return $this->quickChecklistProkersCache[$cacheKey];
        }

        return $this->quickChecklistProkersCache[$cacheKey] = $this->applyQuickChecklistFilters(
            Proker::query()
                ->select([
                    'id',
                    'bidang_id',
                    'nama',
                    'status',
                    'progress_persen',
                    'point_dari',
                    'penanggung_jawab',
                    'target_selesai',
                    'jadwal_ringkas',
                    'waktu_pelaksanaan',
                    'rab_global',
                    'periode_tahun',
                ])
                ->with(['bidang:id,nama'])
                ->withCount('indikators')
                ->withCount([
                    'indikators as checked_indikators_count' => fn (Builder $query) => $query->where('is_checked', true),
                    'updates',
                ])
        )
            ->orderByRaw("CASE WHEN status = 'selesai' THEN 1 ELSE 0 END")
            ->orderByRaw('CASE WHEN target_selesai IS NULL THEN 1 ELSE 0 END')
            ->orderBy('target_selesai')
            ->orderByDesc('periode_tahun')
            ->limit(10)
            ->get();
    }

    public function getAttentionProkers(): Collection
    {
        if ($this->shouldDeferDashboardData()) {
            return collect();
        }

        if ($this->attentionProkersCache !== null) {
            return $this->attentionProkersCache;
        }

        $staleDate = now()->subDays(30);

        return $this->attentionProkersCache = Proker::query()
            ->select([
                'id',
                'bidang_id',
                'nama',
                'status',
                'progress_persen',
                'penanggung_jawab',
                'jadwal_ringkas',
                'target_selesai',
                'periode_tahun',
            ])
            ->with(['bidang:id,nama'])
            ->withCount('indikators')
            ->withCount([
                'indikators as checked_indikators_count' => fn ($query) => $query->where('is_checked', true),
                'updates',
            ])
            ->withMax('updates', 'tanggal_update')
            ->orderByRaw("case when status = 'terkendala' then 0 else 1 end")
            ->orderByRaw(
                'case when updates_max_tanggal_update is null or date(updates_max_tanggal_update) < ? then 0 else 1 end',
                [$staleDate->toDateString()]
            )
            ->orderByRaw('case when target_selesai is null then 1 else 0 end')
            ->orderBy('target_selesai')
            ->orderBy('progress_persen')
            ->limit(8)
            ->get()
            ->map(function (Proker $proker) use ($staleDate): Proker {
                $lastUpdateAt = blank($proker->updates_max_tanggal_update)
                    ? null
                    : Carbon::parse($proker->updates_max_tanggal_update);
                $indicatorCount = (int) ($proker->indikators_count ?? 0);
                $checkedIndicatorCount = (int) ($proker->checked_indikators_count ?? 0);
                $progress = (int) ($proker->progress_persen ?? 0);
                $reasons = [];

                if ($proker->status === 'terkendala') {
                    $reasons[] = 'Status terkendala';
                }

                if ($lastUpdateAt === null) {
                    $reasons[] = 'Belum ada update';
                } elseif ($lastUpdateAt->lt($staleDate)) {
                    $reasons[] = 'Update '.$lastUpdateAt->diffInDays(now()).' hari lalu';
                }

                if ($proker->target_selesai && $proker->status !== 'selesai' && $proker->target_selesai->isPast()) {
                    $reasons[] = 'Lewat target '.$proker->target_selesai->diffInDays(now()).' hari';
                }

                if ($indicatorCount > 0 && $checkedIndicatorCount === 0) {
                    $reasons[] = 'Checklist belum dimulai';
                } elseif ($indicatorCount > 0 && $checkedIndicatorCount < $indicatorCount) {
                    $reasons[] = 'Checklist belum penuh';
                }

                $attentionLevel = match (true) {
                    $proker->status === 'terkendala',
                    $lastUpdateAt === null,
                    ($lastUpdateAt !== null && $lastUpdateAt->lt($staleDate)),
                    ($proker->target_selesai && $proker->status !== 'selesai' && $proker->target_selesai->isPast()) => 'tinggi',
                    $progress < 60 || ($indicatorCount > 0 && $checkedIndicatorCount < $indicatorCount) => 'sedang',
                    default => 'rendah',
                };

                $proker->attention_level = $attentionLevel;
                $proker->attention_summary = collect($reasons)
                    ->filter()
                    ->take(3)
                    ->implode(' | ') ?: 'Perlu review manual.';
                $proker->last_update_label = $lastUpdateAt?->translatedFormat('d M Y') ?? 'Belum ada update';

                return $proker;
            });
    }

    public function getSummaryText(): string
    {
        if ($this->shouldDeferDashboardData()) {
            return 'Memuat ringkasan dashboard proker, indikator, dan histori monitoring...';
        }

        if ($this->summaryTextCache !== null) {
            return $this->summaryTextCache;
        }

        $recentUpdates = $this->getRecentUpdates();

        if ($recentUpdates->isEmpty()) {
            $metrics = $this->getDashboardMetrics();

            return $this->summaryTextCache = $metrics['missing_target_count'] > 0
                ? "Belum ada histori monitoring. Saat ini {$metrics['missing_target_count']} proker juga belum memiliki target selesai."
                : 'Belum ada histori monitoring. Mulai dari membuat proker, menambah indikator, lalu catat update berkala.';
        }

        $lastUpdate = $recentUpdates->first();
        $date = $lastUpdate->tanggal_update instanceof Carbon
            ? $lastUpdate->tanggal_update->translatedFormat('d M Y')
            : (string) $lastUpdate->tanggal_update;
        $metrics = $this->getDashboardMetrics();

        return $this->summaryTextCache = $metrics['missing_target_count'] > 0
            ? "Update terbaru tercatat pada {$date}. Dashboard ini juga mendeteksi {$metrics['missing_target_count']} proker tanpa target selesai agar segera dirapikan."
            : "Update terbaru tercatat pada {$date}. Gunakan dashboard ini untuk membaca kesehatan proker dari checklist indikator, distribusi status, dan histori tindak lanjut.";
    }

    /**
     * @return array<int, array<string, int|string>>
     */
    protected function getIndicatorSummaryRows(bool $useFilters = true): array
    {
        $cacheKey = $useFilters
            ? $this->makeFilterCacheKey($this->indicatorPeriodYear, $this->indicatorProkerSearch)
            : 'all';

        if (array_key_exists($cacheKey, $this->indicatorSummaryRowsCache)) {
            return $this->indicatorSummaryRowsCache[$cacheKey];
        }

        $search = trim($this->indicatorProkerSearch);

        $query = ProkerBidang::query()
            ->leftJoin('prokers', function (JoinClause $join) use ($useFilters, $search): void {
                $join->on('proker_bidangs.id', '=', 'prokers.bidang_id');

                if ($useFilters && filled($this->indicatorPeriodYear)) {
                    $join->where('prokers.periode_tahun', '=', (int) $this->indicatorPeriodYear);
                }

                if ($useFilters && $search !== '') {
                    $join->where('prokers.nama', 'like', '%'.$search.'%');
                }
            })
            ->leftJoin('proker_indikators', 'prokers.id', '=', 'proker_indikators.proker_id')
            ->selectRaw('proker_bidangs.id as bidang_id')
            ->selectRaw('proker_bidangs.nama as bidang')
            ->selectRaw('count(distinct prokers.id) as proker_count')
            ->selectRaw('count(proker_indikators.id) as total_indikator')
            ->selectRaw('sum(case when proker_indikators.is_checked = 1 then 1 else 0 end) as indikator_selesai')
            ->selectRaw('coalesce(round(avg(case when prokers.id is not null then prokers.progress_persen end)), 0) as avg_progress')
            ->selectRaw("sum(case when prokers.status = 'terkendala' then 1 else 0 end) as terkendala_count")
            ->groupBy('proker_bidangs.id', 'proker_bidangs.nama')
            ->orderBy('proker_bidangs.nama');

        if ($useFilters) {
            $query->havingRaw('count(distinct prokers.id) > 0');
        }

        return $this->indicatorSummaryRowsCache[$cacheKey] = $query
            ->get()
            ->map(fn (object $row): array => [
                'bidang_id' => (int) $row->bidang_id,
                'bidang' => (string) $row->bidang,
                'proker_count' => (int) $row->proker_count,
                'total_indikator' => (int) $row->total_indikator,
                'indikator_selesai' => (int) ($row->indikator_selesai ?? 0),
                'persen_indikator' => (int) ($row->total_indikator > 0
                    ? round(((int) ($row->indikator_selesai ?? 0) / (int) $row->total_indikator) * 100)
                    : 0),
                'avg_progress' => (int) $row->avg_progress,
                'terkendala_count' => (int) ($row->terkendala_count ?? 0),
            ])
            ->all();
    }

    protected function makeFilterCacheKey(?string $periodYear, string $search): string
    {
        return ($periodYear ?: 'all').'|'.mb_strtolower(trim($search));
    }

    /**
     * @return array<string, mixed>
     */
    protected function getDashboardMetrics(): array
    {
        if ($this->dashboardMetricsCache !== null) {
            return $this->dashboardMetricsCache;
        }

        $statusCounts = Proker::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $indikatorSummary = ProkerIndikator::query()
            ->selectRaw('count(*) as total_indikator')
            ->selectRaw('sum(case when is_checked = 1 then 1 else 0 end) as indikator_selesai')
            ->first();

        $staleCount = Proker::query()
            ->leftJoin('proker_updates as latest_updates', function (JoinClause $join): void {
                $join->on('latest_updates.proker_id', '=', 'prokers.id')
                    ->whereRaw(
                        'latest_updates.id = (select max(inner_updates.id) from proker_updates as inner_updates where inner_updates.proker_id = prokers.id)'
                    );
            })
            ->where(function (Builder $query): void {
                $query
                    ->whereNull('latest_updates.tanggal_update')
                    ->orWhereDate('latest_updates.tanggal_update', '<', now()->subDays(30)->toDateString());
            })
            ->count('prokers.id');

        $overdueCount = Proker::query()
            ->whereNotNull('target_selesai')
            ->whereDate('target_selesai', '<', now()->toDateString())
            ->where('status', '!=', 'selesai')
            ->count();

        $missingTargetCount = Proker::query()
            ->whereNull('target_selesai')
            ->count();

        $noUpdateCount = Proker::query()
            ->doesntHave('updates')
            ->count();

        $bidangRows = collect($this->getIndicatorSummaryRows(false))
            ->filter(fn (array $row): bool => $row['total_indikator'] > 0);

        $topBidang = $bidangRows
            ->sort(function (array $left, array $right): int {
                return [$right['persen_indikator'], $right['avg_progress'], -$right['terkendala_count']]
                    <=> [$left['persen_indikator'], $left['avg_progress'], -$left['terkendala_count']];
            })
            ->first();

        $weakBidang = $bidangRows
            ->sort(function (array $left, array $right): int {
                return [$left['persen_indikator'], $left['avg_progress'], -$left['terkendala_count']]
                    <=> [$right['persen_indikator'], $right['avg_progress'], -$right['terkendala_count']];
            })
            ->first();

        $totalProkers = (int) $statusCounts->sum();
        $totalIndikator = (int) ($indikatorSummary?->total_indikator ?? 0);
        $indikatorSelesai = (int) ($indikatorSummary?->indikator_selesai ?? 0);

        return $this->dashboardMetricsCache = [
            'total_prokers' => $totalProkers,
            'selesai' => (int) ($statusCounts['selesai'] ?? 0),
            'terkendala' => (int) ($statusCounts['terkendala'] ?? 0),
            'stale_count' => $staleCount,
            'overdue_count' => $overdueCount,
            'missing_target_count' => $missingTargetCount,
            'no_update_count' => $noUpdateCount,
            'total_indikator' => $totalIndikator,
            'indikator_selesai' => $indikatorSelesai,
            'indikator_rate' => $totalIndikator > 0 ? (int) round(($indikatorSelesai / $totalIndikator) * 100) : 0,
            'top_bidang' => $topBidang,
            'weak_bidang' => $weakBidang,
        ];
    }

    protected function shouldDeferDashboardData(): bool
    {
        return ! $this->dashboardDataReady;
    }

    public function isDegradedDashboardMode(): bool
    {
        return EndpointProtectionPolicy::shouldSkipExpensiveAdminDashboardWidgets();
    }

    protected static function hasRequiredTables(): bool
    {
        return static::$requiredTablesAvailable ??= SchemaFacade::hasTable('proker_bidangs')
            && SchemaFacade::hasTable('prokers')
            && SchemaFacade::hasTable('proker_indikators')
            && SchemaFacade::hasTable('proker_updates');
    }

    protected static function userCanModule(string $ability): bool
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return false;
        }

        $user->loadMissing('roles');

        if ($user->hasRole('admin')) {
            return true;
        }

        $prefix = static::$permissionPrefix;

        if (blank($prefix)) {
            return false;
        }

        if ($ability === 'view') {
            return $user->canViewModule($prefix);
        }

        return $user->canManageModule($prefix);
    }

    public function downloadTemplateAction(): Action
    {
        return Action::make('downloadTemplate')
            ->label('Download Template')
            ->icon('heroicon-o-arrow-down-tray')
            ->color('gray')
            ->url(route('admin.prokers.import-template'))
            ->openUrlInNewTab();
    }

    public function downloadProkerExcelAction(): Action|ActionGroup
    {
        $periodActions = $this->getDownloadProkerExcelActions();

        if ($periodActions === []) {
            return Action::make('downloadProkerExcel')
                ->label('Download Excel')
                ->icon('heroicon-o-document-arrow-down')
                ->color('gray')
                ->disabled();
        }

        return ActionGroup::make($periodActions)
            ->label('Download Excel')
            ->icon('heroicon-o-document-arrow-down')
            ->color('success')
            ->button()
            ->dropdownPlacement('bottom-start');
    }

    /**
     * @return array<int, Action>
     */
    protected function getDownloadProkerExcelActions(): array
    {
        return collect(ProkerResource::getPeriodYearOptions())
            ->map(
                fn (string $label, string $year): Action => Action::make("downloadProkerExcel{$year}")
                    ->label($label)
                    ->icon('heroicon-o-arrow-down-tray')
                    ->url(route('admin.prokers.export', ['periode_tahun' => (int) $year]))
                    ->openUrlInNewTab()
            )
            ->values()
            ->all();
    }

    public function importProkerAction(): Action
    {
        return Action::make('importProker')
            ->label('Import Proker')
            ->icon('heroicon-o-arrow-up-tray')
            ->hidden(fn (): bool => ! static::userCanModule('manage'))
            ->modalHeading('Import Proker')
            ->modalSubmitActionLabel('Kirim File')
            ->modalWidth('2xl')
            ->form([
                Forms\Components\Placeholder::make('download_format')
                    ->label('Download Format')
                    ->content(new HtmlString('<a href="'.route('admin.prokers.import-template').'" target="_blank" class="text-primary-600 font-semibold underline">Download format import Proker</a>')),
                Forms\Components\FileUpload::make('berkas')
                    ->label('Kirim File')
                    ->disk('public')
                    ->directory('proker/imports')
                    ->acceptedFileTypes([
                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        'application/vnd.ms-excel',
                    ])
                    ->helperText('Mendukung file matrix sekolah saat ini dan template standar aplikasi.')
                    ->required(),
                Forms\Components\Radio::make('sheet_mode')
                    ->label('Sheet yang Diimpor')
                    ->options([
                        'sheet:2025' => 'Sheet 2025 saja',
                        'sheet:2026' => 'Sheet 2026 saja (untuk file AFBS saat ini)',
                        'first' => 'Sheet pertama saja',
                        'all' => 'Semua sheet di workbook',
                    ])
                    ->helperText('Gunakan pilihan sheet spesifik agar import workbook AFBS lebih akurat.')
                    ->default('first')
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

                $fullPath = $disk->path($path);

                try {
                    $result = app(ProkerWorkbookImporter::class)->import(
                        $fullPath,
                        auth()->id(),
                        $data['sheet_mode'] ?? 'first',
                    );
                } catch (Throwable $exception) {
                    report($exception);

                    Notification::make()
                        ->title('Import proker gagal.')
                        ->body(filled($exception->getMessage()) ? $exception->getMessage() : 'Terjadi kesalahan saat membaca workbook.')
                        ->danger()
                        ->send();

                    return;
                } finally {
                    if ($disk->exists($path)) {
                        $disk->delete($path);
                    }
                }

                $sheetSummary = collect($result['sheets'])
                    ->map(fn (array $sheet): string => "{$sheet['sheet']} ({$sheet['rows']} baris)")
                    ->implode(', ');

                Notification::make()
                    ->title('Import proker selesai.')
                    ->body("{$result['created']} data baru, {$result['updated']} data diperbarui. Sheet: {$sheetSummary}")
                    ->success()
                    ->send();

                $this->flushDashboardCaches();
            });
    }

    public function quickUpdateAction(): Action
    {
        return Action::make('quickUpdate')
            ->label('Catat Update')
            ->icon('heroicon-o-pencil-square')
            ->color('gray')
            ->hidden(fn (): bool => ! static::userCanModule('manage'))
            ->modalWidth('2xl')
            ->modalHeading(fn (array $arguments): string => 'Catat Update: '.$this->resolveActionProker($arguments)->nama)
            ->fillForm(fn (array $arguments): array => $this->getMonitoringDefaultData($this->resolveActionProker($arguments), false))
            ->form($this->getMonitoringActionSchema(false))
            ->action(function (array $arguments, array $data): void {
                $this->persistMonitoringUpdate($arguments, $data, false);
            });
    }

    public function checklistTerlaksanaAction(): Action
    {
        return Action::make('checklistTerlaksana')
            ->label('Checklist Terlaksana')
            ->icon('heroicon-o-check-badge')
            ->color('success')
            ->hidden(fn (): bool => ! static::userCanModule('manage'))
            ->modalWidth('2xl')
            ->modalHeading(fn (array $arguments): string => 'Checklist Terlaksana: '.$this->resolveActionProker($arguments)->nama)
            ->fillForm(fn (array $arguments): array => $this->getMonitoringDefaultData($this->resolveActionProker($arguments), true))
            ->form($this->getMonitoringActionSchema(true))
            ->action(function (array $arguments, array $data): void {
                $this->persistMonitoringUpdate($arguments, $data, true);
            });
    }

    /**
     * @return array<int, Forms\Components\Component>
     */
    protected function getMonitoringActionSchema(bool $forCompletion): array
    {
        $schema = [
            Grid::make(['default' => 1, 'md' => 3])
                ->schema([
                    Forms\Components\DatePicker::make('tanggal_update')
                        ->label('Tanggal Update')
                        ->required(),
                    Forms\Components\Select::make('status_snapshot')
                        ->label('Status')
                        ->required()
                        ->options([
                            'draft' => 'Draft',
                            'berjalan' => 'Berjalan',
                            'terkendala' => 'Terkendala',
                            'selesai' => 'Selesai',
                        ]),
                    Forms\Components\TextInput::make('progress_persen')
                        ->label('Progress (%)')
                        ->numeric()
                        ->minValue(0)
                        ->maxValue(100),
                ]),
            Forms\Components\Textarea::make('ringkasan')
                ->label('Ringkasan Progress')
                ->rows(3),
            Forms\Components\Textarea::make('evaluasi')
                ->label('Keterangan / Evaluasi')
                ->rows(4),
            Forms\Components\Textarea::make('tindak_lanjut')
                ->label('Tindak Lanjut')
                ->rows(4),
            Forms\Components\FileUpload::make('dokumentasi')
                ->label('Bukti Dokumentasi')
                ->disk('public')
                ->directory('proker/updates')
                ->helperText('Dokumentasi sebelumnya tetap ditampilkan. Anda bisa menambah, mengurutkan, atau menghapus sebelum submit.')
                ->multiple()
                ->appendFiles()
                ->downloadable()
                ->openable()
                ->reorderable()
                ->panelLayout('grid')
                ->maxFiles(10)
                ->maxSize(4096),
        ];

        if ($forCompletion) {
            $schema[] = Forms\Components\Toggle::make('ceklis_semua_indikator')
                ->label('Ceklis semua indikator yang belum selesai')
                ->helperText('Aktifkan jika proker ini sudah final dan semua indikatornya juga harus selesai.');
        }

        return $schema;
    }

    /**
     * @return array<string, mixed>
     */
    protected function getMonitoringDefaultData(Proker $proker, bool $forCompletion): array
    {
        $latestUpdate = $this->resolveLatestMonitoringUpdate($proker);

        return [
            'tanggal_update' => now()->toDateString(),
            'status_snapshot' => $forCompletion ? 'selesai' : ($latestUpdate?->status_snapshot ?: $proker->status),
            'progress_persen' => $forCompletion ? 100 : (int) ($latestUpdate?->progress_persen ?? $proker->progress_persen),
            'ringkasan' => $latestUpdate?->ringkasan,
            'evaluasi' => $latestUpdate?->evaluasi,
            'tindak_lanjut' => $latestUpdate?->tindak_lanjut,
            'dokumentasi' => array_values(array_filter((array) ($latestUpdate?->dokumentasi ?? []))),
            'ceklis_semua_indikator' => $forCompletion && $proker->indikators()->exists(),
        ];
    }

    protected function resolveActionProker(array $arguments): Proker
    {
        return Proker::query()
            ->withCount('indikators')
            ->findOrFail($arguments['proker'] ?? null);
    }

    protected function resolveLatestMonitoringUpdate(Proker $proker): ?ProkerUpdate
    {
        return ProkerUpdate::query()
            ->where('proker_id', $proker->id)
            ->orderByDesc('tanggal_update')
            ->orderByDesc('id')
            ->first();
    }

    protected function persistMonitoringUpdate(array $arguments, array $data, bool $forCompletion): void
    {
        abort_unless(static::userCanModule('manage'), 403);

        $proker = $this->resolveActionProker($arguments);
        $latestUpdate = $this->resolveLatestMonitoringUpdate($proker);

        $ringkasan = $data['ringkasan'] ?? null;

        if (! filled($ringkasan) && $forCompletion && blank($latestUpdate?->ringkasan)) {
            $ringkasan = 'Proker ditandai terlaksana dari dashboard proker.';
        }

        $dokumentasi = $data['dokumentasi'] ?? ($latestUpdate?->dokumentasi ?? []);
        $dokumentasi = array_values(array_filter((array) $dokumentasi));

        $proker->recordMonitoringUpdate([
            'tanggal_update' => $data['tanggal_update'],
            'status_snapshot' => $data['status_snapshot'],
            'progress_persen' => $data['progress_persen'],
            'ringkasan' => $ringkasan,
            'evaluasi' => $data['evaluasi'] ?? null,
            'tindak_lanjut' => $data['tindak_lanjut'] ?? null,
            'dokumentasi' => $dokumentasi,
            'created_by' => auth()->id(),
        ], (bool) ($data['ceklis_semua_indikator'] ?? false));

        $this->flushDashboardCaches();

        Notification::make()
            ->title($forCompletion ? 'Proker ditandai terlaksana.' : 'Update proker tersimpan.')
            ->body("Perubahan untuk {$proker->nama} sudah masuk. Catatan dan dokumentasi terakhir tetap bisa dilanjutkan pada update berikutnya.")
            ->success()
            ->send();
    }

    protected function flushDashboardCaches(): void
    {
        $this->analysisItemsCache = null;
        $this->dashboardMetricsCache = null;
        $this->decisionRecommendationsCache = null;
        $this->quickFilterChipsCache = null;
        $this->summaryTextCache = null;
        $this->recentUpdatesCache = null;
        $this->attentionProkersCache = null;
        $this->indicatorSummaryRowsCache = [];
        $this->indicatorSummaryMetaCache = [];
        $this->quickChecklistMetaCache = [];
        $this->quickChecklistProkersCache = [];
    }
}
