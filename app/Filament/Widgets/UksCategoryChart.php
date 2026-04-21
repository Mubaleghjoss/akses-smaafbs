<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\UksRecordResource;
use App\Support\Admin\Dashboard\UksDashboardSupport;

class UksCategoryChart extends InteractiveDoughnutChartWidget
{
    protected ?string $heading = 'Distribusi Kategori Sakit';

    protected int|string|array $columnSpan = [
        'default' => 'full',
        'md' => 2,
    ];

    protected function getData(): array
    {
        $rows = collect(UksDashboardSupport::snapshot(auth()->user())['category_rows'] ?? []);

        return [
            'datasets' => [[
                'label' => 'Jumlah Kasus',
                'data' => $rows->pluck('total')->map(fn ($value): int => (int) $value)->all(),
                'backgroundColor' => ['#ef4444', '#f59e0b', '#22c55e', '#3b82f6', '#8b5cf6', '#06b6d4', '#f97316', '#14b8a6'],
                'segmentDetails' => $rows->map(fn ($row): array => [
                    'label' => (string) $row->kategori,
                    'count' => (int) $row->total,
                    'countLabel' => number_format((int) $row->total, 0, ',', '.').' kasus',
                    'shortDescription' => 'Klik untuk melihat daftar UKS pada kategori ini.',
                    'description' => 'Daftar UKS akan dibuka dengan filter kategori sakit yang sesuai.',
                    'filterLabel' => 'Kategori: '.$row->kategori,
                    'url' => UksRecordResource::getUrl('index', [
                        'chart_kategori' => $row->kategori,
                    ]),
                ])->all(),
            ]],
            'labels' => $rows->pluck('kategori')->all(),
        ];
    }
}
