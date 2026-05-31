<x-filament-panels::page>
    <div class="mx-auto w-full space-y-4">
        <section class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-900">
            <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <div class="min-w-0">
                    <h2 class="text-base font-semibold text-gray-900 dark:text-white">
                        Bacaan: {{ $this->getRecord()->siswa?->nama ?? 'Murid' }}
                    </h2>
                </div>

                @include('filament.resources.boarding-pencapaian-resource.partials.navigation', [
                    'record' => $this->getRecord(),
                    'active' => 'bacaan',
                ])
            </div>
        </section>

        @include('filament.resources.boarding-pencapaian-resource.partials.boarding-menu', [
            'record' => $this->getRecord(),
            'active' => 'bacaan',
        ])

        <section class="rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-gray-900">
            <div class="grid divide-y divide-gray-200 text-sm dark:divide-white/10 sm:grid-cols-4 sm:divide-x sm:divide-y-0">
                <div class="p-3">
                    <div class="text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Total</div>
                    <div class="mt-1 text-lg font-semibold text-gray-950 dark:text-white">{{ number_format((int) ($summaryMetrics['total_sessions'] ?? 0), 0, ',', '.') }}</div>
                </div>
                <div class="p-3">
                    <div class="text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Terakhir</div>
                    <div class="mt-1 text-lg font-semibold text-gray-950 dark:text-white">{{ $summaryMetrics['latest_date'] ?? '-' }}</div>
                </div>
                <div class="p-3">
                    <div class="text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Nilai</div>
                    <div class="mt-1 font-semibold text-gray-950 dark:text-white">{{ $summaryMetrics['latest_grades'] ?? 'Belum ada' }}</div>
                </div>
                <div class="p-3">
                    <div class="text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Penyimak</div>
                    <div class="mt-1 font-semibold text-gray-950 dark:text-white">{{ $summaryMetrics['latest_reviewer'] ?? '-' }}</div>
                </div>
            </div>
        </section>

        <section class="rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-gray-900">
            <div class="border-b border-gray-200 p-4 dark:border-white/10">
                <h3 class="text-base font-semibold text-gray-900 dark:text-white">Tabel Bacaan</h3>
            </div>
            <div class="w-full overflow-x-auto">
                {{ $this->getTable()->render() }}
            </div>
        </section>
    </div>
</x-filament-panels::page>
