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
                'title' => 'Analisa Total Literasi',
                'description' => 'Rekap semua materi Literacy Habituation Program.',
            ])
        </div>
    </details>
</x-filament-widgets::widget>
