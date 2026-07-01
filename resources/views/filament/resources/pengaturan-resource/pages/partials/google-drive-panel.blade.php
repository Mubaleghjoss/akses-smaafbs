@php
    $googleDrive = $page->googleDrivePreview();
    $showGoogleDriveMonitoring = $page->showGoogleDriveMonitoring;
    $showGoogleDriveMonitoringDetails = $showGoogleDriveMonitoring && $page->showGoogleDriveMonitoringDetails;
    $hasMonitoring = $showGoogleDriveMonitoring ? $page->hasAnyGoogleDriveMonitoring() : false;
    $googleDriveBadgeColor = match ($googleDrive->readinessLabel()) {
        'Siap dipakai' => 'success',
        'Belum lengkap' => 'warning',
        default => 'gray',
    };
    $googleDriveStatusCards = $showGoogleDriveMonitoring ? $page->googleDriveStatusCards() : [];
    $googleDriveSyncModeCards = $showGoogleDriveMonitoring ? $page->googleDriveSyncModeCards() : [];
    $googleDriveModuleCards = $showGoogleDriveMonitoring ? $page->googleDriveModuleCards() : [];
    $prestasiAssetCards = $showGoogleDriveMonitoring ? $page->prestasiAssetCards() : [];
    $googleDriveQueueRows = $showGoogleDriveMonitoringDetails ? $page->googleDriveQueueRows() : [];
    $googleDriveAttentionRows = $showGoogleDriveMonitoringDetails ? $page->googleDriveAttentionRows() : [];
    $googleDriveSyncedRows = $showGoogleDriveMonitoringDetails ? $page->googleDriveSyncedRows() : [];
    $quickLinks = [
        ['url' => $page->dokumenKomiteIndexUrl(), 'label' => 'Buka Dokumen Komite', 'icon' => 'heroicon-o-folder-open'],
        ['url' => $page->dokumenKomiteCreateUrl(), 'label' => 'Tambah Dokumen', 'icon' => 'heroicon-o-plus'],
        ['url' => $page->berkasSiswaIndexUrl(), 'label' => 'Buka Berkas Siswa', 'icon' => 'heroicon-o-folder-open'],
        ['url' => $page->berkasSiswaCreateUrl(), 'label' => 'Tambah Berkas Siswa', 'icon' => 'heroicon-o-plus'],
        ['url' => $page->berkasGuruIndexUrl(), 'label' => 'Buka Berkas Guru', 'icon' => 'heroicon-o-folder-open'],
        ['url' => $page->berkasGuruCreateUrl(), 'label' => 'Tambah Berkas Guru', 'icon' => 'heroicon-o-plus'],
        ['url' => $page->prestasiIndexUrl(), 'label' => 'Buka Prestasi', 'icon' => 'heroicon-o-folder-open'],
        ['url' => $page->prestasiCreateUrl(), 'label' => 'Tambah Prestasi', 'icon' => 'heroicon-o-plus'],
    ];
    $autoSyncLabels = collect([
        $googleDrive->autoSyncKomiteDocuments ? 'Dokumen Komite' : null,
        data_get($page->data, 'google_drive_auto_sync_berkas_siswa') ? 'Berkas Siswa' : null,
        data_get($page->data, 'google_drive_auto_sync_berkas_guru') ? 'Berkas Guru' : null,
        data_get($page->data, 'google_drive_auto_sync_prestasi') ? 'Prestasi' : null,
        data_get($page->data, 'google_drive_auto_sync_identitas_sekolah') ? 'Identitas Sekolah' : null,
    ])->filter()->values();
    $googleDriveRows = [
        ['label' => 'Status', 'value' => $googleDrive->readinessLabel(), 'badge' => true],
        ['label' => 'Service account', 'value' => $googleDrive->serviceAccountEmail() ?: 'Belum diisi'],
        ['label' => 'Folder tujuan', 'value' => $googleDrive->rootFolderId ?: 'Belum diisi'],
        ['label' => 'Mode upload', 'value' => $autoSyncLabels->isNotEmpty() ? 'Otomatis via queue: '.$autoSyncLabels->implode(', ') : 'Manual / belum aktif'],
    ];
@endphp

