<x-filament-panels::page>
    @php($periods = $this->getProgressPeriods())

    <div class="assessment-report-progress space-y-6">
        @if ($periods === [])
            @php($emptyState = $this->getEmptyState())

            <section class="assessment-report-progress__empty-state rounded-2xl border p-5 shadow-sm sm:p-8" data-empty-state="{{ $emptyState['key'] }}">
                <div class="assessment-report-progress__empty-layout flex flex-col gap-5 sm:flex-row sm:items-start">
                    <div class="assessment-report-progress__empty-icon flex h-12 w-12 shrink-0 items-center justify-center rounded-xl" aria-hidden="true">
                        <x-filament::icon :icon="$emptyState['icon']" class="h-7 w-7" />
                    </div>

                    <div class="assessment-report-progress__empty-content min-w-0 flex-1">
                        <span class="assessment-report-progress__empty-label inline-flex rounded-full px-3 py-1 text-xs font-bold">Informasi progres rapor</span>
                        <h2 class="assessment-report-progress__empty-title mt-3 break-words text-xl font-bold leading-tight sm:text-2xl">{{ $emptyState['title'] }}</h2>
                        <p class="assessment-report-progress__empty-description mt-3 max-w-3xl break-words text-sm leading-6 sm:text-base sm:leading-7">{{ $emptyState['description'] }}</p>

                        <div class="assessment-report-progress__empty-action mt-5 rounded-xl border p-4">
                            <div class="assessment-report-progress__empty-action-layout flex items-start gap-3">
                                <x-filament::icon icon="heroicon-o-information-circle" class="mt-0.5 h-5 w-5 shrink-0" />
                                <div class="min-w-0">
                                    <p class="text-xs font-bold uppercase tracking-wide">Yang perlu Anda lakukan</p>
                                    <p class="mt-1 break-words text-sm font-semibold leading-6">{{ $emptyState['action'] }}</p>
                                </div>
                            </div>
                        </div>

                        <p class="assessment-report-progress__empty-footnote mt-4 text-xs leading-5">Informasi ini diperbarui otomatis dari periode, penugasan, wali kelas, peran, dan akses Penilaian yang tercatat di sistem.</p>
                    </div>
                </div>
            </section>
        @else
            <section class="assessment-report-progress__hero rounded-2xl border p-4 shadow-sm sm:p-6">
                <div class="assessment-report-progress__hero-layout flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                    <div class="assessment-report-progress__hero-copy min-w-0">
                        <span class="assessment-report-progress__eyebrow inline-flex items-center gap-2 rounded-full px-3 py-1 text-xs font-bold">
                            <x-filament::icon icon="heroicon-o-flag" class="h-4 w-4" />
                            Tujuan akhir: rapor siap dicetak
                        </span>
                        <h2 class="assessment-report-progress__title mt-3 text-xl font-bold sm:text-2xl">Apa yang harus saya kerjakan sekarang?</h2>
                        <p class="assessment-report-progress__copy mt-2 max-w-3xl text-sm leading-6">Halaman ini membaca status Penilaian yang sebenarnya. Semua tanggung jawab akun Anda ditampilkan per peran dan per periode aktif, tanpa checklist manual.</p>
                    </div>
                    <div class="assessment-report-progress__period-count rounded-xl border px-4 py-3 text-center">
                        <strong class="block text-2xl">{{ count($periods) }}</strong>
                        <span class="text-xs font-semibold">periode aktif dalam cakupan</span>
                    </div>
                </div>
            </section>

            @foreach ($periods as $period)
                <section class="assessment-report-progress__period rounded-2xl border p-4 shadow-sm sm:p-6" aria-labelledby="progress-period-{{ $period['id'] }}">
                    <header class="assessment-report-progress__period-header flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div class="min-w-0">
                            <div class="assessment-report-progress__badges flex flex-wrap items-center gap-2">
                                <span class="assessment-report-progress__type rounded-full px-2.5 py-1 text-xs font-bold">{{ $period['type_label'] }}</span>
                                <span class="assessment-report-progress__workflow rounded-full px-2.5 py-1 text-xs font-semibold">{{ $period['status_label'] }}</span>
                            </div>
                            <h2 id="progress-period-{{ $period['id'] }}" class="assessment-report-progress__period-title mt-2 break-words text-lg font-bold sm:text-xl">{{ $period['name'] }}</h2>
                        </div>
                        <div class="assessment-report-progress__summary text-right">
                            <strong class="assessment-report-progress__overall block text-2xl">{{ $period['overall_percent'] }}%</strong>
                            <span class="assessment-report-progress__readiness {{ $period['ready_to_print'] ? 'is-ready' : 'is-blocked' }} mt-1 inline-flex shrink-0 items-center gap-2 rounded-full px-3 py-1.5 text-xs font-bold">
                                <x-filament::icon :icon="$period['ready_to_print'] ? 'heroicon-o-check-circle' : 'heroicon-o-exclamation-triangle'" class="h-4 w-4" />
                                {{ $period['readiness_label'] }}
                            </span>
                        </div>
                    </header>

                    <div class="assessment-report-progress__path mt-5 grid grid-cols-2 gap-2 sm:grid-cols-4 lg:grid-cols-8" aria-label="Tahapan menuju cetak rapor">
                        @foreach (['Konfigurasi', 'Penugasan', 'Input Nilai', 'Verifikasi', 'Rekap Wali', 'Preflight', 'Kunci', 'Cetak'] as $step)
                            <div class="assessment-report-progress__step rounded-lg border px-2 py-2 text-center text-[11px] font-semibold">{{ $step }}</div>
                        @endforeach
                    </div>

                    <div class="assessment-report-progress__roles mt-5 grid gap-4 lg:grid-cols-2">
                        @foreach ($period['roles'] as $role)
                            <article class="assessment-report-progress__role flex min-w-0 flex-col rounded-xl border p-4">
                                <div class="assessment-report-progress__role-header flex min-w-0 items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <h3 class="assessment-report-progress__role-title font-bold">{{ $role['label'] }}</h3>
                                        <p class="assessment-report-progress__scope mt-1 break-words text-xs leading-5">{{ $role['scope'] }}</p>
                                    </div>
                                    <span class="assessment-report-progress__role-status rounded-full px-2.5 py-1 text-[11px] font-bold">{{ $role['status'] }}</span>
                                </div>
                                <div class="mt-4 flex items-end justify-between gap-3">
                                    <div>
                                        <strong class="assessment-report-progress__percent text-2xl">{{ $role['percent'] }}%</strong>
                                        <p class="assessment-report-progress__numbers mt-1 text-xs">{{ $role['completed'] }} selesai · {{ $role['pending'] }} belum selesai</p>
                                    </div>
                                </div>
                                <div class="assessment-report-progress__track mt-3 h-2 overflow-hidden rounded-full" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $role['percent'] }}" aria-label="Progres {{ $role['label'] }}">
                                    <div class="assessment-report-progress__bar h-full rounded-full" style="width: {{ $role['percent'] }}%"></div>
                                </div>
                                <div class="assessment-report-progress__next mt-4 rounded-lg border p-3">
                                    <p class="text-[11px] font-bold uppercase tracking-wide">Langkah berikutnya</p>
                                    <p class="mt-1 text-sm font-semibold leading-5">{{ $role['next_action'] }}</p>
                                </div>
                                <a href="{{ $role['url'] }}" wire:navigate class="assessment-report-progress__action mt-4 inline-flex min-h-10 items-center justify-center gap-2 rounded-lg px-4 py-2 text-sm font-bold">
                                    Kerjakan Sekarang
                                    <x-filament::icon icon="heroicon-o-arrow-right" class="h-4 w-4" />
                                </a>
                            </article>
                        @endforeach
                    </div>
                </section>
            @endforeach
        @endif
    </div>
</x-filament-panels::page>
