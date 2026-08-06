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

            <section class="literasi-monthly-share" aria-labelledby="literasi-monthly-share-title">
                <div class="literasi-monthly-share__heading">
                    <span>
                        <strong id="literasi-monthly-share-title">Salin Rekap Bulanan ke WhatsApp</strong>
                        <small>Pilih lingkup laporan. Data lengkap baru dihitung saat tombol ditekan, lalu ditampilkan untuk diperiksa sebelum disalin.</small>
                    </span>
                    <x-filament::icon icon="heroicon-o-clipboard-document-list" />
                </div>

                <div class="literasi-monthly-share__actions">
                    @foreach($monthlyShareScopes as $scope => $scopeData)
                        <button
                            type="button"
                            wire:click="prepareMonthlyShare('{{ $scope }}')"
                            wire:loading.attr="disabled"
                            wire:target="prepareMonthlyShare"
                            class="literasi-monthly-share__button"
                        >
                            <x-filament::icon icon="heroicon-o-clipboard-document-check" />
                            <span wire:loading.remove wire:target="prepareMonthlyShare('{{ $scope }}')">{{ $scopeData['button'] }}</span>
                            <span wire:loading wire:target="prepareMonthlyShare('{{ $scope }}')">Menyiapkan...</span>
                        </button>
                    @endforeach
                </div>
            </section>

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

            <x-filament::modal id="literacy-monthly-share-preview" width="5xl">
                <x-slot name="heading">{{ $monthlyShareTitle ?: 'Pratinjau Rekap Bulanan' }}</x-slot>
                <x-slot name="description">Periksa teks berikut sebelum menyalinnya ke grup WhatsApp.</x-slot>

                <div class="literasi-monthly-share-preview" wire:key="literacy-monthly-share-{{ md5($monthlyShareText ?? '') }}">
                    <div class="literasi-monthly-share-preview__meta">
                        <span>Seluruh data sesuai lingkup terpilih</span>
                        <strong class="tabular-nums">{{ number_format(mb_strlen($monthlyShareText ?? ''), 0, ',', '.') }} karakter</strong>
                    </div>
                    <textarea id="literacy-monthly-share-text" rows="20" readonly>{{ $monthlyShareText }}</textarea>
                    <div class="literasi-monthly-share-preview__footer">
                        <span id="literacy-monthly-share-text-status" class="literasi-monthly-share-preview__status" aria-live="polite"></span>
                        <div>
                            <x-filament::button
                                type="button"
                                color="gray"
                                x-on:click="$dispatch('close-modal', { id: 'literacy-monthly-share-preview' })"
                            >
                                Tutup
                            </x-filament::button>
                            <x-filament::button
                                type="button"
                                icon="heroicon-o-clipboard-document-check"
                                class="js-literacy-copy"
                                data-copy-target="literacy-monthly-share-text"
                                data-default-label="Salin Teks Lengkap"
                                data-empty-message="Rekap belum tersedia."
                                data-success-message="Rekap berhasil disalin. Buka WhatsApp lalu pilih Tempel."
                                data-fallback-message="Clipboard otomatis tidak tersedia. Salin teks dari kotak yang muncul."
                                :disabled="blank($monthlyShareText)"
                            >
                                <span>Salin Teks Lengkap</span>
                            </x-filament::button>
                        </div>
                    </div>
                </div>
            </x-filament::modal>
        </div>
    </details>
</x-filament-widgets::widget>
