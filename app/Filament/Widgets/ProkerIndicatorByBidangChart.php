<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\ProkerResource;
use App\Support\Admin\Dashboard\ProkerDashboardSupport;

class ProkerIndicatorByBidangChart extends InteractiveBarChartWidget
{
    protected ?string $heading = 'Capaian Indikator per Bidang';

    protected int|string|array $columnSpan = [
        'default' => 'full',
        'md' => 1,
    ];

    protected function getData(): array
    {
        $rows = collect(ProkerDashboardSupport::snapshot()['indicator_by_bidang'] ?? []);

        return [
            'datasets' => [
                [
                    'label' => 'Capaian indikator (%)',
                    'data' => $rows->pluck('rate')->all(),
                    'backgroundColor' => '#f59e0b',
                    'borderRadius' => 8,
                    'pointDetails' => $rows->map(function (array $row): array {
                        return [
                            'label' => $row['label'],
                            'count' => (int) $row['rate'],
                            'countLabel' => $row['rate'].'% capaian',
                            'shortDescription' => 'Klik untuk membuka daftar proker bidang ini.',
                            'description' => 'Daftar proker akan dibuka dengan filter bidang yang sesuai.',
                            'filterLabel' => 'Bidang: '.$row['label'],
                            'url' => ProkerResource::getUrl('index', [
                                'filters[bidang][value]' => $row['bidang_id'],
                            ]),
                        ];
                    })->all(),
                ],
            ],
            'labels' => $rows->pluck('label')->all(),
        ];
    }
}
