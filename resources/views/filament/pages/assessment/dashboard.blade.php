<x-filament-panels::page>
    @php
        $metrics = $this->getMetrics();
        $settingCards = $this->getSettingCards();
    @endphp

    <div class="space-y-6">
        <section class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm sm:p-5 dark:border-white/10 dark:bg-gray-900">
            <div class="grid min-w-0 gap-4 md:grid-cols-[minmax(0,1fr)_minmax(16rem,24rem)] md:items-end">
                <div class="min-w-0">
                    <h2 class="text-lg font-bold text-gray-950 dark:text-white">Pusat Penilaian ASTS–ASAS</h2>
                    <p class="mt-1 text-sm leading-6 text-gray-600 dark:text-gray-300">Pilih periode, lalu buka menu atau status yang perlu ditangani.</p>
                </div>
                <label class="block min-w-0">
                    <span class="text-sm font-semibold text-gray-800 dark:text-gray-200">Periode yang dipantau</span>
                    <select wire:model.live="periodId" class="mt-2 w-full min-w-0 rounded-lg border-gray-300 text-sm dark:border-white/10 dark:bg-gray-950">
                        @forelse ($this->getPeriodOptions() as $id => $label)
                            <option value="{{ $id }}">{{ $label }}</option>
                        @empty
                            <option value="">Belum ada periode</option>
                        @endforelse
                    </select>
                </label>
            </div>
        </section>

        <section aria-labelledby="assessment-menu-title">
            <div class="mb-3">
                <h2 id="assessment-menu-title" class="text-base font-bold text-gray-950 dark:text-white">Menu Pengaturan</h2>
                <p class="mt-1 text-sm text-gray-500">Semua pengaturan penting tersedia langsung dari kartu berikut.</p>
            </div>
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3">
                @foreach ($settingCards as $card)
                    <article class="flex min-w-0 flex-col rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-900">
                        <div class="flex min-w-0 items-start justify-between gap-3">
                            <h3 class="min-w-0 break-words font-bold text-gray-950 dark:text-white">{{ $card['title'] }}</h3>
                            <span class="shrink-0 rounded-full bg-gray-100 px-2.5 py-1 text-xs font-semibold text-gray-700 dark:bg-white/10 dark:text-gray-200">{{ $card['value'] }}</span>
                        </div>
                        <p class="mt-1 text-xs font-medium text-gray-500">{{ $card['caption'] }}</p>
                        <div class="mt-3 flex-1 space-y-1.5 text-sm leading-5 text-gray-600 dark:text-gray-300">
                            @foreach ($card['points'] as $point)
                                <p>{{ $point }}</p>
                            @endforeach
                        </div>
                        @if ($card['url'])
                            <a href="{{ $card['url'] }}" wire:navigate class="mt-4 inline-flex min-h-11 items-center justify-center rounded-lg border border-primary-600 px-4 py-2 text-sm font-bold text-primary-700 transition-colors hover:bg-primary-50 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 dark:text-primary-300 dark:ring-offset-gray-900 dark:hover:bg-primary-950/30">
                                {{ $card['action'] }}
                            </a>
                        @else
                            <span class="mt-4 inline-flex min-h-11 items-center justify-center rounded-lg bg-gray-100 px-4 py-2 text-sm font-semibold text-gray-500 dark:bg-white/5 dark:text-gray-400">Akses tidak tersedia</span>
                        @endif
                    </article>
                @endforeach
            </div>
        </section>

        <section aria-labelledby="assessment-readiness-title">
            <div class="mb-3">
                <h2 id="assessment-readiness-title" class="text-base font-bold text-gray-950 dark:text-white">Kesiapan Fondasi</h2>
                <p class="mt-1 text-sm text-gray-500">Ringkasan data yang dibutuhkan sebelum periode dibuka.</p>
            </div>
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3">
                @foreach ($this->getReadiness() as $item)
                    <article class="min-w-0 rounded-xl border bg-white p-4 shadow-sm dark:bg-gray-900 {{ $item['ready'] ? 'border-success-300 dark:border-success-500/30' : 'border-warning-300 dark:border-warning-500/30' }}">
                        <div class="flex items-start justify-between gap-3">
                            <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-200">{{ $item['label'] }}</h3>
                            <strong class="text-xl text-gray-950 dark:text-white">{{ $item['count'] }}</strong>
                        </div>
                        <p class="mt-2 text-xs font-semibold {{ $item['ready'] ? 'text-success-700 dark:text-success-300' : 'text-warning-700 dark:text-warning-300' }}">{{ $item['ready'] ? 'Siap digunakan' : 'Perlu dilengkapi' }}</p>
                    </article>
                @endforeach
            </div>
        </section>

        <section class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm sm:p-5 dark:border-white/10 dark:bg-gray-900" aria-labelledby="assessment-status-title">
            <div class="flex min-w-0 flex-wrap items-start justify-between gap-3">
                <div class="min-w-0">
                    <h2 id="assessment-status-title" class="font-bold text-gray-950 dark:text-white">Status Pengumpulan</h2>
                    @if ($metrics['period'])
                        <p class="mt-1 text-sm text-gray-500">{{ $metrics['student_count'] }} siswa · {{ $metrics['class_count'] }} kelas · {{ $metrics['assignment_count'] }} penugasan</p>
                    @else
                        <p class="mt-1 text-sm text-gray-500">Belum ada periode yang dapat dipantau.</p>
                    @endif
                </div>
                @if ($metrics['period'])
                    <span class="rounded-full bg-gray-100 px-3 py-1 text-xs font-semibold text-gray-700 dark:bg-white/10 dark:text-gray-200">{{ $metrics['period']->status->label() }}</span>
                @endif
            </div>
            @if ($metrics['period'])
                <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3">
                    @foreach ($metrics['cards'] as $card)
                        <a href="{{ $card['url'] }}" wire:navigate class="min-h-11 rounded-lg border border-gray-200 p-3 transition-colors hover:border-primary-400 hover:bg-primary-50 focus:outline-none focus:ring-2 focus:ring-primary-500 dark:border-white/10 dark:hover:bg-primary-950/20">
                            <span class="text-sm font-semibold text-gray-700 dark:text-gray-200">{{ $card['label'] }}</span>
                            <strong class="mt-1 block text-xl text-gray-950 dark:text-white">{{ $card['count'] }}</strong>
                        </a>
                    @endforeach
                </div>
            @endif
        </section>

        <section class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm sm:p-5 dark:border-white/10 dark:bg-gray-900" aria-labelledby="assessment-activity-title">
            <div class="flex min-w-0 flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div class="min-w-0">
                    <h2 id="assessment-activity-title" class="font-bold text-gray-950 dark:text-white">Aktivitas Terbaru</h2>
                    <p class="mt-1 text-sm text-gray-500">Jejak perubahan pada periode terpilih.</p>
                </div>
                @if (\App\Filament\Resources\AssessmentAuditLogResource::canViewAny())
                    <a href="{{ \App\Filament\Resources\AssessmentAuditLogResource::getUrl() }}" wire:navigate class="inline-flex min-h-11 items-center justify-center rounded-lg border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary-500 dark:border-white/15 dark:text-gray-200 dark:hover:bg-white/5">Buka Semua Histori</a>
                @endif
            </div>
            <div class="assessment-audit-card-grid mt-4 grid grid-cols-1 gap-3 lg:grid-cols-2">
                @forelse ($this->getRecentAuditRows() as $row)
                    <article class="min-w-0 rounded-lg border border-gray-200 p-3 dark:border-white/10">
                        <div class="flex items-start justify-between gap-3">
                            <h3 class="break-words text-sm font-bold text-gray-950 dark:text-white">{{ $row['event'] }}</h3>
                            <time class="shrink-0 text-xs text-gray-500">{{ $row['time'] }}</time>
                        </div>
                        <p class="mt-2 text-xs leading-5 text-gray-600 dark:text-gray-300">{{ $row['actor'] }} · {{ $row['period'] }} · {{ $row['subject'] }}</p>
                        <p class="assessment-audit-card__points mt-1 break-words text-xs leading-5 text-gray-500">{{ $row['reason'] }}</p>
                        <button type="button" wire:click="mountAction('viewAuditLog', { record: {{ $row['id'] }} })" class="mt-3 inline-flex min-h-11 items-center justify-center rounded-lg border border-gray-300 px-3 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary-500 dark:border-white/15 dark:text-gray-200 dark:hover:bg-white/5">
                            Lihat Detail
                        </button>
                    </article>
                @empty
                    <p class="rounded-lg border border-dashed border-gray-300 p-4 text-sm text-gray-500 dark:border-white/15">Belum ada aktivitas pada periode ini.</p>
                @endforelse
            </div>
        </section>
    </div>
</x-filament-panels::page>
