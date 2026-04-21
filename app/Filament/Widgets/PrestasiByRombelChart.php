<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\PrestasiResource;
use App\Support\Admin\Dashboard\PrestasiDashboardSupport;

class PrestasiByRombelChart extends InteractiveBarChartWidget
{
    protected ?string $heading = 'Analisa Prestasi per Rombel';

    protected int|string|array $columnSpan = 'full';

    /**
     * @param  array<string, mixed>|object  $row
     */
    protected function rowValue(array|object $row, string $key): mixed
    {
        return data_get($row, $key);
    }

    protected function getData(): array
    {
        $rows = collect(PrestasiDashboardSupport::snapshot(auth()->user())['by_rombel'] ?? []);

        return [
            'datasets' => [
                [
                    'label' => 'Total Prestasi',
                    'data' => $rows->pluck('total_prestasi')->map(fn ($value): int => (int) $value)->all(),
                    'backgroundColor' => '#f59e0b',
                    'borderRadius' => 8,
                    'pointDetails' => $rows->map(fn ($row): array => [
                        'label' => 'Prestasi - '.($this->rowValue($row, 'rombel_saat_ini') ?: '-'),
                        'count' => (int) $this->rowValue($row, 'total_prestasi'),
                        'countLabel' => number_format((int) $this->rowValue($row, 'total_prestasi'), 0, ',', '.').' prestasi',
                        'shortDescription' => 'Klik untuk melihat prestasi pada rombel ini.',
                        'description' => 'Daftar prestasi akan difilter ke rombel yang dipilih.',
                        'filterLabel' => 'Rombel: '.($this->rowValue($row, 'rombel_saat_ini') ?: '-'),
                        'url' => PrestasiResource::getUrl('index', [
                            'tableFilters[rombel][value]' => $this->rowValue($row, 'rombel_saat_ini'),
                        ]),
                    ])->all(),
                ],
            ],
            'labels' => $rows->pluck('rombel_saat_ini')->all(),
        ];
    }
}
