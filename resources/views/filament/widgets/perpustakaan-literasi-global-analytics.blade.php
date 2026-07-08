<x-filament-widgets::widget>
    <details class="literasi-index-summary">
        <summary>
            <span>
                <strong>Ringkasan Literasi</strong>
                <small>History pengerjaan, analisa total, ranking, dan plagiasi.</small>
            </span>
        </summary>

        <div class="literasi-index-summary__content">
            <a
                href="{{ \App\Filament\Resources\PerpustakaanLiterasiMaterialResource::getUrl('student-history') }}"
                class="literasi-history-link-card"
            >
                <span>
                    <strong>History Pengerjaan Siswa</strong>
                    <small>Lihat riwayat tugas setiap siswa, jawaban yang sudah dinilai, dan akun admin/guru penilainya.</small>
                </span>
                <span class="literasi-history-link-card__action">Buka History</span>
            </a>

            @include('filament.resources.perpustakaan-literasi-material-resource.partials.analytics-panel', [
                'analytics' => $analytics,
                'title' => 'Keseluruhan Soal Selama 1 Bulan',
                'description' => 'Rekap semua materi Literasi Numerasi pada bulan berjalan.',
            ])

            <details class="literasi-index-summary" open>
                <summary>
                    <span>
                        <strong>Secara Kategori Soal Selama 1 Bulan</strong>
                        <small>Ringkasan dipisahkan untuk tiap kategori program.</small>
                    </span>
                </summary>

                <div class="literasi-index-summary__content">
                    @foreach($categoryAnalytics as $category)
                        @include('filament.resources.perpustakaan-literasi-material-resource.partials.analytics-panel', [
                            'analytics' => $category['analytics'],
                            'title' => $category['label'],
                            'description' => 'Rekap bulan berjalan untuk kategori '.$category['label'].'.',
                            'compact' => true,
                        ])
                    @endforeach
                </div>
            </details>
        </div>
    </details>
</x-filament-widgets::widget>
