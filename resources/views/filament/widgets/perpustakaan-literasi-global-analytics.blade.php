<x-filament-widgets::widget>
    <details class="literasi-index-summary">
        <summary>
            <span>
                <strong>Ringkasan Literasi</strong>
                <small>History pengerjaan, analisa total, ranking, dan plagiasi.</small>
            </span>
        </summary>

        <div class="literasi-index-summary__content">
            <section class="literasi-health" aria-labelledby="literasi-health-title">
                <div class="literasi-health__heading">
                    <span>
                        <strong id="literasi-health-title">Kesehatan Pengiriman</strong>
                        <small>Antrean, retry, worker, dan pantauan jaringan sekolah. Data diperbarui saat panel dimuat.</small>
                    </span>
                    <span class="literasi-health__updated">{{ $operationalHealth['updated_at']->format('H:i:s') }}</span>
                </div>

                <div class="literasi-health__grid">
                    <article class="literasi-health-card">
                        <span>Antrean Sekarang</span>
                        <strong>{{ number_format($operationalHealth['waiting'], 0, ',', '.') }} menunggu</strong>
                        <small>{{ $operationalHealth['active'] }}/{{ $operationalHealth['active_slots'] }} slot aktif · rata-rata {{ number_format($operationalHealth['average_seconds'], 1, ',', '.') }} dtk</small>
                    </article>
                    <article class="literasi-health-card">
                        <span>Masuk 24 Jam</span>
                        <strong>{{ number_format($operationalHealth['direct_24h'] + $operationalHealth['queued_24h'], 0, ',', '.') }} jawaban</strong>
                        <small>Langsung {{ $operationalHealth['direct_24h'] }} · sempat antre {{ $operationalHealth['queued_24h'] }}</small>
                    </article>
                    <article @class(['literasi-health-card', 'is-warning' => $operationalHealth['retry_429_24h'] + $operationalHealth['retry_503_24h'] > 0])>
                        <span>Retry 24 Jam</span>
                        <strong>429: {{ $operationalHealth['retry_429_24h'] }} · 503: {{ $operationalHealth['retry_503_24h'] }}</strong>
                        <small>429 aplikasi {{ $operationalHealth['app_throttled_24h'] }} · 429 hosting/jaringan {{ $operationalHealth['hosting_throttled_24h'] }} · gagal setelah pemulihan {{ $operationalHealth['retry_exhausted_24h'] }}</small>
                    </article>
                    <article @class(['literasi-health-card', 'is-warning' => $operationalHealth['validation_failed_24h'] + $operationalHealth['verification_mismatch_24h'] > 0])>
                        <span>Perlu Diperbaiki Murid</span>
                        <strong>{{ $operationalHealth['verification_mismatch_24h'] }} verifikasi tidak cocok</strong>
                        <small>Validasi {{ $operationalHealth['validation_failed_24h'] }} · sudah mengisi {{ $operationalHealth['already_submitted_24h'] }} · Sampah {{ $operationalHealth['response_in_trash_24h'] }}</small>
                    </article>
                    <article @class(['literasi-health-card', 'is-warning' => $operationalHealth['unexpected_payload_24h'] > 0, 'is-danger' => $operationalHealth['server_error_24h'] > 0])>
                        <span>Pemulihan Struk</span>
                        <strong>{{ $operationalHealth['receipt_recovered_24h'] }} berhasil dipulihkan</strong>
                        <small>Respons tidak sesuai {{ $operationalHealth['unexpected_payload_24h'] }} · error aplikasi {{ $operationalHealth['server_error_24h'] }} · deadlock {{ $operationalHealth['queue_deadlock_24h'] }}</small>
                    </article>
                    <article @class(['literasi-health-card', 'is-danger' => $operationalHealth['failed_jobs'] > 0, 'is-warning' => ! $operationalHealth['scheduler_healthy'] && $operationalHealth['failed_jobs'] === 0])>
                        <span>Analisis Background</span>
                        <strong>{{ $operationalHealth['pending_jobs'] }} menunggu · {{ $operationalHealth['failed_jobs'] }} gagal</strong>
                        <small>Worker: {{ str($operationalHealth['worker_status'])->replace('_', ' ')->headline() }} · cron {{ $operationalHealth['scheduler_label'] }}</small>
                    </article>
                    <article @class([
                        'literasi-health-card',
                        'is-warning' => $operationalHealth['network_monitor_state'] === 'disabled',
                        'is-danger' => $operationalHealth['network_monitor_state'] !== 'disabled' && ! $operationalHealth['network_healthy'],
                    ])>
                        <span>Jaringan Sekolah</span>
                        <strong>{{ $operationalHealth['network_monitor_label'] }} · {{ str($operationalHealth['network_status'])->headline() }}</strong>
                        <small>
                            {{ $operationalHealth['network_label'] }}
                            @if($operationalHealth['network_duration_ms'])
                                · {{ number_format($operationalHealth['network_duration_ms'], 0, ',', '.') }} ms
                            @endif
                            @if($operationalHealth['network_error_code'])
                                · {{ $operationalHealth['network_error_code'] }}
                            @endif
                            · Aktif/nonaktifkan dari shortcut di desktop laptop sekolah.
                        </small>
                    </article>
                </div>
            </section>

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
