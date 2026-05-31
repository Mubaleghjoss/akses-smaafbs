<x-filament-panels::page>
    <div class="mx-auto w-full space-y-4">
        <section class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-900">
            <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <div class="min-w-0">
                    <h2 class="text-base font-semibold text-gray-900 dark:text-white">
                        Hafalan: {{ $this->getRecord()->siswa?->nama ?? 'Murid' }}
                    </h2>
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">
                        Pencapaian #{{ $this->getRecord()->getKey() }} | Point nonaktif hanya ditampilkan jika sudah pernah dinilai.
                    </p>
                </div>

                @include('filament.resources.boarding-pencapaian-resource.partials.navigation', [
                    'record' => $this->getRecord(),
                    'active' => 'hafalan',
                ])
            </div>
        </section>

        @include('filament.resources.boarding-pencapaian-resource.partials.boarding-menu', [
            'record' => $this->getRecord(),
            'active' => 'hafalan',
        ])

        <section class="rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-gray-900">
            <div class="w-full overflow-x-auto">
                {{ $this->getTable()->render() }}
            </div>
        </section>
    </div>
</x-filament-panels::page>
