<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\PerpustakaanLiterasiMaterialResource;
use App\Models\PerpustakaanLiterasiMaterial;
use App\Support\Perpustakaan\LiterasiAnalytics;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Schema;

class PerpustakaanLiterasiGlobalAnalytics extends Widget
{
    protected string $view = 'filament.widgets.perpustakaan-literasi-global-analytics';

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    protected ?string $pollingInterval = null;

    public string $activeAnalyticsTab = 'all';

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
        ];
    }

    public function selectAnalyticsTab(string $tab): void
    {
        if (array_key_exists($tab, $this->analyticsTabs())) {
            $this->activeAnalyticsTab = $tab;
        }
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
