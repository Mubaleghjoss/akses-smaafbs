<x-filament-panels::page>
    <div class="mx-auto w-full space-y-4">
        <section class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-900">
            <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <div class="min-w-0">
                    <h2 class="text-base font-semibold text-gray-900 dark:text-white">
                        Makna: {{ $this->getRecord()->siswa?->nama ?? 'Murid' }}
                    </h2>
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">
                        Pantau target makna Al-Quran dan Himpunan Hadits per murid dengan status khatam, sebagian, atau belum diisi.
                    </p>
                </div>

                @include('filament.resources.boarding-pencapaian-resource.partials.navigation', [
                    'record' => $this->getRecord(),
                    'active' => 'makna',
                ])
            </div>
        </section>

        <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <article class="rounded-2xl border border-slate-200 bg-slate-50 p-4 shadow-sm dark:border-slate-500/20 dark:bg-slate-500/10">
                <div class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-700 dark:text-slate-300">Total Target</div>
                <div class="mt-2 text-2xl font-semibold text-slate-900 dark:text-white">{{ number_format((int) ($summaryMetrics['total_targets'] ?? 0), 0, ',', '.') }}</div>
                <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">Jumlah target makna yang dipantau untuk murid ini.</p>
            </article>

            <article class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4 shadow-sm dark:border-emerald-500/20 dark:bg-emerald-500/10">
                <div class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-700 dark:text-emerald-300">Khatam</div>
                <div class="mt-2 text-2xl font-semibold text-slate-900 dark:text-white">{{ number_format((int) ($summaryMetrics['khatam'] ?? 0), 0, ',', '.') }}</div>
                <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">Target yang sudah selesai dituntaskan.</p>
            </article>

            <article class="rounded-2xl border border-amber-200 bg-amber-50 p-4 shadow-sm dark:border-amber-500/20 dark:bg-amber-500/10">
                <div class="text-xs font-semibold uppercase tracking-[0.16em] text-amber-700 dark:text-amber-300">Sebagian</div>
                <div class="mt-2 text-2xl font-semibold text-slate-900 dark:text-white">{{ number_format((int) ($summaryMetrics['partial'] ?? 0), 0, ',', '.') }}</div>
                <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">Masih berjalan dan bisa diisi sisa lembarnya.</p>
            </article>

            <article class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-900">
                <div class="text-xs font-semibold uppercase tracking-[0.16em] text-gray-700 dark:text-gray-300">Belum Diisi</div>
                <div class="mt-2 text-2xl font-semibold text-slate-900 dark:text-white">{{ number_format((int) ($summaryMetrics['blank'] ?? 0), 0, ',', '.') }}</div>
                <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">Target yang masih belum memiliki progres makna.</p>
            </article>
        </section>

        <section class="rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-gray-900">
            <div class="w-full overflow-x-auto">
                {{ $this->getTable()->render() }}
            </div>
        </section>
    </div>
</x-filament-panels::page>
