<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\DataSiswaResource;
use App\Support\Admin\Dashboard\DataSiswaDashboardSupport;

class DataSiswaGenderByRombelChart extends InteractiveBarChartWidget
{
    protected ?string $heading = 'Jenis Kelamin per Rombel';

    protected int|string|array $columnSpan = [
        'default' => 'full',
        'md' => 2,
    ];

    /**
     * @param  array<string, mixed>|object  $row
     */
    protected function rowValue(array|object $row, string $key): mixed
    {
        return data_get($row, $key);
    }

    protected function getData(): array
    {
        $rows = collect(DataSiswaDashboardSupport::snapshot(auth()->user())['gender_by_rombel'] ?? []);

        return [
            'datasets' => [
                [
                    'label' => 'Putra',
                    'data' => $rows->pluck('total_putra')->map(fn ($value): int => (int) $value)->all(),
                    'backgroundColor' => '#3b82f6',
                    'borderRadius' => 8,
                    'pointDetails' => $rows->map(fn ($row): array => [
                        'label' => 'Putra - '.($this->rowValue($row, 'rombel_saat_ini') ?: '-'),
                        'count' => (int) $this->rowValue($row, 'total_putra'),
                        'countLabel' => number_format((int) $this->rowValue($row, 'total_putra'), 0, ',', '.').' siswa',
                        'shortDescription' => 'Klik untuk melihat siswa putra aktif pada rombel ini.',
                        'description' => 'Daftar akan difilter ke siswa aktif putra pada rombel yang dipilih.',
                        'filterLabel' => 'Rombel: '.($this->rowValue($row, 'rombel_saat_ini') ?: '-').' | JK: L | Status: Aktif',
                        'filters' => [
                            'status' => ['value' => 'aktif'],
                            'rombel_saat_ini' => ['value' => $this->rowValue($row, 'rombel_saat_ini')],
                            'jk' => ['value' => 'L'],
                        ],
                        'chartQuery' => [
                            'chart_status' => 'aktif',
                            'chart_rombel' => $this->rowValue($row, 'rombel_saat_ini'),
                            'chart_jk' => 'L',
                        ],
                        'url' => DataSiswaResource::getUrl('index', [
                            'chart_status' => 'aktif',
                            'chart_rombel' => $this->rowValue($row, 'rombel_saat_ini'),
                            'chart_jk' => 'L',
                        ]),
                    ])->all(),
                ],
                [
                    'label' => 'Putri',
                    'data' => $rows->pluck('total_putri')->map(fn ($value): int => (int) $value)->all(),
                    'backgroundColor' => '#ec4899',
                    'borderRadius' => 8,
                    'pointDetails' => $rows->map(fn ($row): array => [
                        'label' => 'Putri - '.($this->rowValue($row, 'rombel_saat_ini') ?: '-'),
                        'count' => (int) $this->rowValue($row, 'total_putri'),
                        'countLabel' => number_format((int) $this->rowValue($row, 'total_putri'), 0, ',', '.').' siswa',
                        'shortDescription' => 'Klik untuk melihat siswa putri aktif pada rombel ini.',
                        'description' => 'Daftar akan difilter ke siswa aktif putri pada rombel yang dipilih.',
                        'filterLabel' => 'Rombel: '.($this->rowValue($row, 'rombel_saat_ini') ?: '-').' | JK: P | Status: Aktif',
                        'filters' => [
                            'status' => ['value' => 'aktif'],
                            'rombel_saat_ini' => ['value' => $this->rowValue($row, 'rombel_saat_ini')],
                            'jk' => ['value' => 'P'],
                        ],
                        'chartQuery' => [
                            'chart_status' => 'aktif',
                            'chart_rombel' => $this->rowValue($row, 'rombel_saat_ini'),
                            'chart_jk' => 'P',
                        ],
                        'url' => DataSiswaResource::getUrl('index', [
                            'chart_status' => 'aktif',
                            'chart_rombel' => $this->rowValue($row, 'rombel_saat_ini'),
                            'chart_jk' => 'P',
                        ]),
                    ])->all(),
                ],
            ],
            'labels' => $rows->pluck('rombel_saat_ini')->all(),
        ];
    }
}
