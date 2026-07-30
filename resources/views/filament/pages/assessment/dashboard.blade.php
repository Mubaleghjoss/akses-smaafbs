<x-filament-panels::page>
    @php
        $metrics = $this->getMetrics();
        $settingCards = $this->getSettingCards();
    @endphp

    <div class="space-y-6">
        <section class="overflow-hidden rounded-2xl border border-primary-200 bg-gradient-to-br from-primary-50 via-white to-white shadow-sm dark:border-primary-500/20 dark:from-primary-950/30 dark:via-gray-900 dark:to-gray-900">
            <div class="grid gap-5 p-4 sm:p-6 lg:grid-cols-[minmax(0,1fr)_minmax(16rem,24rem)] lg:items-center">
                <div class="min-w-0">
                    <span class="inline-flex items-center gap-2 rounded-full bg-primary-100 px-3 py-1 text-xs font-bold text-primary-700 dark:bg-primary-500/15 dark:text-primary-300">
                        <x-filament::icon icon="heroicon-o-cog-6-tooth" class="h-4 w-4" />
                        Pusat Pengaturan
                    </span>
                    <h2 class="mt-3 break-words text-xl font-bold text-gray-950 sm:text-2xl dark:text-white">Siapkan Penilaian secara bertahap dan terukur</h2>
                    <p class="mt-2 max-w-2xl text-sm leading-6 text-gray-600 dark:text-gray-300">
                        Mulai dari master resmi, periode, dan komponen. Setiap fungsi memiliki kartu sendiri agar status dan tindakan mudah dibaca di layar HP.
                    </p>
                </div>

                <label class="block min-w-0 rounded-xl border border-gray-200 bg-white/90 p-3 shadow-sm dark:border-white/10 dark:bg-gray-950/70">
                    <span class="text-xs font-bold uppercase tracking-wide text-gray-500 dark:text-gray-400">Periode yang dipantau</span>
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

        <section>
            <div class="mb-3">
                <h2 class="text-base font-bold text-gray-950 dark:text-white">Menu Pengaturan</h2>
                <p class="mt-1 text-sm text-gray-500">Kartu menampilkan jumlah data dan membuka halaman pengelolaan yang sesuai.</p>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
                @foreach ($settingCards as $card)
                    @php
                        $toneClasses = match ($card['tone']) {
                            'success' => 'bg-success-50 text-success-700 dark:bg-success-500/10 dark:text-success-300',
                            'warning' => 'bg-warning-50 text-warning-700 dark:bg-warning-500/10 dark:text-warning-300',
                            'info' => 'bg-info-50 text-info-700 dark:bg-info-500/10 dark:text-info-300',
                            'gray' => 'bg-gray-100 text-gray-700 dark:bg-white/10 dark:text-gray-300',
                            default => 'bg-primary-50 text-primary-700 dark:bg-primary-500/10 dark:text-primary-300',
                        };
                    @endphp
                    <article class="flex min-w-0 flex-col rounded-2xl border border-gray-200 bg-white p-4 shadow-sm transition sm:p-5 dark:border-white/10 dark:bg-gray-900 {{ $card['url'] ? 'hover:-translate-y-0.5 hover:border-primary-400 hover:shadow-md' : 'opacity-70' }}">
                        <div class="flex min-w-0 items-start justify-between gap-3">
                            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl {{ $toneClasses }}">
                                <x-filament::icon :icon="$card['icon']" class="h-6 w-6" />
                            </span>
                            <div class="min-w-0 text-right">
                                <p class="break-words text-xl font-bold text-gray-950 dark:text-white">{{ $card['value'] }}</p>
                                <p class="break-words text-[11px] leading-4 text-gray-500">{{ $card['caption'] }}</p>
                            </div>
                        </div>

                        <h3 class="mt-4 break-words text-base font-bold text-gray-950 dark:text-white">{{ $card['title'] }}</h3>
                        <p class="mt-2 flex-1 break-words text-sm leading-6 text-gray-600 dark:text-gray-300">{{ $card['description'] }}</p>

                        @if ($card['url'])
                            <a href="{{ $card['url'] }}" wire:navigate class="mt-5 inline-flex min-h-10 w-full items-center justify-center gap-2 rounded-xl bg-primary-600 px-4 py-2 text-sm font-bold text-white transition hover:bg-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 dark:ring-offset-gray-900">
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

        <section>
            <div class="mb-3">
                <h2 class="text-base font-bold text-gray-950 dark:text-white">Kesiapan Fondasi</h2>
                <p class="mt-1 text-sm text-gray-500">Empat data dasar ini harus tersedia sebelum periode dibuka.</p>
            </div>
            <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
                @foreach ($this->getReadiness() as $item)
                    <article class="min-w-0 rounded-xl border p-3 shadow-sm sm:p-4 {{ $item['ready'] ? 'border-success-200 bg-success-50 dark:border-success-500/30 dark:bg-success-950/20' : 'border-warning-200 bg-warning-50 dark:border-warning-500/30 dark:bg-warning-950/20' }}">
                        <div class="flex min-w-0 items-center gap-3">
                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg {{ $item['ready'] ? 'bg-success-100 text-success-700 dark:bg-success-500/15 dark:text-success-300' : 'bg-warning-100 text-warning-700 dark:bg-warning-500/15 dark:text-warning-300' }}">
                                <x-filament::icon :icon="$item['ready'] ? 'heroicon-o-check-circle' : 'heroicon-o-exclamation-triangle'" class="h-5 w-5" />
                            </span>
                            <div class="min-w-0">
                                <p class="break-words text-[11px] font-semibold uppercase tracking-wide text-gray-500">{{ $item['label'] }}</p>
                                <p class="mt-0.5 text-xl font-bold text-gray-950 dark:text-white">{{ $item['count'] }}</p>
                            </div>
                        </div>
                        <p class="mt-3 text-xs font-semibold {{ $item['ready'] ? 'text-success-700 dark:text-success-300' : 'text-warning-700 dark:text-warning-300' }}">
                            {{ $item['ready'] ? 'Siap digunakan' : 'Perlu dilengkapi' }}
                        </p>
                    </article>
                @endforeach
            </div>
        </section>

        @if ($metrics['period'])
            <section class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm sm:p-5 dark:border-white/10 dark:bg-gray-900">
                <div class="flex min-w-0 flex-wrap items-start justify-between gap-3">
                    <div class="min-w-0">
                        <h2 class="font-bold text-gray-950 dark:text-white">Status Pengumpulan</h2>
                        <p class="mt-1 break-words text-sm text-gray-500">
                            {{ $metrics['student_count'] }} siswa · {{ $metrics['class_count'] }} kelas · {{ $metrics['assignment_count'] }} penugasan
                        </p>
                    </div>
                    <span class="rounded-full bg-gray-100 px-3 py-1 text-xs font-semibold text-gray-700 dark:bg-white/10 dark:text-gray-200">
                        {{ $metrics['period']->status->label() }}
                    </span>
                </div>
                <div class="mt-4 grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-5">
                    @foreach ($metrics['cards'] as $card)
                        <a href="{{ $card['url'] }}" wire:navigate class="min-w-0 rounded-xl border border-gray-200 bg-gray-50 p-3 transition hover:border-primary-400 hover:bg-primary-50 sm:p-4 dark:border-white/10 dark:bg-gray-950 dark:hover:border-primary-500/50 dark:hover:bg-primary-950/20">
                            <p class="break-words text-[11px] font-semibold uppercase tracking-wide text-gray-500">{{ $card['label'] }}</p>
                            <p class="mt-1 text-xl font-bold text-gray-950 sm:text-2xl dark:text-white">{{ $card['count'] }}</p>
                            <p class="mt-2 break-words text-xs font-semibold text-primary-600 dark:text-primary-300">Buka daftar terfilter →</p>
                        </a>
                    @endforeach
                </div>
            </section>
        @else
            <section class="rounded-2xl border border-dashed border-gray-300 p-6 text-center dark:border-white/15">
                <span class="mx-auto flex h-11 w-11 items-center justify-center rounded-xl bg-gray-100 text-gray-600 dark:bg-white/10 dark:text-gray-300">
                    <x-filament::icon icon="heroicon-o-calendar-days" class="h-6 w-6" />
                </span>
                <h2 class="mt-3 font-semibold text-gray-950 dark:text-white">Belum ada periode Penilaian</h2>
                <p class="mt-2 text-sm text-gray-500">Mulai dari impor master resmi, buat skema 100%, lalu buka periode.</p>
            </section>
        @endif

        <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-gray-900">
            <div class="border-b border-gray-200 p-4 dark:border-white/10">
                <h2 class="font-bold text-gray-950 dark:text-white">Aktivitas Terbaru</h2>
                <p class="mt-1 text-sm text-gray-500">Jejak perubahan pada periode yang dipilih.</p>
            </div>
            <div class="divide-y divide-gray-200 dark:divide-white/10">
                @forelse ($this->getRecentAuditRows() as $row)
                    <article class="grid min-w-0 gap-1 p-4 sm:grid-cols-[minmax(0,1fr)_auto]">
                        <div class="min-w-0">
                            <p class="break-words font-semibold text-gray-900 dark:text-white">{{ $row['event'] }}</p>
                            <p class="break-words text-sm text-gray-500">{{ $row['actor'] }}{{ $row['reason'] ? ' · '.$row['reason'] : '' }}</p>
                        </div>
                        <time class="text-xs text-gray-500">{{ $row['time'] }}</time>
                    </article>
                @empty
                    <div class="p-8 text-center text-sm text-gray-500">Belum ada aktivitas pada periode ini.</div>
                @endforelse
            </div>
        </section>
    </div>
</x-filament-panels::page>
