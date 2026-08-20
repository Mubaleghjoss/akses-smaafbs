<x-filament-panels::page>
    <div class="space-y-6">
        @if ($previewToken)
            <div class="grid gap-4 md:grid-cols-4">
                @foreach ($counts as $status => $count)
                    <x-filament::section compact>
                        <x-slot name="heading">{{ str($status)->replace('_', ' ')->title() }}</x-slot>
                        <p class="text-2xl font-bold">{{ $count }}</p>
                    </x-filament::section>
                @endforeach
            </div>

            <x-filament::section heading="Status Preview">
                <div class="flex flex-wrap gap-4 text-sm text-gray-600 dark:text-gray-300">
                    <span>Preview tersedia untuk diterapkan.</span>
                    @if ($previewExpiresAt)
                        <span>Kedaluwarsa: {{ $previewExpiresAt }}</span>
                    @endif
                </div>
            </x-filament::section>

            <x-filament::section heading="Ringkasan Field">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead><tr><th class="p-2">Field</th><th class="p-2">Jumlah perubahan</th></tr></thead>
                        <tbody>
                            @forelse ($fieldSummary as $field => $count)
                                <tr class="border-t border-gray-200 dark:border-white/10"><td class="p-2">{{ $field }}</td><td class="p-2">{{ $count }}</td></tr>
                            @empty
                                <tr><td class="p-2 text-gray-500" colspan="2">Tidak ada perubahan field.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-filament::section>

            <x-filament::section heading="Item Preview">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead><tr><th class="p-2">Sumber</th><th class="p-2">Target</th><th class="p-2">Status</th><th class="p-2">Field berubah</th><th class="p-2">Alasan konflik</th></tr></thead>
                        <tbody>
                            @forelse ($items as $item)
                                <tr class="border-t border-gray-200 dark:border-white/10">
                                    <td class="p-2">{{ $item['source_id'] ?? '-' }}</td>
                                    <td class="p-2">{{ $item['target_id'] ?? '-' }}</td>
                                    <td class="p-2"><x-filament::badge>{{ str($item['status'])->replace('_', ' ')->title() }}</x-filament::badge></td>
                                    <td class="p-2">{{ implode(', ', $item['changed_fields']) ?: '-' }}</td>
                                    <td class="p-2">{{ $item['reason'] ?? '-' }}</td>
                                </tr>
                            @empty
                                <tr><td class="p-2 text-gray-500" colspan="5">Tidak ada item untuk ditampilkan.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-filament::section>
        @else
            <x-filament::section heading="Push Data Siswa ke Server">
                <p class="text-sm text-gray-600 dark:text-gray-300">Muat preview terlebih dahulu untuk melihat jumlah dan nama field yang akan diproses. Nilai data siswa tidak ditampilkan di halaman ini.</p>
            </x-filament::section>
        @endif

        @if ($applyResult)
            <x-filament::section heading="Hasil Push">
                <p class="text-sm text-gray-600 dark:text-gray-300">Push telah diproses. Ringkasan hasil tersedia tanpa menampilkan payload atau nilai sebelum/sesudah.</p>
            </x-filament::section>
        @endif
    </div>
</x-filament-panels::page>
