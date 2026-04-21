<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\DataSiswaResource;
use App\Support\Admin\Dashboard\DataSiswaDashboardSupport;

class DataSiswaStatusChart extends InteractiveDoughnutChartWidget
{
    protected ?string $heading = 'Distribusi Status Siswa';

    protected int|string|array $columnSpan = [
        'default' => 'full',
        'md' => 1,
    ];

    protected function getData(): array
    {
        $statuses = [
            'aktif' => [
                'label' => 'Aktif',
                'color' => '#22c55e',
                'description' => 'Siswa aktif adalah murid yang masih berjalan belajar di sekolah dan tercatat aktif pada periode saat ini.',
                'filterLabel' => 'Status: Aktif',
            ],
            'alumni' => [
                'label' => 'Alumni',
                'color' => '#f59e0b',
                'description' => 'Alumni adalah murid yang sudah lulus, tetapi datanya tetap diarsipkan untuk kebutuhan administrasi dan pelacakan riwayat.',
                'filterLabel' => 'Status: Alumni',
            ],
            'pindah' => [
                'label' => 'Pindah',
                'color' => '#3b82f6',
                'description' => 'Status pindah digunakan untuk siswa yang keluar dari sekolah karena mutasi atau perpindahan ke sekolah lain.',
                'filterLabel' => 'Status: Pindah / Mutasi',
            ],
            'keluar' => [
                'label' => 'Keluar',
                'color' => '#ef4444',
                'description' => 'Status keluar digunakan untuk siswa yang tidak lagi aktif karena alasan selain lulus dan mutasi.',
                'filterLabel' => 'Status: Keluar',
            ],
        ];

        $counts = DataSiswaDashboardSupport::snapshot(auth()->user())['status_counts'] ?? [];

        $labels = [];
        $data = [];
        $colors = [];
        $segmentDetails = [];

        foreach ($statuses as $status => $config) {
            $total = (int) ($counts[$status] ?? 0);

            $labels[] = $config['label'];
            $data[] = $total;
            $colors[] = $config['color'];
            $segmentDetails[] = [
                'label' => $config['label'],
                'count' => $total,
                'countLabel' => number_format($total, 0, ',', '.').' siswa',
                'shortDescription' => 'Klik untuk melihat daftar siswa dengan filter status yang sama.',
                'description' => $config['description'],
                'filterLabel' => $config['filterLabel'],
                'filters' => [
                    'status' => ['value' => $status],
                ],
                'chartQuery' => [
                    'chart_status' => $status,
                ],
                'url' => DataSiswaResource::getUrl('index', [
                    'chart_status' => $status,
                ]),
            ];
        }

        return [
            'datasets' => [
                [
                    'label' => 'Status Siswa',
                    'data' => $data,
                    'backgroundColor' => $colors,
                    'segmentDetails' => $segmentDetails,
                ],
            ],
            'labels' => $labels,
        ];
    }
}
