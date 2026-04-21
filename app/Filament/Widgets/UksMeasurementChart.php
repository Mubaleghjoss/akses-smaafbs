<?php

namespace App\Filament\Widgets;

use App\Support\Uks\UksAnthropometrySupport;
use App\Support\Admin\Dashboard\UksDashboardSupport;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Collection;

class UksMeasurementChart extends ChartWidget
{
    protected ?string $heading = 'Rerata Berat, Tinggi, dan Lingkar Kepala';

    protected int|string|array $columnSpan = [
        'default' => 'full',
        'md' => 2,
    ];

    protected ?string $pollingInterval = null;

    protected function getData(): array
    {
        $measurementAverages = UksDashboardSupport::snapshot(auth()->user())['measurement_averages'];

        return [
            'datasets' => [[
                'label' => 'Rata-rata Murid Aktif',
                'data' => [
                    (float) ($measurementAverages['berat_badan'] ?? 0),
                    (float) ($measurementAverages['tinggi_badan'] ?? 0),
                    (float) ($measurementAverages['lingkar_kepala'] ?? 0),
                ],
                'backgroundColor' => ['#f59e0b', '#3b82f6', '#22c55e'],
                'borderRadius' => 8,
            ]],
            'labels' => ['Berat Badan (kg)', 'Tinggi Badan (cm)', 'Lingkar Kepala (cm)'],
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
