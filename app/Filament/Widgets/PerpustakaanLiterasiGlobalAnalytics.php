<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\PerpustakaanLiterasiMaterialResource;
use App\Models\PerpustakaanLiterasiMaterial;
use App\Support\Perpustakaan\LiteracyConnectivityAnalytics;
use App\Support\Perpustakaan\LiteracyMonthlyShareText;
use App\Support\Perpustakaan\LiteracyOperationalHealth;
use App\Support\Perpustakaan\LiterasiAnalytics;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class PerpustakaanLiterasiGlobalAnalytics extends Widget
{
    protected string $view = 'filament.widgets.perpustakaan-literasi-global-analytics';

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    protected ?string $pollingInterval = null;

    public string $activeAnalyticsTab = 'all';

    public ?string $monthlyShareText = null;

    public ?string $monthlyShareTitle = null;

    public string $connectivityDate = '';

    public string $connectivityFrom = '06:00';

    public string $connectivityTo = '10:00';

    public function mount(): void
    {
        $this->connectivityDate = now()->format('Y-m-d');
    }

    public static function canView(): bool
    {
        return PerpustakaanLiterasiMaterialResource::canViewAny()
            && Schema::hasTable('perpustakaan_literasi_materials')
            && Schema::hasTable('perpustakaan_literasi_responses')
            && Schema::hasTable('perpustakaan_literasi_answers')
            && Schema::hasTable('perpustakaan_literasi_similarity_matches');
    }

    protected function getViewData(): array
    {
        $tabs = $this->analyticsTabs();
        $activeTab = array_key_exists($this->activeAnalyticsTab, $tabs)
            ? $this->activeAnalyticsTab
            : 'all';

        return [
            'analyticsTabs' => $tabs,
            'activeAnalyticsTab' => $activeTab,
            'analytics' => $activeTab === 'all'
                ? LiterasiAnalytics::global()
                : LiterasiAnalytics::forProgramCategory($activeTab),
            'analyticsTitle' => $activeTab === 'all'
                ? 'Keseluruhan Soal Selama 1 Bulan'
                : $tabs[$activeTab].' Selama 1 Bulan',
            'analyticsDescription' => $activeTab === 'all'
                ? 'Rekap semua materi Literasi Numerasi pada bulan berjalan.'
                : 'Rekap bulan berjalan khusus kategori '.$tabs[$activeTab].'.',
            'operationalHealth' => app(LiteracyOperationalHealth::class)->snapshot(),
            'monthlyShareScopes' => LiteracyMonthlyShareText::scopeOptions(),
            'connectivity' => app(LiteracyConnectivityAnalytics::class)->snapshot(
                $this->connectivityDate ?: now()->format('Y-m-d'),
                $this->connectivityFrom,
                $this->connectivityTo,
            ),
        ];
    }

    public function refreshConnectivity(): void
    {
        abort_unless(static::canView(), 403);

        $this->validate([
            'connectivityDate' => ['required', 'date_format:Y-m-d'],
            'connectivityFrom' => ['required', 'date_format:H:i'],
            'connectivityTo' => ['required', 'date_format:H:i', 'after_or_equal:connectivityFrom'],
        ], [
            'connectivityTo.after_or_equal' => 'Jam akhir harus sama atau lebih besar daripada jam awal.',
        ]);
    }

    public function selectAnalyticsTab(string $tab): void
    {
        if (array_key_exists($tab, $this->analyticsTabs())) {
            $this->activeAnalyticsTab = $tab;
        }
    }

    public function prepareMonthlyShare(string $scope): void
    {
        abort_unless(static::canView(), 403);

        if (! LiteracyMonthlyShareText::validScope($scope)) {
            throw ValidationException::withMessages([
                'monthlyShareScope' => 'Lingkup rekap bulanan tidak valid.',
            ]);
        }

        $this->monthlyShareTitle = LiteracyMonthlyShareText::title($scope);
        $this->monthlyShareText = LiteracyMonthlyShareText::make($scope);
        $this->dispatch('open-modal', id: 'literacy-monthly-share-preview');
    }

    protected function analyticsTabs(): array
    {
        return [
            'all' => 'Keseluruhan',
            PerpustakaanLiterasiMaterial::CATEGORY_LITERACY_HABITUATION => 'Literacy Habituation',
            PerpustakaanLiterasiMaterial::CATEGORY_NUMERACY_EXCELLENCE => 'Numeracy Excellence',
            PerpustakaanLiterasiMaterial::CATEGORY_SIGAP_29_KARAKTER => 'SIGAP 29 Karakter',
            '__blank' => PerpustakaanLiterasiMaterial::uncategorizedProgramLabel(),
        ];
    }
}
