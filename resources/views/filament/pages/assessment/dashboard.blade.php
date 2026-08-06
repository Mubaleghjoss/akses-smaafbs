<x-filament-panels::page>
    @php
        $metrics = $this->getMetrics();
        $settingCards = $this->getSettingCards();
    @endphp

    <div class="assessment-dashboard-page">
        <section class="assessment-dashboard-panel assessment-dashboard-overview">
            <div class="assessment-dashboard-overview__layout">
                <div class="min-w-0">
                    <h2 class="assessment-dashboard-title">Pusat Penilaian ASTS–ASAS</h2>
                    <p class="assessment-dashboard-copy">Pilih periode, lalu buka menu atau status yang perlu ditangani.</p>
                </div>
                <label class="assessment-dashboard-period">
                    <span class="assessment-dashboard-label">Periode yang dipantau</span>
                    <select wire:model.live="periodId" class="assessment-dashboard-select">
                        @forelse ($this->getPeriodOptions() as $id => $label)
                            <option value="{{ $id }}">{{ $label }}</option>
                        @empty
                            <option value="">Belum ada periode</option>
                        @endforelse
                    </select>
                </label>
            </div>
        </section>

        <section class="assessment-dashboard-group" aria-labelledby="assessment-menu-title">
            <div class="assessment-dashboard-group__head">
                <h2 id="assessment-menu-title">Menu Pengaturan</h2>
                <p>Semua pengaturan penting tersedia langsung dari kartu berikut.</p>
            </div>
            <div class="assessment-dashboard-card-grid">
                @foreach ($settingCards as $card)
                    <article class="assessment-dashboard-card assessment-dashboard-card--menu">
                        <div class="assessment-dashboard-card__head">
                            <h3>{{ $card['title'] }}</h3>
                            <span class="assessment-dashboard-card__badge">{{ $card['value'] }}</span>
                        </div>
                        <p class="assessment-dashboard-card__caption">{{ $card['caption'] }}</p>
                        <div class="assessment-dashboard-card__points">
                            @foreach ($card['points'] as $point)
                                <p>{{ $point }}</p>
                            @endforeach
                        </div>
                        @if ($card['url'])
                            <a href="{{ $card['url'] }}" wire:navigate class="assessment-dashboard-card__action">
                                {{ $card['action'] }}
                            </a>
                        @else
                            <span class="assessment-dashboard-card__action is-disabled">Akses tidak tersedia</span>
                        @endif
                    </article>
                @endforeach
            </div>
        </section>

        <section class="assessment-dashboard-group" aria-labelledby="assessment-readiness-title">
            <div class="assessment-dashboard-group__head">
                <h2 id="assessment-readiness-title">Kesiapan Fondasi</h2>
                <p>Ringkasan data yang dibutuhkan sebelum periode dibuka.</p>
            </div>
            <div class="assessment-dashboard-readiness-grid">
                @foreach ($this->getReadiness() as $item)
                    <article class="assessment-dashboard-card assessment-dashboard-readiness-card {{ $item['ready'] ? 'is-ready' : 'is-warning' }}">
                        <div class="assessment-dashboard-card__head">
                            <h3>{{ $item['label'] }}</h3>
                            <strong class="assessment-dashboard-readiness-card__count">{{ $item['count'] }}</strong>
                        </div>
                        <p class="assessment-dashboard-readiness-card__status">{{ $item['ready'] ? 'Siap digunakan' : 'Perlu dilengkapi' }}</p>
                    </article>
                @endforeach
            </div>
        </section>

        <section class="assessment-dashboard-panel assessment-dashboard-status" aria-labelledby="assessment-status-title">
            <div class="assessment-dashboard-panel__head">
                <div class="min-w-0">
                    <h2 id="assessment-status-title">Status Pengumpulan</h2>
                    @if ($metrics['period'])
                        <p>{{ $metrics['student_count'] }} siswa · {{ $metrics['class_count'] }} kelas · {{ $metrics['assignment_count'] }} penugasan</p>
                    @else
                        <p>Belum ada periode yang dapat dipantau.</p>
                    @endif
                </div>
                @if ($metrics['period'])
                    <span class="assessment-dashboard-status__badge">{{ $metrics['period']->status->label() }}</span>
                @endif
            </div>
            @if ($metrics['period'])
                <div class="assessment-dashboard-status-grid">
                    @foreach ($metrics['cards'] as $card)
                        <a href="{{ $card['url'] }}" wire:navigate class="assessment-dashboard-status-card">
                            <span>{{ $card['label'] }}</span>
                            <strong>{{ $card['count'] }}</strong>
                        </a>
                    @endforeach
                </div>
            @endif
        </section>

        <section class="assessment-dashboard-panel assessment-dashboard-activity" aria-labelledby="assessment-activity-title">
            <div class="assessment-dashboard-panel__head">
                <div class="min-w-0">
                    <h2 id="assessment-activity-title">Aktivitas Terbaru</h2>
                    <p>Jejak perubahan pada periode terpilih.</p>
                </div>
                @if (\App\Filament\Resources\AssessmentAuditLogResource::canViewAny())
                    <a href="{{ \App\Filament\Resources\AssessmentAuditLogResource::getUrl() }}" wire:navigate class="assessment-dashboard-secondary-action">Buka Semua Histori</a>
                @endif
            </div>
            <div class="assessment-audit-card-grid">
                @forelse ($this->getRecentAuditRows() as $row)
                    <article class="assessment-audit-card">
                        <div class="assessment-audit-card__head">
                            <h3>{{ $row['event'] }}</h3>
                            <time>{{ $row['time'] }}</time>
                        </div>
                        <p class="assessment-audit-card__meta">{{ $row['actor'] }} · {{ $row['period'] }} · {{ $row['subject'] }}</p>
                        <p class="assessment-audit-card__points">{{ $row['reason'] }}</p>
                        <button type="button" wire:click="mountAction('viewAuditLog', { record: {{ $row['id'] }} })" class="assessment-dashboard-secondary-action">
                            Lihat Detail
                        </button>
                    </article>
                @empty
                    <p class="assessment-audit-empty">Belum ada aktivitas pada periode ini.</p>
                @endforelse
            </div>
        </section>
    </div>
</x-filament-panels::page>
