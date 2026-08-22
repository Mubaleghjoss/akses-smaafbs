@php
    $status = $page->serverDataPullRunStatus;
    $statusLabel = match ($status) {
        'queued' => 'Menunggu runner',
        'running' => 'Sedang diproses',
        'success' => 'Selesai',
        'error' => 'Gagal',
        default => 'Belum dijalankan',
    };
    $statusColor = match ($status) {
        'queued',
        'running' => 'warning',
        'success' => 'success',
        'error' => 'danger',
        default => 'gray',
    };
@endphp

<div
    class="space-y-3"
    @if (in_array($status, ['queued', 'running'], true))
        wire:poll.2s="refreshServerDataPullRun"
    @endif
>
    <div
        wire:loading.flex
        wire:target="mountAction,callMountedAction,testServerDataConnection,pullServerDataFromServer"
        class="hidden items-center gap-3 rounded-lg border border-primary-200 bg-primary-50 px-4 py-3 text-sm text-primary-800 dark:border-primary-500/30 dark:bg-primary-500/10 dark:text-primary-200"
    >
        <x-filament::loading-indicator class="h-5 w-5 shrink-0" />
        <div class="min-w-0">
            <p class="font-medium">Menyiapkan proses server</p>
            <p class="mt-1 text-xs leading-5">Permintaan sedang dikirim ke runner CLI.</p>
        </div>
    </div>

    @if (in_array($status, ['queued', 'running'], true))
        <div class="flex items-center gap-3 rounded-lg border border-primary-200 bg-primary-50 px-4 py-3 text-sm text-primary-800 dark:border-primary-500/30 dark:bg-primary-500/10 dark:text-primary-200">
            <x-filament::loading-indicator class="h-5 w-5 shrink-0" />
            <div class="min-w-0">
                <p class="font-medium">{{ $status === 'queued' ? 'Menunggu runner CLI' : 'Sinkronisasi sedang berjalan' }}</p>
                <p class="mt-1 text-xs leading-5">Log diperbarui otomatis setiap dua detik. Halaman boleh ditinggalkan, tetapi jangan matikan komputer atau MySQL.</p>
            </div>
        </div>
    @endif

    <div class="overflow-hidden rounded-lg border border-gray-200 bg-gray-950 text-gray-100 shadow-sm dark:border-white/10">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-white/10 px-4 py-3">
            <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-2">
                    <p class="text-sm font-medium">Log tarik data server</p>
                    <x-filament::badge :color="$statusColor">{{ $statusLabel }}</x-filament::badge>
                </div>
                <p class="mt-1 text-xs text-gray-400">
                    {{ $page->serverDataPullRunOperation ?: 'Uji koneksi dan proses tarik data akan dicatat di sini.' }}
                </p>
            </div>

            @if ($page->serverDataPullLogs !== [] && ! in_array($status, ['queued', 'running'], true))
                <x-filament::button
                    type="button"
                    color="gray"
                    size="sm"
                    icon="heroicon-o-trash"
                    wire:click="clearServerDataPullLogs"
                >
                    Bersihkan Tampilan
                </x-filament::button>
            @endif
        </div>

        @if ($page->serverDataPullLogs === [])
            <div class="px-4 py-5 text-sm text-gray-400">
                Belum ada aktivitas. Uji koneksi bersifat opsional; Tarik Data Server selalu memeriksa koneksi sebelum menimpa data lokal.
            </div>
        @else
            <div class="max-h-72 space-y-1 overflow-y-auto px-4 py-3 font-mono text-xs leading-5">
                @foreach ($page->serverDataPullLogs as $entry)
                    <div class="grid min-w-0 grid-cols-[4.5rem_minmax(0,1fr)] gap-2">
                        <span class="text-gray-500">{{ $entry['time'] }}</span>
                        <span @class([
                            'break-words',
                            'text-emerald-300' => $entry['level'] === 'success',
                            'text-red-300' => $entry['level'] === 'error',
                            'text-gray-200' => ! in_array($entry['level'], ['success', 'error'], true),
                        ])>{{ $entry['message'] }}</span>
                    </div>
                @endforeach
            </div>
        @endif

        <div class="grid gap-2 border-t border-white/10 px-4 py-3 text-xs text-gray-400 sm:grid-cols-3">
            <span>Mulai: {{ $page->serverDataPullRunStartedAt ?: '-' }}</span>
            <span>Selesai: {{ $page->serverDataPullRunFinishedAt ?: '-' }}</span>
            <span>Durasi: {{ $page->serverDataPullRunDuration !== null ? number_format($page->serverDataPullRunDuration, 2).' detik' : '-' }}</span>
        </div>
    </div>

    <p class="text-xs leading-5 text-gray-500 dark:text-gray-400">
        Riwayat permanen: <code>storage/logs/server-sync-YYYY-MM-DD.log</code>
    </p>
</div>
