<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\GuruTendikResource;
use App\Models\GuruTendik;
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
        $palette = [
            'Guru' => '#f59e0b',
            'Tendik' => '#3b82f6',
            'Pamong' => '#10b981',
        ];

        $counts = GuruTendikDashboardSupport::snapshot(auth()->user())['jenis_ptk_counts'] ?? [];

        $labels = [];
        $data = [];
        $colors = [];
        $segmentDetails = [];

        foreach (GuruTendik::jenisPtkOptions() as $value => $label) {
            $total = (int) ($counts[$value] ?? 0);
            $labels[] = $label;
            $data[] = $total;
            $colors[] = $palette[$value] ?? '#64748b';
            $segmentDetails[] = [
                'label' => $label,
                'count' => $total,
                'countLabel' => number_format($total, 0, ',', '.').' data',
                'shortDescription' => 'Klik untuk melihat daftar guru/tendik dengan jenis PTK ini.',
                'description' => 'Daftar akan dibuka dengan filter jenis PTK yang sama.',
                'filterLabel' => 'Jenis PTK: '.$label,
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
