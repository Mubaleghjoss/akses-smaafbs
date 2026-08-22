<div class="min-w-0 rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-900/50">
    <div class="flex flex-wrap items-start justify-between gap-2">
        <div class="min-w-0 flex-1">
            <p class="break-words font-medium text-gray-950 dark:text-white">{{ $row['judul'] }}</p>

            <div class="mt-2 flex flex-wrap gap-2">
                <x-filament::badge :color="$row['module_color']">{{ $row['module_label'] }}</x-filament::badge>
                <x-filament::badge color="gray">{{ $row['jenis'] }}</x-filament::badge>
                <x-filament::badge :color="$row['status_color']">{{ $row['status_label'] }}</x-filament::badge>

                @if (($row['sync_mode_label'] ?? '-') !== '-')
                    <x-filament::badge :color="$row['sync_mode_color']">{{ $row['sync_mode_label'] }}</x-filament::badge>
                @endif

                @foreach (($row['asset_badges'] ?? []) as $assetBadge)
                    <x-filament::badge :color="$assetBadge['color']">{{ $assetBadge['label'] }}</x-filament::badge>
                @endforeach
            </div>

            <p class="mt-2 break-words text-xs leading-5 text-gray-500 dark:text-gray-400">{{ $row['context'] }}</p>
        </div>

        <p class="text-sm font-semibold text-gray-700 dark:text-gray-200">{{ $row['progress'] }}%</p>
    </div>

    <p class="mt-3 break-words text-sm leading-6 text-gray-600 dark:text-gray-400">{{ $row['message'] }}</p>

    @if ($mode === 'active')
        <div class="mt-3">
            <div class="h-2 overflow-hidden rounded-full bg-gray-100 dark:bg-white/10">
                <div class="h-full rounded-full bg-primary-500" style="width: {{ max(8, min(100, $row['progress'])) }}%"></div>
            </div>
        </div>
    @endif

    <div class="mt-3 flex flex-wrap items-center justify-between gap-2">
        <span class="break-words text-xs text-gray-500 dark:text-gray-400">{{ $row['timestamp_label'] }}: {{ $row['timestamp'] }}</span>

        <div class="flex flex-wrap gap-2">
            @if ($row['has_uploadable_files'])
                <x-filament::button
                    type="button"
                    color="primary"
                    size="sm"
                    icon="heroicon-o-cloud-arrow-up"
                    wire:click="uploadGoogleDriveNow('{{ $row['source'] }}', {{ $row['id'] }})"
                >
                    Upload Sekarang
                </x-filament::button>
            @endif

            @if ($row['drive_url'])
                <x-filament::button tag="a" :href="$row['drive_url']" color="gray" outlined size="sm" icon="heroicon-o-arrow-top-right-on-square" target="_blank">
                    Buka Drive
                </x-filament::button>
            @endif

            @if ($row['admin_url'])
                <x-filament::button tag="a" :href="$row['admin_url']" color="gray" outlined size="sm" icon="heroicon-o-pencil-square">
                    Buka
                </x-filament::button>
            @endif
        </div>
    </div>
</div>
