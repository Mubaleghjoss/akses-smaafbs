<x-filament-panels::page>
    <div class="space-y-6">
        <section class="rounded-xl border border-gray-200 bg-white p-4 dark:border-white/10 dark:bg-gray-900">
            <label class="block max-w-xl text-sm font-semibold">Periode Aktif yang Dipantau
                <select wire:model.live="periodId" class="mt-2 w-full min-w-0 rounded-lg border-gray-300 dark:border-white/10 dark:bg-gray-950">
                    @forelse ($this->getPeriodOptions() as $id => $label)
                        <option value="{{ $id }}">{{ $label }}</option>
                    @empty
                        <option value="">Belum ada periode</option>
                    @endforelse
                </select>
            </label>
        </section>

        <section>
            <h2 class="mb-3 font-bold text-gray-950 dark:text-white">Kesiapan Fondasi</h2>
            <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
                @foreach ($this->getReadiness() as $item)
                    <article class="min-w-0 rounded-xl border p-4 {{ $item['ready'] ? 'border-success-200 bg-success-50 dark:border-success-500/30 dark:bg-success-950/20' : 'border-warning-200 bg-warning-50 dark:border-warning-500/30 dark:bg-warning-950/20' }}">
                        <p class="break-words text-xs font-semibold uppercase tracking-wide text-gray-500">{{ $item['label'] }}</p>
                        <p class="mt-1 text-2xl font-bold text-gray-950 dark:text-white">{{ $item['count'] }}</p>
                        <p class="mt-1 text-xs {{ $item['ready'] ? 'text-success-700' : 'text-warning-700' }}">{{ $item['ready'] ? 'Siap' : 'Perlu dilengkapi' }}</p>
                    </article>
                @endforeach
            </div>
        </section>

        @php($metrics = $this->getMetrics())
        @if ($metrics['period'])
            <section>
                <div class="mb-3 flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <h2 class="font-bold text-gray-950 dark:text-white">Status Pengumpulan</h2>
                        <p class="mt-1 text-sm text-gray-500">{{ $metrics['student_count'] }} siswa · {{ $metrics['class_count'] }} kelas · {{ $metrics['assignment_count'] }} penugasan</p>
                    </div>
                    <span class="rounded-full bg-gray-100 px-3 py-1 text-xs font-semibold dark:bg-white/10">
                        {{ $metrics['period']->status->label() }}
                    </span>
                </div>
                <div class="grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-5">
                    @foreach ($metrics['cards'] as $card)
                        <a href="{{ $card['url'] }}" wire:navigate class="min-w-0 rounded-xl border border-gray-200 bg-white p-4 transition hover:border-primary-400 hover:shadow-sm dark:border-white/10 dark:bg-gray-900">
                            <p class="break-words text-xs font-semibold uppercase tracking-wide text-gray-500">{{ $card['label'] }}</p>
                            <p class="mt-1 text-2xl font-bold text-gray-950 dark:text-white">{{ $card['count'] }}</p>
                            <p class="mt-2 text-xs font-semibold text-primary-600">Buka daftar terfilter →</p>
                        </a>
                    @endforeach
                </div>
            </section>
        @else
            <section class="rounded-xl border border-dashed border-gray-300 p-8 text-center dark:border-white/15">
                <h2 class="font-semibold text-gray-950 dark:text-white">Belum ada periode Penilaian</h2>
                <p class="mt-2 text-sm text-gray-500">Mulai dari impor master resmi, buat skema 100%, lalu buka periode.</p>
            </section>
        @endif

        <section class="rounded-xl border border-gray-200 bg-white dark:border-white/10 dark:bg-gray-900">
            <div class="border-b border-gray-200 p-4 dark:border-white/10"><h2 class="font-bold">Aktivitas Terbaru</h2></div>
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
                    <div class="p-8 text-center text-sm text-gray-500">Belum ada aktivitas.</div>
                @endforelse
            </div>
        </section>
    </div>
</x-filament-panels::page>
