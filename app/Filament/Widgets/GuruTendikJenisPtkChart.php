<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\GuruTendikResource;
use App\Support\Admin\Dashboard\GuruTendikDashboardSupport;

class GuruTendikJenisPtkChart extends InteractiveDoughnutChartWidget
{
    protected ?string $heading = 'Komposisi Jenis PTK';

    protected int|string|array $columnSpan = [
        'default' => 'full',
        'md' => 1,
    ];

    protected function getData(): array
    {
        $options = [
            'Guru' => ['label' => 'Guru', 'color' => '#f59e0b'],
            'Tendik' => ['label' => 'Tendik', 'color' => '#3b82f6'],
        ];

        $counts = GuruTendikDashboardSupport::snapshot(auth()->user())['jenis_ptk_counts'] ?? [];

        $labels = [];
        $data = [];
        $colors = [];
        $segmentDetails = [];

        foreach ($options as $value => $config) {
            $total = (int) ($counts[$value] ?? 0);
            $labels[] = $config['label'];
            $data[] = $total;
            $colors[] = $config['color'];
            $segmentDetails[] = [
                'label' => $config['label'],
                'count' => $total,
                'countLabel' => number_format($total, 0, ',', '.').' data',
                'shortDescription' => 'Klik untuk melihat daftar guru/tendik dengan jenis PTK ini.',
                'description' => 'Daftar akan dibuka dengan filter jenis PTK yang sama.',
                'filterLabel' => 'Jenis PTK: '.$config['label'],
                'url' => GuruTendikResource::getUrl('index', [
                    'chart_jenis_ptk' => $value,
                ]),
            ];
        }

        return [
            'datasets' => [[
                'label' => 'Jenis PTK',
                'data' => $data,
                'backgroundColor' => $colors,
                'segmentDetails' => $segmentDetails,
            ]],
            'labels' => $labels,
        ];
    }
}
