<x-filament-panels::page>
    <div class="mx-auto w-full space-y-4">
        <section class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-900">
            <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
                <div class="min-w-0">
                    <h2 class="text-base font-semibold text-gray-900 dark:text-white">
                        Update antropometri semua murid aktif
                    </h2>
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">
                        Gunakan halaman ini untuk memperbarui berat, tinggi, dan lingkar kepala murid tanpa membuka form UKS satu per satu.
                    </p>
                </div>

                <div class="flex flex-wrap gap-2 md:justify-end">
                    <x-filament::button
                        tag="a"
                        :href="\App\Filament\Resources\UksRecordResource::getUrl('index')"
                        color="gray"
                        icon="heroicon-o-arrow-left"
                        size="sm"
                    >
                        Kembali ke Rekam UKS
                    </x-filament::button>
                </div>
            </div>
        </section>

        <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <article class="rounded-2xl border border-amber-200 bg-amber-50 p-4 shadow-sm dark:border-amber-500/20 dark:bg-amber-500/10">
                <div class="text-xs font-semibold uppercase tracking-[0.16em] text-amber-700 dark:text-amber-300">Rerata Berat</div>
                <div class="mt-2 text-2xl font-semibold text-slate-900 dark:text-white">
                    {{ number_format((float) ($summaryMetrics['average_weight'] ?? 0), 2, ',', '.') }} <span class="text-sm font-medium text-slate-500 dark:text-slate-400">kg</span>
                </div>
                <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">
                    Berdasarkan data terbaru murid aktif.
                </p>
            </article>

            <article class="rounded-2xl border border-sky-200 bg-sky-50 p-4 shadow-sm dark:border-sky-500/20 dark:bg-sky-500/10">
                <div class="text-xs font-semibold uppercase tracking-[0.16em] text-sky-700 dark:text-sky-300">Rerata Tinggi</div>
                <div class="mt-2 text-2xl font-semibold text-slate-900 dark:text-white">
                    {{ number_format((float) ($summaryMetrics['average_height'] ?? 0), 2, ',', '.') }} <span class="text-sm font-medium text-slate-500 dark:text-slate-400">cm</span>
                </div>
                <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">
                    Berdasarkan data terbaru murid aktif.
                </p>
            </article>

            <article class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4 shadow-sm dark:border-emerald-500/20 dark:bg-emerald-500/10">
                <div class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-700 dark:text-emerald-300">Rerata Lingkar Kepala</div>
                <div class="mt-2 text-2xl font-semibold text-slate-900 dark:text-white">
                    {{ number_format((float) ($summaryMetrics['average_head_circumference'] ?? 0), 2, ',', '.') }} <span class="text-sm font-medium text-slate-500 dark:text-slate-400">cm</span>
                </div>
                <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">
                    Berdasarkan data terbaru murid aktif.
                </p>
            </article>

            <article class="rounded-2xl border border-rose-200 bg-rose-50 p-4 shadow-sm dark:border-rose-500/20 dark:bg-rose-500/10">
                <div class="text-xs font-semibold uppercase tracking-[0.16em] text-rose-700 dark:text-rose-300">Belum Diukur Bulan Ini</div>
                <div class="mt-2 text-2xl font-semibold text-slate-900 dark:text-white">
                    {{ number_format((int) ($summaryMetrics['unmeasured_this_month'] ?? 0), 0, ',', '.') }}
                    <span class="text-sm font-medium text-slate-500 dark:text-slate-400">dari {{ number_format((int) ($summaryMetrics['total_students'] ?? 0), 0, ',', '.') }} murid</span>
                </div>
                <div class="mt-3">
                    <x-filament::button
                        tag="a"
                        :href="\App\Filament\Resources\UksRecordResource::getUrl('anthropometry', ['anthropometry_filter' => 'belum_bulan_ini'])"
                        color="danger"
                        size="sm"
                        icon="heroicon-o-funnel"
                    >
                        Lihat daftar
                    </x-filament::button>
                </div>
            </article>
        </section>

        <section class="rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-gray-900">
            <div class="w-full overflow-x-auto">
                {{ $this->getTable()->render() }}
            </div>
        </section>
    </div>
</x-filament-panels::page>
