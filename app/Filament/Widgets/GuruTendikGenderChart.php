<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\GuruTendikResource;
use App\Support\Admin\Dashboard\GuruTendikDashboardSupport;

class GuruTendikGenderChart extends InteractiveDoughnutChartWidget
{
    protected ?string $heading = 'Distribusi Jenis Kelamin';

    protected int|string|array $columnSpan = [
        'default' => 'full',
        'md' => 1,
    ];

    protected function getData(): array
    {
        $options = [
            'L' => ['label' => 'Laki-laki', 'color' => '#22c55e'],
            'P' => ['label' => 'Perempuan', 'color' => '#ec4899'],
        ];

        $counts = GuruTendikDashboardSupport::snapshot(auth()->user())['gender_counts'] ?? [];

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
                'shortDescription' => 'Klik untuk melihat daftar guru/tendik dengan jenis kelamin ini.',
                'description' => 'Daftar akan dibuka dengan filter jenis kelamin yang sama.',
                'filterLabel' => 'Jenis Kelamin: '.$config['label'],
                'url' => GuruTendikResource::getUrl('index', [
                    'chart_jk' => $value,
                ]),
            ];
        }

        return [
            'datasets' => [[
                'label' => 'Jenis Kelamin',
                'data' => $data,
                'backgroundColor' => $colors,
                'segmentDetails' => $segmentDetails,
            ]],
            'labels' => $labels,
        ];
    }
}
