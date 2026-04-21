<x-filament-panels::page>
    <div class="mx-auto w-full space-y-4">
        <section class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-900">
            <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <div class="min-w-0">
                    <h2 class="text-base font-semibold text-gray-900 dark:text-white">
                        Bacaan: {{ $this->getRecord()->siswa?->nama ?? 'Murid' }}
                    </h2>
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">
                        Riwayat bacaan menyimpan semua tanggal simakan, nilai PP, KL, TJ, MJ, catatan, dan siapa penyimaknya.
                    </p>
                </div>

                @include('filament.resources.boarding-pencapaian-resource.partials.navigation', [
                    'record' => $this->getRecord(),
                    'active' => 'bacaan',
                ])
            </div>
        </section>

        <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <article class="rounded-2xl border border-slate-200 bg-slate-50 p-4 shadow-sm dark:border-slate-500/20 dark:bg-slate-500/10">
                <div class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-700 dark:text-slate-300">Total Simakan</div>
                <div class="mt-2 text-2xl font-semibold text-slate-900 dark:text-white">{{ number_format((int) ($summaryMetrics['total_sessions'] ?? 0), 0, ',', '.') }}</div>
                <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">Jumlah seluruh riwayat bacaan yang sudah disimpan.</p>
            </article>

            <article class="rounded-2xl border border-sky-200 bg-sky-50 p-4 shadow-sm dark:border-sky-500/20 dark:bg-sky-500/10">
                <div class="text-xs font-semibold uppercase tracking-[0.16em] text-sky-700 dark:text-sky-300">Terakhir Disimak</div>
                <div class="mt-2 text-2xl font-semibold text-slate-900 dark:text-white">{{ $summaryMetrics['latest_date'] ?? '-' }}</div>
                <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">Tanggal simakan terbaru yang tercatat.</p>
            </article>

            <article class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4 shadow-sm dark:border-emerald-500/20 dark:bg-emerald-500/10">
                <div class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-700 dark:text-emerald-300">Nilai Terakhir</div>
                <div class="mt-2 text-lg font-semibold text-slate-900 dark:text-white">{{ $summaryMetrics['latest_grades'] ?? 'Belum ada riwayat' }}</div>
                <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">Ringkasan PP, KL, TJ, dan MJ pada simakan terakhir.</p>
            </article>

            <article class="rounded-2xl border border-amber-200 bg-amber-50 p-4 shadow-sm dark:border-amber-500/20 dark:bg-amber-500/10">
                <div class="text-xs font-semibold uppercase tracking-[0.16em] text-amber-700 dark:text-amber-300">Penyimak Terakhir</div>
                <div class="mt-2 text-lg font-semibold text-slate-900 dark:text-white">{{ $summaryMetrics['latest_reviewer'] ?? '-' }}</div>
                <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">Bisa berupa akun sistem atau nama manual.</p>
            </article>
        </section>

        <section class="rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-gray-900">
            <div class="w-full overflow-x-auto">
                {{ $this->getTable()->render() }}
            </div>
        </section>
    </div>
</x-filament-panels::page>
