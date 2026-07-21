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

            <div class="literasi-analytics-tabs" role="tablist" aria-label="Kategori analisa literasi">
                @foreach($analyticsTabs as $key => $label)
                    <button
                        type="button"
                        role="tab"
                        wire:click="selectAnalyticsTab('{{ $key }}')"
                        wire:loading.attr="disabled"
                        @class(['is-active' => $activeAnalyticsTab === $key])
                        aria-selected="{{ $activeAnalyticsTab === $key ? 'true' : 'false' }}"
                    >
                        {{ $label }}
                    </button>
                @endforeach
            </div>

            <div class="literasi-analytics-loading" wire:loading.flex wire:target="selectAnalyticsTab">
                Memuat analisa kategori...
            </div>

            <div wire:loading.remove wire:target="selectAnalyticsTab" wire:key="literasi-analytics-{{ $activeAnalyticsTab }}">
                @include('filament.resources.perpustakaan-literasi-material-resource.partials.analytics-panel', [
                    'analytics' => $analytics,
                    'title' => $analyticsTitle,
                    'description' => $analyticsDescription,
                ])
            </div>
        </div>
    </details>
</x-filament-widgets::widget>
