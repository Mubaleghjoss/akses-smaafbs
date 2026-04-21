<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\DataSiswaResource;
use App\Models\DataSiswa;
use App\Support\Admin\Dashboard\DataSiswaDashboardSupport;

class DataSiswaNonAktifReasonChart extends InteractiveDoughnutChartWidget
{
    protected ?string $heading = 'Analisa Alasan Non Aktif';

    protected int|string|array $columnSpan = [
        'default' => 'full',
        'md' => 1,
    ];

    protected function getData(): array
    {
        $categories = [
            'lulus' => [
                'label' => 'Lulus / Alumni',
                'color' => '#f59e0b',
                'description' => 'Kelompok ini berisi siswa non aktif karena sudah lulus dan berubah menjadi alumni.',
                'filterLabel' => 'Status: Alumni',
                'filters' => [
                    'status' => ['value' => 'alumni'],
                ],
                'chartQuery' => [
                    'chart_status' => 'alumni',
                ],
                'url' => DataSiswaResource::getUrl('index', [
                    'chart_status' => 'alumni',
                ]),
            ],
            'mutasi' => [
                'label' => 'Mutasi',
                'color' => '#3b82f6',
                'description' => 'Kelompok ini berisi siswa non aktif karena mutasi atau pindah sekolah.',
                'filterLabel' => 'Status: Pindah / Mutasi',
                'filters' => [
                    'status' => ['value' => 'pindah'],
                ],
                'chartQuery' => [
                    'chart_status' => 'pindah',
                ],
                'url' => DataSiswaResource::getUrl('index', [
                    'chart_status' => 'pindah',
                ]),
            ],
            'mengundurkan_diri' => [
                'label' => 'Mengundurkan Diri',
                'color' => '#ef4444',
                'description' => 'Kelompok ini menandai siswa yang dinonaktifkan karena memilih keluar atau mengundurkan diri.',
                'filterLabel' => 'Kategori Non Aktif: Mengundurkan Diri',
                'filters' => [
                    'kategori_non_aktif' => ['value' => 'mengundurkan_diri'],
                ],
                'chartQuery' => [
                    'chart_kategori_non_aktif' => 'mengundurkan_diri',
                ],
                'url' => DataSiswaResource::getUrl('index', [
                    'chart_kategori_non_aktif' => 'mengundurkan_diri',
                ]),
            ],
            'wafat' => [
                'label' => 'Wafat',
                'color' => '#6366f1',
                'description' => 'Kelompok ini menandai siswa non aktif karena wafat.',
                'filterLabel' => 'Kategori Non Aktif: Wafat',
                'filters' => [
                    'kategori_non_aktif' => ['value' => 'wafat'],
                ],
                'chartQuery' => [
                    'chart_kategori_non_aktif' => 'wafat',
                ],
                'url' => DataSiswaResource::getUrl('index', [
                    'chart_kategori_non_aktif' => 'wafat',
                ]),
            ],
            'lainnya' => [
                'label' => 'Lainnya',
                'color' => '#6b7280',
                'description' => 'Kelompok ini berisi alasan non aktif lain di luar lulus, mutasi, mengundurkan diri, dan wafat.',
                'filterLabel' => 'Status: Keluar / Kategori Lainnya',
                'filters' => [
                    'status' => ['value' => 'keluar'],
                ],
                'chartQuery' => [
                    'chart_status' => 'keluar',
                ],
                'url' => DataSiswaResource::getUrl('index', [
                    'chart_status' => 'keluar',
                ]),
            ],
        ];

        $counts = array_fill_keys(array_keys($categories), 0);
        $aggregatedCounts = DataSiswaDashboardSupport::snapshot(auth()->user())['non_active_category_counts'] ?? [];

        foreach ($aggregatedCounts as $category => $total) {
            if ($category !== null && array_key_exists($category, $counts)) {
                $counts[$category] = (int) $total;
            }
        }

        $labels = [];
        $data = [];
        $colors = [];
        $segmentDetails = [];

        foreach ($categories as $category => $config) {
            $total = (int) ($counts[$category] ?? 0);

            $labels[] = $config['label'];
            $data[] = $total;
            $colors[] = $config['color'];
            $segmentDetails[] = [
                'label' => $config['label'],
                'count' => $total,
                'countLabel' => number_format($total, 0, ',', '.').' siswa',
                'shortDescription' => 'Klik untuk melihat daftar siswa yang masuk kelompok alasan ini.',
                'description' => $config['description'],
                'filterLabel' => $config['filterLabel'],
                'filters' => $config['filters'],
                'chartQuery' => $config['chartQuery'],
                'url' => $config['url'],
            ];
        }

        return [
            'datasets' => [
                [
                    'label' => 'Alasan Non Aktif',
                    'data' => $data,
                    'backgroundColor' => $colors,
                    'segmentDetails' => $segmentDetails,
                ],
            ],
            'labels' => $labels,
        ];
    }
}
