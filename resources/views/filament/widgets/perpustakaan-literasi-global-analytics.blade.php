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
                        <small class="literasi-network-stages">
                            Gateway {{ $operationalHealth['network_gateway_ok'] === true ? 'Terlihat' : ($operationalHealth['network_gateway_ok'] === false ? 'Tidak terlihat' : '-') }}
                            · Internet {{ $operationalHealth['network_internet_ok'] === true ? 'OK' : ($operationalHealth['network_internet_ok'] === false ? 'Gagal' : '-') }}
                            · DNS {{ $operationalHealth['network_dns_ok'] === true ? 'OK' : ($operationalHealth['network_dns_ok'] === false ? 'Gagal' : '-') }}
                            · TCP 443 {{ $operationalHealth['network_tcp_ok'] === true ? 'OK' : ($operationalHealth['network_tcp_ok'] === false ? 'Gagal' : '-') }}
                            · HTTPS {{ $operationalHealth['network_http_status'] ?: '-' }}
                        </small>
                    </article>
                </div>
            </section>

            <section class="literasi-connectivity" aria-labelledby="literasi-connectivity-title">
                <div class="literasi-connectivity__heading">
                    <span>
                        <strong id="literasi-connectivity-title" class="text-balance">Konektivitas dan Pengunjung</strong>
                        <small class="text-pretty">Perangkat merupakan perkiraan browser anonim, bukan jumlah orang. Telemetri tidak menyimpan nama atau jawaban siswa.</small>
                    </span>
                    <x-filament::icon icon="heroicon-o-signal" />
                </div>

                <form wire:submit="refreshConnectivity" class="literasi-connectivity__filters">
                    <label>
                        <span>Tanggal</span>
                        <input type="date" wire:model="connectivityDate" max="{{ now()->format('Y-m-d') }}">
                    </label>
                    <label>
                        <span>Dari</span>
                        <input type="time" wire:model="connectivityFrom">
                    </label>
                    <label>
                        <span>Sampai</span>
                        <input type="time" wire:model="connectivityTo">
                    </label>
                    <x-filament::button type="submit" icon="heroicon-o-arrow-path" wire:loading.attr="disabled" wire:target="refreshConnectivity">
                        <span wire:loading.remove wire:target="refreshConnectivity">Tampilkan</span>
                        <span wire:loading wire:target="refreshConnectivity">Memuat...</span>
                    </x-filament::button>
                </form>
                @error('connectivityTo')
                    <p class="literasi-connectivity__error">{{ $message }}</p>
                @enderror

                <div class="literasi-connectivity__period">
                    <strong>{{ $connectivity['date'] }}</strong>
                    <span>{{ $connectivity['range'] }}</span>
                </div>

                <div class="literasi-connectivity__metrics">
                    <article><span>Perangkat aktif</span><strong>{{ number_format($connectivity['devices'], 0, ',', '.') }}</strong><small>ID browser anonim</small></article>
                    <article><span>Sesi mencapai aplikasi</span><strong>{{ number_format($connectivity['sessions'], 0, ',', '.') }}</strong><small>Maksimal sekali per browser/hari</small></article>
                    <article><span>Tiket submit</span><strong>{{ number_format($connectivity['tickets'], 0, ',', '.') }}</strong><small>Request yang mencapai Laravel</small></article>
                    <article><span>Jawaban tersimpan</span><strong>{{ number_format($connectivity['responses'], 0, ',', '.') }}</strong><small>Respons aktif dan Sampah</small></article>
                    <article><span>Struk dibuka</span><strong>{{ number_format($connectivity['receipts'], 0, ',', '.') }}</strong><small>Dicatat setelah pembaruan ini</small></article>
                    <article @class(['is-warning' => $connectivity['offline'] > 0])><span>Jaringan terputus</span><strong>{{ number_format($connectivity['offline'], 0, ',', '.') }}</strong><small>Navigasi tanpa respons</small></article>
                    <article @class(['is-danger' => $connectivity['unavailable'] > 0])><span>Server 503/504</span><strong>{{ number_format($connectivity['unavailable'], 0, ',', '.') }}</strong><small>Terlihat oleh browser</small></article>
                    <article><span>Berhasil pulih</span><strong>{{ number_format($connectivity['recovered'], 0, ',', '.') }}</strong><small>{{ number_format($connectivity['school_events'], 0, ',', '.') }} event cocok IP sekolah</small></article>
                </div>

                <div class="literasi-connectivity__hourly">
                    <div class="literasi-connectivity__hourly-heading">
                        <strong>Rincian per jam</strong>
                        <small>Informasional; request aset lengkap tetap diperiksa melalui access log cPanel.</small>
                    </div>
                    @forelse($connectivity['hourly'] as $hour)
                        <article>
                            <strong>{{ $hour['label'] }}</strong>
                            <span>Perangkat <b>{{ $hour['devices'] }}</b></span>
                            <span>Tiket <b>{{ $hour['tickets'] }}</b></span>
                            <span>Jawaban <b>{{ $hour['responses'] }}</b></span>
                            <span>Struk <b>{{ $hour['receipts'] }}</b></span>
                            <span>Offline <b>{{ $hour['offline'] }}</b></span>
                            <span>503/504 <b>{{ $hour['unavailable'] }}</b></span>
                        </article>
                    @empty
                        <div class="literasi-connectivity__empty">
                            <strong>Belum ada aktivitas pada rentang ini.</strong>
                            <small>Pilih tanggal atau jam lain, lalu tekan Tampilkan.</small>
                        </div>
                    @endforelse
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