<div class="space-y-4">
    <div class="flex flex-wrap items-center gap-2">
        <x-filament::badge :color="$googleDriveBadgeColor">
            {{ $googleDrive->readinessLabel() }}
        </x-filament::badge>

        <x-filament::button type="button" color="gray" outlined size="sm" icon="heroicon-o-cloud-arrow-up" wire:click="testGoogleDriveConnection">
            Uji Koneksi Google Drive
        </x-filament::button>

        <x-filament::button type="button" color="gray" outlined size="sm" icon="heroicon-o-arrow-path" wire:click="refreshGoogleDriveMonitoring">
            Refresh Monitor
        </x-filament::button>

        @foreach ($quickLinks as $link)
            @if ($link['url'])
                <x-filament::button tag="a" :href="$link['url']" color="gray" outlined size="sm" :icon="$link['icon']">
                    {{ $link['label'] }}
                </x-filament::button>
            @endif
        @endforeach
    </div>

    <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
        @foreach ($googleDriveRows as $row)
            <div class="min-w-0 rounded-lg border border-gray-200 bg-white p-3 shadow-sm dark:border-white/10 dark:bg-gray-900/50">
                <p class="text-xs font-medium uppercase tracking-normal text-gray-500 dark:text-gray-400">{{ $row['label'] }}</p>

                @if (($row['badge'] ?? false) === true)
                    <div class="mt-2">
                        <x-filament::badge :color="$googleDriveBadgeColor">
                            {{ $row['value'] }}
                        </x-filament::badge>
                    </div>
                @else
                    <p class="mt-2 break-all text-sm font-medium leading-5 text-gray-950 dark:text-white">{{ $row['value'] }}</p>
                @endif
            </div>
        @endforeach
    </div>

    @if (! $showGoogleDriveMonitoring)
        <div class="rounded-lg border border-dashed border-gray-300 bg-gray-50 px-4 py-4 text-sm leading-6 text-gray-600 dark:border-white/10 dark:bg-white/5 dark:text-gray-300">
            Monitor Google Drive diminimalkan otomatis. Buka monitor dari header saat perlu melihat antrean dan status upload.
        </div>
    @elseif ($hasMonitoring)
        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
            @foreach ($googleDriveStatusCards as $card)
                <div class="min-w-0 rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-900/50">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="break-words text-sm font-medium text-gray-900 dark:text-white">{{ $card['label'] }}</p>
                            <p class="mt-1 text-xs leading-5 text-gray-500 dark:text-gray-400">{{ $card['description'] }}</p>
                        </div>

                        <x-filament::badge :color="$card['color']">
                            {{ $card['count'] }}
                        </x-filament::badge>
                    </div>
                </div>
            @endforeach
        </div>

        <div>
            <p class="text-sm font-medium text-gray-900 dark:text-white">Cakupan modul</p>
            <div class="mt-3 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                @foreach ($googleDriveModuleCards as $card)
                    <div class="min-w-0 rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-900/50">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="break-words text-sm font-medium text-gray-900 dark:text-white">{{ $card['label'] }}</p>
                                <p class="mt-1 text-xs leading-5 text-gray-500 dark:text-gray-400">{{ $card['description'] }}</p>
                            </div>

                            <x-filament::badge :color="$card['color']">
                                {{ $card['count'] }}
                            </x-filament::badge>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        @if ($prestasiAssetCards !== [])
            <div>
                <p class="text-sm font-medium text-gray-900 dark:text-white">Asset prestasi</p>
                <div class="mt-3 grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                    @foreach ($prestasiAssetCards as $card)
                        <div class="min-w-0 rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-900/50">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="break-words text-sm font-medium text-gray-900 dark:text-white">{{ $card['label'] }}</p>
                                    <p class="mt-1 text-xs leading-5 text-gray-500 dark:text-gray-400">{{ $card['description'] }}</p>
                                </div>

                                <x-filament::badge :color="$card['color']">
                                    {{ $card['count'] }}
                                </x-filament::badge>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        <div>
            <p class="text-sm font-medium text-gray-900 dark:text-white">Mode sinkron terakhir</p>
            <div class="mt-3 grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                @foreach ($googleDriveSyncModeCards as $card)
                    <div class="min-w-0 rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-900/50">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="break-words text-sm font-medium text-gray-900 dark:text-white">{{ $card['label'] }}</p>
                                <p class="mt-1 text-xs leading-5 text-gray-500 dark:text-gray-400">{{ $card['description'] }}</p>
                            </div>

                            <x-filament::badge :color="$card['color']">
                                {{ $card['count'] }}
                            </x-filament::badge>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @else
        <div class="rounded-lg border border-dashed border-gray-300 bg-gray-50 px-4 py-4 text-sm leading-6 text-gray-600 dark:border-white/10 dark:bg-white/5 dark:text-gray-300">
            Tabel monitoring Google Drive belum tersedia. Monitoring akan muncul otomatis setelah tabel dokumen komite atau berkas terkait tersedia.
        </div>
    @endif

    @if ($showGoogleDriveMonitoringDetails)
        <div class="grid gap-4 xl:grid-cols-3">
            <div class="space-y-3">
                <div>
                    <p class="text-sm font-medium text-gray-900 dark:text-white">Antrean dan proses aktif</p>
                    <p class="mt-1 text-xs leading-5 text-gray-500 dark:text-gray-400">File dari semua modul yang sudah masuk queue atau sedang diunggah oleh worker.</p>
                </div>

                @forelse ($googleDriveQueueRows as $row)
                    @include('filament.resources.pengaturan-resource.pages.partials.google-drive-row', ['row' => $row, 'mode' => 'active'])
                @empty
                    <div class="rounded-lg border border-dashed border-gray-300 bg-gray-50 px-4 py-4 text-sm text-gray-600 dark:border-white/10 dark:bg-white/5 dark:text-gray-300">
                        Tidak ada file yang sedang antre atau diproses sekarang.
                    </div>
                @endforelse
            </div>

            <div class="space-y-3">
                <div>
                    <p class="text-sm font-medium text-gray-900 dark:text-white">Belum terkirim / perlu tindakan</p>
                    <p class="mt-1 text-xs leading-5 text-gray-500 dark:text-gray-400">File yang gagal, belum lengkap konfigurasinya, nonaktif, atau belum pernah tersinkron.</p>
                </div>

                @forelse ($googleDriveAttentionRows as $row)
                    @include('filament.resources.pengaturan-resource.pages.partials.google-drive-row', ['row' => $row, 'mode' => 'attention'])
                @empty
                    <div class="rounded-lg border border-dashed border-gray-300 bg-gray-50 px-4 py-4 text-sm text-gray-600 dark:border-white/10 dark:bg-white/5 dark:text-gray-300">
                        Tidak ada file yang perlu tindakan saat ini.
                    </div>
                @endforelse
            </div>

            <div class="space-y-3">
                <div>
                    <p class="text-sm font-medium text-gray-900 dark:text-white">Sudah tersinkron terakhir</p>
                    <p class="mt-1 text-xs leading-5 text-gray-500 dark:text-gray-400">File terbaru dari semua modul yang berhasil tersimpan di Google Drive.</p>
                </div>

                @forelse ($googleDriveSyncedRows as $row)
                    @include('filament.resources.pengaturan-resource.pages.partials.google-drive-row', ['row' => $row, 'mode' => 'synced'])
                @empty
                    <div class="rounded-lg border border-dashed border-gray-300 bg-gray-50 px-4 py-4 text-sm text-gray-600 dark:border-white/10 dark:bg-white/5 dark:text-gray-300">
                        Belum ada file yang berhasil tersinkron ke Google Drive.
                    </div>
                @endforelse
            </div>
        </div>
    @elseif ($showGoogleDriveMonitoring && $hasMonitoring)
        <div class="rounded-lg border border-dashed border-gray-300 bg-gray-50 px-4 py-4 text-sm leading-6 text-gray-600 dark:border-white/10 dark:bg-white/5 dark:text-gray-300">
            Detail monitor Google Drive sedang ditunda agar halaman tetap ringan. Klik <strong>Buka Detail Drive</strong> di header jika ingin melihat antrean, perhatian, dan file yang sudah tersinkron.
        </div>
    @endif
</div>
