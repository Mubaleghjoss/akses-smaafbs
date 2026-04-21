<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\ProkerResource;
use App\Support\Admin\Dashboard\ProkerDashboardSupport;

class ProkerStatusChart extends InteractiveDoughnutChartWidget
{
    protected ?string $heading = 'Distribusi Status Proker';

    protected int|string|array $columnSpan = [
        'default' => 'full',
        'md' => 1,
    ];

    protected function getData(): array
    {
        $statuses = ['draft', 'berjalan', 'terkendala', 'selesai'];
        $labels = ['Draft', 'Berjalan', 'Terkendala', 'Selesai'];
        $colors = ['#94a3b8', '#3b82f6', '#ef4444', '#22c55e'];
        $counts = ProkerDashboardSupport::snapshot()['status_counts'] ?? [];

        return [
            'datasets' => [
                [
                    'label' => 'Status Proker',
                    'data' => collect($statuses)->map(fn (string $status): int => (int) ($counts[$status] ?? 0))->all(),
                    'backgroundColor' => $colors,
                    'segmentDetails' => collect($statuses)->map(fn (string $status, int $index): array => [
                        'label' => $labels[$index],
                        'count' => (int) ($counts[$status] ?? 0),
                        'countLabel' => number_format((int) ($counts[$status] ?? 0), 0, ',', '.').' proker',
                        'shortDescription' => 'Klik untuk membuka daftar proker dengan status ini.',
                        'description' => 'Daftar proker akan dibuka dengan filter status yang sesuai.',
                        'filterLabel' => 'Status: '.$labels[$index],
                        'url' => ProkerResource::getUrl('index', [
                            'filters[status][value]' => $status,
                        ]),
                    ])->all(),
                ],
            ],
            'labels' => $labels,
        ];
    }
}
