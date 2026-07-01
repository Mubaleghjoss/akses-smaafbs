<x-filament-panels::page>
    @php
        $serverPull = $this->serverDataPullStatus();
        $brandRows = [
            ['label' => 'Nama situs', 'value' => $this->data['site_name'] ?? '-'],
            ['label' => 'PWA pendek', 'value' => $this->data['pwa_short_name'] ?? '-'],
            ['label' => 'Theme color', 'value' => $this->data['theme_color'] ?? '-'],
        ];
    @endphp

    <form wire:submit="save" class="space-y-5">
        <div class="grid gap-4 xl:grid-cols-2">
            <x-filament::section
                heading="Pengaturan utama"
                description="Nilai aktif yang paling sering dicek sebelum menyimpan perubahan situs."
                icon="heroicon-o-adjustments-horizontal"
                collapsible
                collapsed
                persist-collapsed
                collapse-id="pengaturan-utama"
            >
                <div class="grid gap-3 sm:grid-cols-3">
                    @foreach ($brandRows as $row)
                        <div class="min-w-0 rounded-lg border border-gray-200 bg-white p-3 shadow-sm dark:border-white/10 dark:bg-gray-900/50">
                            <p class="text-xs font-medium uppercase tracking-normal text-gray-500 dark:text-gray-400">{{ $row['label'] }}</p>
                            <p class="mt-2 break-words text-sm font-medium text-gray-950 dark:text-white">{{ $row['value'] }}</p>
                        </div>
                    @endforeach
                </div>
            </x-filament::section>

            <x-filament::section
                heading="Sinkron data API"
                description="Pengaturan sumber API dibuat singkat: aktif/nonaktif dan domain server."
                icon="heroicon-o-server-stack"
                collapsible
                collapsed
                persist-collapsed
                collapse-id="sinkron-data-api-ringkasan"
            >
                <x-slot name="afterHeader">
                    <x-filament::badge :color="$serverPull['color']">
                        {{ $serverPull['label'] }}
                    </x-filament::badge>
                </x-slot>

                <div class="grid gap-3 sm:grid-cols-2">
                    <div class="min-w-0 rounded-lg border border-gray-200 bg-white p-3 shadow-sm dark:border-white/10 dark:bg-gray-900/50">
                        <p class="text-xs font-medium uppercase tracking-normal text-gray-500 dark:text-gray-400">Domain server</p>
                        <p class="mt-2 break-all text-sm font-medium leading-5 text-gray-950 dark:text-white">{{ $serverPull['domain'] }}</p>
                    </div>

                    <div class="min-w-0 rounded-lg border border-gray-200 bg-white p-3 shadow-sm dark:border-white/10 dark:bg-gray-900/50">
                        <p class="text-xs font-medium uppercase tracking-normal text-gray-500 dark:text-gray-400">Status</p>
                        <p class="mt-2 break-words text-sm font-medium leading-5 text-gray-950 dark:text-white">
                            {{ $serverPull['pull_ready'] ? 'Siap tarik data server' : ($serverPull['pull_errors'][0] ?? 'Konfigurasi lokal belum lengkap') }}
                        </p>
                    </div>
                </div>
            </x-filament::section>
        </div>

        {{ $this->form }}

        <div class="sticky bottom-4 z-10 flex flex-wrap justify-end gap-2 rounded-lg border border-gray-200 bg-white/95 p-3 shadow-sm backdrop-blur dark:border-white/10 dark:bg-gray-900/95">
            <x-filament::button type="submit" icon="heroicon-o-check-circle" size="sm">
                Simpan Pengaturan
            </x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
