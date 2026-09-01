<x-filament-panels::page>
    @php($periods = $this->getProgressPeriods())

    <div class="assessment-report-progress space-y-6">
        <section class="assessment-report-progress__hero rounded-2xl border p-4 shadow-sm sm:p-6">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div class="min-w-0">
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

        @forelse ($periods as $period)
            <section class="assessment-report-progress__period rounded-2xl border p-4 shadow-sm sm:p-6" aria-labelledby="progress-period-{{ $period['id'] }}">
                <header class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="assessment-report-progress__type rounded-full px-2.5 py-1 text-xs font-bold">{{ $period['type_label'] }}</span>
                            <span class="assessment-report-progress__workflow rounded-full px-2.5 py-1 text-xs font-semibold">{{ $period['status_label'] }}</span>
                        </div>
                        <h2 id="progress-period-{{ $period['id'] }}" class="assessment-report-progress__period-title mt-2 break-words text-lg font-bold sm:text-xl">{{ $period['name'] }}</h2>
                    </div>
                    <div class="text-right">
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

                <div class="mt-5 grid gap-4 lg:grid-cols-2">
                    @foreach ($period['roles'] as $role)
                        <article class="assessment-report-progress__role flex min-w-0 flex-col rounded-xl border p-4">
                            <div class="flex min-w-0 items-start justify-between gap-3">
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
        @empty
            <section class="assessment-report-progress__empty rounded-2xl border border-dashed p-6 text-center">
                <x-filament::icon icon="heroicon-o-check-badge" class="mx-auto h-10 w-10" />
                <h2 class="mt-3 font-bold">Tidak ada periode aktif dalam cakupan Anda</h2>
                <p class="mt-1 text-sm leading-6">Tidak ada pekerjaan rapor yang perlu ditampilkan saat ini. Periode draf dan periode yang sudah diterbitkan tidak masuk antrean progres.</p>
            </section>
        @endforelse
    </div>
</x-filament-panels::page>
