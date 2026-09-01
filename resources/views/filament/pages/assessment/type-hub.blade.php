<x-filament-panels::page>
    @php
        $hub = $this->getHubData();
        $period = $hub['period'];
    @endphp

    <div class="assessment-type-page space-y-6">
        @include('filament.pages.assessment.partials.type-navigation')

        <section class="assessment-dashboard-hero assessment-type-hero overflow-hidden rounded-2xl border border-primary-200 bg-gradient-to-br from-primary-50 via-white to-white shadow-sm dark:border-primary-500/20 dark:from-primary-950/30 dark:via-gray-900 dark:to-gray-900">
            <div class="assessment-dashboard-hero__layout grid gap-5 p-4 sm:p-6 lg:grid-cols-[minmax(0,1fr)_minmax(16rem,24rem)] lg:items-center">
                <div class="min-w-0">
                    <span class="assessment-type-hero__eyebrow inline-flex items-center gap-2 rounded-full px-3 py-1 text-xs font-bold">
                        <x-filament::icon icon="heroicon-o-academic-cap" class="h-4 w-4" />
                        Pusat {{ $this->getAssessmentTypeLabel() }}
                    </span>
                    <h2 class="assessment-type-hero__title mt-3 break-words text-xl font-bold sm:text-2xl">
                        Semua kebutuhan {{ $this->getAssessmentTypeLabel() }} dalam satu halaman
                    </h2>
                    <p class="assessment-type-hero__copy mt-2 max-w-2xl text-sm leading-6">
                        Pilih kartu sesuai pekerjaan Anda. Data, nilai, dan rapor tetap dipisahkan berdasarkan periode yang dipilih.
                    </p>
                </div>

                <label class="assessment-dashboard-period block min-w-0 rounded-xl border border-gray-200 bg-white/90 p-3 shadow-sm dark:border-white/10 dark:bg-gray-950/70">
                    <span class="assessment-type-period__label text-xs font-bold uppercase tracking-wide">Periode yang dipantau</span>
                    <select wire:model.live="periodId" class="assessment-type-period__select mt-2 w-full min-w-0 rounded-lg text-sm">
                        @forelse ($this->getPeriodOptions() as $id => $label)
                            <option value="{{ $id }}">{{ $label }}</option>
                        @empty
                            <option value="">Belum ada periode {{ $this->getAssessmentTypeLabel() }}</option>
                        @endforelse
                    </select>
                </label>
            </div>
        </section>

        @if ($period)
            <section class="assessment-progress-note">
                <span class="assessment-progress-note__icon">
                    <x-filament::icon icon="heroicon-o-information-circle" />
                </span>
                <div>
                    <strong>{{ $hub['completed_count'] }} penugasan sudah dikirim; {{ $hub['remaining_count'] }} masih dapat dilengkapi.</strong>
                    <p>Status mencakup mapel yang diampu dan mapel pada kelas wali yang boleh dipantau. Input Nilai hanya menampilkan mapel yang Anda ampu.</p>
                </div>
            </section>

            <section class="assessment-type-summary-grid grid grid-cols-2 gap-3 lg:grid-cols-4">
                @foreach ([
                    ['label' => 'Siswa', 'value' => $hub['student_count'], 'icon' => 'heroicon-o-users'],
                    ['label' => 'Kelas', 'value' => $hub['class_count'], 'icon' => 'heroicon-o-building-office-2'],
                    ['label' => 'Penugasan', 'value' => $hub['assignment_count'], 'icon' => 'heroicon-o-clipboard-document-list'],
                    ['label' => 'Kemajuan', 'value' => $hub['completion_percentage'].'%', 'icon' => 'heroicon-o-chart-bar'],
                ] as $summary)
                    <article class="assessment-type-summary-card min-w-0 rounded-xl border border-gray-200 bg-white p-3 shadow-sm sm:p-4 dark:border-white/10 dark:bg-gray-900">
                        <div class="flex min-w-0 items-center gap-3">
                            <span class="assessment-type-summary-card__icon flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-primary-50 text-primary-600 dark:bg-primary-500/10 dark:text-primary-300">
                                <x-filament::icon :icon="$summary['icon']" class="h-5 w-5" />
                            </span>
                            <div class="min-w-0">
                                <p class="assessment-type-summary-card__label truncate text-xs font-semibold uppercase tracking-wide">{{ $summary['label'] }}</p>
                                <p class="assessment-type-summary-card__value mt-0.5 text-xl font-bold sm:text-2xl">{{ $summary['value'] }}</p>
                            </div>
                        </div>
                    </article>
                @endforeach
            </section>
        @else
            <section class="assessment-type-empty rounded-2xl border border-dashed p-5">
                <div class="flex items-start gap-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-warning-100 text-warning-700 dark:bg-warning-500/15 dark:text-warning-300">
                        <x-filament::icon icon="heroicon-o-information-circle" class="h-5 w-5" />
                    </span>
                    <div class="min-w-0">
                        <h2 class="assessment-type-empty__title font-bold">Belum ada periode {{ $this->getAssessmentTypeLabel() }}</h2>
                        <p class="assessment-type-empty__copy mt-1 text-sm leading-6">Admin dapat menyiapkan periode, komponen, dan penugasan dari kartu Pengaturan Penilaian.</p>
                    </div>
                </div>
            </section>
        @endif

        <section>
            <div class="mb-3">
                <h2 class="assessment-type-section__title text-base font-bold">Pilih Pekerjaan</h2>
                <p class="assessment-type-section__copy mt-1 text-sm">Setiap fungsi tampil sebagai kartu agar mudah ditemukan di HP maupun desktop.</p>
            </div>

            <div class="assessment-type-action-grid grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                @foreach ($hub['cards'] as $card)
                    @php
                        $toneClasses = match ($card['tone']) {
                            'success' => 'bg-success-50 text-success-700 dark:bg-success-500/10 dark:text-success-300',
                            'warning' => 'bg-warning-50 text-warning-700 dark:bg-warning-500/10 dark:text-warning-300',
                            'info' => 'bg-info-50 text-info-700 dark:bg-info-500/10 dark:text-info-300',
                            default => 'bg-primary-50 text-primary-700 dark:bg-primary-500/10 dark:text-primary-300',
                        };
                    @endphp
                    <article class="assessment-settings-card assessment-type-action-card flex min-w-0 flex-col rounded-2xl border border-gray-200 bg-white p-4 shadow-sm transition sm:p-5 dark:border-white/10 dark:bg-gray-900 {{ $card['url'] ? 'is-actionable hover:-translate-y-0.5 hover:border-primary-400 hover:shadow-md' : 'is-restricted opacity-70' }}">
                        <div class="flex min-w-0 items-start justify-between gap-3">
                            <span class="assessment-settings-card__icon flex h-11 w-11 shrink-0 items-center justify-center rounded-xl {{ $toneClasses }}">
                                <x-filament::icon :icon="$card['icon']" class="h-6 w-6" />
                            </span>
                            <div class="min-w-0 text-right">
                                <p class="assessment-type-action-card__value break-words text-xl font-bold">{{ $card['value'] }}</p>
                                <p class="assessment-type-action-card__caption break-words text-[11px] leading-4">{{ $card['caption'] }}</p>
                            </div>
                        </div>

                        <h3 class="assessment-type-action-card__title mt-4 break-words text-base font-bold">{{ $card['title'] }}</h3>
                        <p class="assessment-type-action-card__copy mt-2 flex-1 break-words text-sm leading-6">{{ $card['description'] }}</p>

                        @if ($card['url'])
                            <a href="{{ $card['url'] }}" wire:navigate class="assessment-settings-card__action mt-5 inline-flex min-h-10 w-full items-center justify-center gap-2 rounded-xl bg-primary-600 px-4 py-2 text-sm font-bold text-white transition hover:bg-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 dark:ring-offset-gray-900">
                                Buka {{ $card['title'] }}
                                <x-filament::icon icon="heroicon-o-arrow-right" class="h-4 w-4" />
                            </a>
                        @else
                            <span class="mt-5 inline-flex min-h-10 w-full items-center justify-center rounded-xl bg-gray-100 px-4 py-2 text-sm font-semibold text-gray-500 dark:bg-white/5 dark:text-gray-400">
                                Akses tidak tersedia
                            </span>
                        @endif
                    </article>
                @endforeach
            </div>
        </section>
    </div>
</x-filament-panels::page>
