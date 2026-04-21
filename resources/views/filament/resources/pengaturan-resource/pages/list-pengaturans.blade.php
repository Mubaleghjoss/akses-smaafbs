<x-filament-panels::page>
    @php
        $googleDrive = $this->googleDrivePreview();
        $showGoogleDriveMonitoring = $this->showGoogleDriveMonitoring;
        $showGoogleDriveMonitoringDetails = $showGoogleDriveMonitoring && $this->showGoogleDriveMonitoringDetails;
        $hasMonitoring = $showGoogleDriveMonitoring ? $this->hasAnyGoogleDriveMonitoring() : false;
        $googleDriveBadgeColor = match ($googleDrive->readinessLabel()) {
            'Siap dipakai' => 'success',
            'Belum lengkap' => 'warning',
            default => 'gray',
        };
        $googleDriveStatusCards = $showGoogleDriveMonitoring ? $this->googleDriveStatusCards() : [];
        $googleDriveSyncModeCards = $showGoogleDriveMonitoring ? $this->googleDriveSyncModeCards() : [];
        $googleDriveModuleCards = $showGoogleDriveMonitoring ? $this->googleDriveModuleCards() : [];
        $prestasiAssetCards = $showGoogleDriveMonitoring ? $this->prestasiAssetCards() : [];
        $googleDriveQueueRows = $showGoogleDriveMonitoringDetails ? $this->googleDriveQueueRows() : [];
        $googleDriveAttentionRows = $showGoogleDriveMonitoringDetails ? $this->googleDriveAttentionRows() : [];
        $googleDriveSyncedRows = $showGoogleDriveMonitoringDetails ? $this->googleDriveSyncedRows() : [];
        $dokumenKomiteIndexUrl = $this->dokumenKomiteIndexUrl();
        $dokumenKomiteCreateUrl = $this->dokumenKomiteCreateUrl();
        $berkasSiswaIndexUrl = $this->berkasSiswaIndexUrl();
        $berkasSiswaCreateUrl = $this->berkasSiswaCreateUrl();
        $berkasGuruIndexUrl = $this->berkasGuruIndexUrl();
        $berkasGuruCreateUrl = $this->berkasGuruCreateUrl();
        $prestasiIndexUrl = $this->prestasiIndexUrl();
        $prestasiCreateUrl = $this->prestasiCreateUrl();
        $autoSyncLabels = collect([
            $googleDrive->autoSyncKomiteDocuments ? 'Dokumen Komite' : null,
            data_get($this->data, 'google_drive_auto_sync_berkas_siswa') ? 'Berkas Siswa' : null,
            data_get($this->data, 'google_drive_auto_sync_berkas_guru') ? 'Berkas Guru' : null,
            data_get($this->data, 'google_drive_auto_sync_prestasi') ? 'Prestasi' : null,
        ])->filter()->values();

        $brandRows = [
            ['label' => 'Nama situs', 'value' => $this->data['site_name'] ?? '-'],
            ['label' => 'PWA pendek', 'value' => $this->data['pwa_short_name'] ?? '-'],
            ['label' => 'Theme color', 'value' => $this->data['theme_color'] ?? '-'],
        ];

        $googleDriveRows = [
            ['label' => 'Service account', 'value' => $googleDrive->serviceAccountEmail() ?: 'Belum diisi'],
            ['label' => 'Folder tujuan', 'value' => $googleDrive->rootFolderId ?: 'Belum diisi'],
            ['label' => 'Mode upload', 'value' => $autoSyncLabels->isNotEmpty() ? 'Otomatis via queue: '.$autoSyncLabels->implode(', ') : 'Manual / belum aktif'],
        ];

        $setupRows = [
            ['step' => '1', 'title' => 'Aktifkan API dan service account', 'description' => 'Buat service account di Google Cloud, aktifkan Google Drive API, lalu unduh JSON credential.'],
            ['step' => '2', 'title' => 'Share folder tujuan', 'description' => 'Tambahkan email service account sebagai Editor pada folder induk arsip komite atau Shared Drive yang dipakai.'],
            ['step' => '3', 'title' => 'Uji koneksi lalu aktifkan sinkron', 'description' => 'Setelah status siap, baru nyalakan sinkron otomatis dan jalankan queue worker agar progress upload bergerak.'],
        ];
    @endphp

    <form wire:submit="save" class="space-y-5">
        <div class="grid gap-4 xl:grid-cols-3">
            <x-filament::section
                heading="Ringkasan pengaturan"
                description="Admin tidak perlu lagi mengelola key-value mentah. Fokuskan perubahan di form terkurasi, lalu uji koneksi Google Drive sebelum mengaktifkan sinkron otomatis."
                icon="heroicon-o-adjustments-horizontal"
            >
                <div class="overflow-hidden rounded-xl ring-1 ring-gray-950/5 dark:ring-white/10">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-white/10">
                        <tbody class="divide-y divide-gray-200 text-sm dark:divide-white/10">
                            <tr>
                                <th class="w-40 bg-gray-50 px-4 py-3 text-left font-medium text-gray-600 dark:bg-white/5 dark:text-gray-300">Panel aktif</th>
                                <td class="px-4 py-3 text-gray-950 dark:text-white">Branding, metadata, PWA, dan integrasi Google Drive</td>
                            </tr>
                            <tr>
                                <th class="w-40 bg-gray-50 px-4 py-3 text-left font-medium text-gray-600 dark:bg-white/5 dark:text-gray-300">Pola arsip</th>
                                <td class="px-4 py-3 text-gray-950 dark:text-white">Simpan lokal dulu, lalu sinkron ke Google Drive secara berurutan</td>
                            </tr>
                            <tr>
                                <th class="w-40 bg-gray-50 px-4 py-3 text-left font-medium text-gray-600 dark:bg-white/5 dark:text-gray-300">Titik kontrol</th>
                                <td class="px-4 py-3 text-gray-950 dark:text-white">Uji koneksi sebelum menyalakan sinkron otomatis</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </x-filament::section>

            <x-filament::section
                heading="Brand aktif"
                description="Nilai cepat yang paling sering dicek admin sebelum menyimpan perubahan branding."
                icon="heroicon-o-swatch"
            >
                <div class="overflow-hidden rounded-xl ring-1 ring-gray-950/5 dark:ring-white/10">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-white/10">
                        <tbody class="divide-y divide-gray-200 text-sm dark:divide-white/10">
                            @foreach ($brandRows as $row)
                                <tr>
                                    <th class="w-40 bg-gray-50 px-4 py-3 text-left font-medium text-gray-600 dark:bg-white/5 dark:text-gray-300">{{ $row['label'] }}</th>
                                    <td class="px-4 py-3 font-medium text-gray-950 dark:text-white">{{ $row['value'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-filament::section>

            <x-filament::section
                heading="Google Drive"
                description="Pantau kesiapan kredensial dan target folder tanpa perlu membuka record dokumen komite."
                icon="heroicon-o-cloud"
            >
                <x-slot name="afterHeader">
                    <x-filament::badge :color="$googleDriveBadgeColor">
                        {{ $googleDrive->readinessLabel() }}
                    </x-filament::badge>
                </x-slot>

                <div class="overflow-hidden rounded-xl ring-1 ring-gray-950/5 dark:ring-white/10">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-white/10">
                        <tbody class="divide-y divide-gray-200 text-sm dark:divide-white/10">
                            @foreach ($googleDriveRows as $row)
                                <tr>
                                    <th class="w-40 bg-gray-50 px-4 py-3 text-left font-medium text-gray-600 dark:bg-white/5 dark:text-gray-300">{{ $row['label'] }}</th>
                                    <td class="px-4 py-3 font-medium text-gray-950 dark:text-white">{{ $row['value'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-filament::section>
        </div>

        <x-filament::section
            heading="Monitoring sinkron file"
            description="Pantau dokumen komite, berkas siswa, berkas guru, dan prestasi yang sudah terkirim ke Google Drive, yang masih antre, dan yang perlu tindakan tanpa keluar dari halaman pengaturan."
            icon="heroicon-o-cloud-arrow-up"
        >
            <x-slot name="afterHeader">
                <div class="flex flex-wrap gap-2">
                    @if ($dokumenKomiteIndexUrl)
                        <x-filament::button tag="a" :href="$dokumenKomiteIndexUrl" color="gray" outlined size="sm" icon="heroicon-o-folder-open">
                            Buka Dokumen Komite
                        </x-filament::button>
                    @endif

                    @if ($dokumenKomiteCreateUrl)
                        <x-filament::button tag="a" :href="$dokumenKomiteCreateUrl" color="gray" outlined size="sm" icon="heroicon-o-plus">
                            Tambah Dokumen
                        </x-filament::button>
                    @endif

                    @if ($berkasSiswaIndexUrl)
                        <x-filament::button tag="a" :href="$berkasSiswaIndexUrl" color="gray" outlined size="sm" icon="heroicon-o-folder-open">
                            Buka Berkas Siswa
                        </x-filament::button>
                    @endif

                    @if ($berkasSiswaCreateUrl)
                        <x-filament::button tag="a" :href="$berkasSiswaCreateUrl" color="gray" outlined size="sm" icon="heroicon-o-plus">
                            Tambah Berkas Siswa
                        </x-filament::button>
                    @endif

                    @if ($berkasGuruIndexUrl)
                        <x-filament::button tag="a" :href="$berkasGuruIndexUrl" color="gray" outlined size="sm" icon="heroicon-o-folder-open">
                            Buka Berkas Guru
                        </x-filament::button>
                    @endif

                    @if ($berkasGuruCreateUrl)
                        <x-filament::button tag="a" :href="$berkasGuruCreateUrl" color="gray" outlined size="sm" icon="heroicon-o-plus">
                            Tambah Berkas Guru
                        </x-filament::button>
                    @endif

                    @if ($prestasiIndexUrl)
                        <x-filament::button tag="a" :href="$prestasiIndexUrl" color="gray" outlined size="sm" icon="heroicon-o-folder-open">
                            Buka Prestasi
                        </x-filament::button>
                    @endif

                    @if ($prestasiCreateUrl)
                        <x-filament::button tag="a" :href="$prestasiCreateUrl" color="gray" outlined size="sm" icon="heroicon-o-plus">
                            Tambah Prestasi
                        </x-filament::button>
                    @endif

                    <x-filament::button type="button" color="gray" outlined size="sm" icon="heroicon-o-arrow-path" wire:click="refreshGoogleDriveMonitoring">
                        Refresh Monitor
                    </x-filament::button>
                </div>
            </x-slot>

            @if (! $showGoogleDriveMonitoring)
                <div class="rounded-xl border border-dashed border-gray-300 bg-gray-50 px-4 py-5 text-sm text-gray-600 dark:border-white/10 dark:bg-white/5 dark:text-gray-300">
                    Monitor Google Drive ditunda agar halaman pengaturan lebih ringan. Klik <strong>Muat Monitor Drive</strong> di header jika ingin menampilkan ringkasan sinkron.
                </div>
            @elseif ($hasMonitoring)
                <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
                    @foreach ($googleDriveStatusCards as $card)
                        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-900/50">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="text-sm font-medium text-gray-900 dark:text-white">{{ $card['label'] }}</p>
                                    <p class="mt-1 text-xs leading-5 text-gray-500 dark:text-gray-400">{{ $card['description'] }}</p>
                                </div>

                                <x-filament::badge :color="$card['color']">
                                    {{ $card['count'] }}
                                </x-filament::badge>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="mt-4">
                    <p class="text-sm font-medium text-gray-900 dark:text-white">Cakupan modul</p>
                    <p class="mt-1 text-xs leading-5 text-gray-500 dark:text-gray-400">Ringkasan histori sinkron per modul yang sedang dipantau dari halaman pengaturan.</p>

                    <div class="mt-3 grid gap-3 sm:grid-cols-3">
                        @foreach ($googleDriveModuleCards as $card)
                            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-900/50">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <p class="text-sm font-medium text-gray-900 dark:text-white">{{ $card['label'] }}</p>
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
                    <div class="mt-4">
                        <p class="text-sm font-medium text-gray-900 dark:text-white">Asset prestasi</p>
                        <p class="mt-1 text-xs leading-5 text-gray-500 dark:text-gray-400">Pantau jumlah file sertifikat dan dokumentasi prestasi yang sudah tersinkron ke Google Drive.</p>

                        <div class="mt-3 grid gap-3 sm:grid-cols-3">
                            @foreach ($prestasiAssetCards as $card)
                                <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-900/50">
                                    <div class="flex items-start justify-between gap-3">
                                        <div class="min-w-0">
                                            <p class="text-sm font-medium text-gray-900 dark:text-white">{{ $card['label'] }}</p>
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

                <div class="mt-4">
                    <p class="text-sm font-medium text-gray-900 dark:text-white">Mode sinkron terakhir</p>
                    <p class="mt-1 text-xs leading-5 text-gray-500 dark:text-gray-400">Ringkasan hasil sinkron terakhir untuk file yang sudah pernah diproses.</p>

                    <div class="mt-3 grid gap-3 sm:grid-cols-3">
                        @foreach ($googleDriveSyncModeCards as $card)
                            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-900/50">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <p class="text-sm font-medium text-gray-900 dark:text-white">{{ $card['label'] }}</p>
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
                <div class="rounded-xl border border-dashed border-gray-300 bg-gray-50 px-4 py-5 text-sm text-gray-600 dark:border-white/10 dark:bg-white/5 dark:text-gray-300">
                    Tabel monitoring Google Drive belum tersedia. Monitoring akan muncul otomatis setelah tabel dokumen komite atau berkas terkait tersedia.
                </div>
            @endif
        </x-filament::section>

        @if ($showGoogleDriveMonitoringDetails)
            <div class="grid gap-4 xl:grid-cols-3">
                <x-filament::section
                    heading="Antrean & proses aktif"
                    description="File dari semua modul yang sudah masuk queue atau sedang diunggah oleh worker."
                    icon="heroicon-o-clock"
                >
                    <div class="space-y-3">
                        @forelse ($googleDriveQueueRows as $row)
                            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-900/50">
                                <div class="flex flex-wrap items-start justify-between gap-2">
                                    <div class="min-w-0 flex-1">
                                        <p class="font-medium text-gray-950 dark:text-white">{{ $row['judul'] }}</p>
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
                                        <p class="mt-2 text-xs leading-5 text-gray-500 dark:text-gray-400">{{ $row['context'] }}</p>
                                    </div>
                                    <p class="text-sm font-semibold text-gray-700 dark:text-gray-200">{{ $row['progress'] }}%</p>
                                </div>

                                <p class="mt-3 text-sm leading-6 text-gray-600 dark:text-gray-400">{{ $row['message'] }}</p>

                                <div class="mt-3">
                                    <div class="h-2 overflow-hidden rounded-full bg-gray-100 dark:bg-white/10">
                                        <div class="h-full rounded-full bg-primary-500" style="width: {{ max(8, min(100, $row['progress'])) }}%"></div>
                                    </div>
                                </div>

                                <div class="mt-3 flex flex-wrap items-center justify-between gap-2">
                                    <span class="text-xs text-gray-500 dark:text-gray-400">{{ $row['timestamp_label'] }}: {{ $row['timestamp'] }}</span>
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

                                        @if ($row['admin_url'])
                                            <x-filament::button tag="a" :href="$row['admin_url']" color="gray" outlined size="sm" icon="heroicon-o-pencil-square">
                                                Buka
                                            </x-filament::button>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="rounded-xl border border-dashed border-gray-300 bg-gray-50 px-4 py-5 text-sm text-gray-600 dark:border-white/10 dark:bg-white/5 dark:text-gray-300">
                                Tidak ada file yang sedang antre atau diproses sekarang.
                            </div>
                        @endforelse
                    </div>
                </x-filament::section>

                <x-filament::section
                    heading="Belum terkirim / perlu tindakan"
                    description="File yang gagal, belum lengkap konfigurasinya, nonaktif, atau belum pernah tersinkron."
                    icon="heroicon-o-exclamation-triangle"
                >
                    <div class="space-y-3">
                        @forelse ($googleDriveAttentionRows as $row)
                            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-900/50">
                                <div class="flex flex-wrap items-start justify-between gap-2">
                                    <div class="min-w-0 flex-1">
                                        <p class="font-medium text-gray-950 dark:text-white">{{ $row['judul'] }}</p>
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
                                        <p class="mt-2 text-xs leading-5 text-gray-500 dark:text-gray-400">{{ $row['context'] }}</p>
                                    </div>
                                    <p class="text-sm font-semibold text-gray-700 dark:text-gray-200">{{ $row['progress'] }}%</p>
                                </div>

                                <p class="mt-3 text-sm leading-6 text-gray-600 dark:text-gray-400">{{ $row['message'] }}</p>

                                <div class="mt-3 flex flex-wrap items-center justify-between gap-2">
                                    <span class="text-xs text-gray-500 dark:text-gray-400">{{ $row['timestamp_label'] }}: {{ $row['timestamp'] }}</span>
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

                                        @if ($row['admin_url'])
                                            <x-filament::button tag="a" :href="$row['admin_url']" color="gray" outlined size="sm" icon="heroicon-o-pencil-square">
                                                Buka
                                            </x-filament::button>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="rounded-xl border border-dashed border-gray-300 bg-gray-50 px-4 py-5 text-sm text-gray-600 dark:border-white/10 dark:bg-white/5 dark:text-gray-300">
                                Tidak ada file yang perlu tindakan saat ini.
                            </div>
                        @endforelse
                    </div>
                </x-filament::section>

                <x-filament::section
                    heading="Sudah tersinkron terakhir"
                    description="File terbaru dari semua modul yang berhasil tersimpan di Google Drive."
                    icon="heroicon-o-check-badge"
                >
                    <div class="space-y-3">
                        @forelse ($googleDriveSyncedRows as $row)
                            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-900/50">
                                <div class="flex flex-wrap items-start justify-between gap-2">
                                    <div class="min-w-0 flex-1">
                                        <p class="font-medium text-gray-950 dark:text-white">{{ $row['judul'] }}</p>
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
                                        <p class="mt-2 text-xs leading-5 text-gray-500 dark:text-gray-400">{{ $row['context'] }}</p>
                                    </div>
                                    <p class="text-sm font-semibold text-gray-700 dark:text-gray-200">{{ $row['progress'] }}%</p>
                                </div>

                                <p class="mt-3 text-sm leading-6 text-gray-600 dark:text-gray-400">{{ $row['message'] }}</p>

                                <div class="mt-3 flex flex-wrap items-center justify-between gap-2">
                                    <span class="text-xs text-gray-500 dark:text-gray-400">{{ $row['timestamp_label'] }}: {{ $row['timestamp'] }}</span>
                                    <div class="flex flex-wrap gap-2">
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
                    @empty
                        <div class="rounded-xl border border-dashed border-gray-300 bg-gray-50 px-4 py-5 text-sm text-gray-600 dark:border-white/10 dark:bg-white/5 dark:text-gray-300">
                            Belum ada file yang berhasil tersinkron ke Google Drive.
                        </div>
                    @endforelse
                </div>
            </x-filament::section>
        </div>
        @elseif ($showGoogleDriveMonitoring && $hasMonitoring)
            <div class="rounded-xl border border-dashed border-gray-300 bg-gray-50 px-4 py-5 text-sm text-gray-600 dark:border-white/10 dark:bg-white/5 dark:text-gray-300">
                Detail monitor Google Drive sedang ditunda agar halaman tetap ringan. Klik <strong>Muat Detail Monitor</strong> di header jika ingin melihat antrean, perhatian, dan file yang sudah tersinkron.
            </div>
        @endif

        <x-filament::section
            heading="Buka bagian"
            description="Urutan setup Google Drive yang aman, dengan tombol lompat cepat ke bagian form yang paling sering dipakai."
            icon="heroicon-o-queue-list"
        >
            <div class="flex flex-wrap gap-2">
                @foreach ($this->quickSectionTargets() as $section)
                    <x-filament::button
                        type="button"
                        color="gray"
                        outlined
                        size="sm"
                        icon="heroicon-o-arrow-down-circle"
                        onclick="document.getElementById('{{ $section['id'] }}')?.scrollIntoView({ behavior: 'smooth', block: 'start' })"
                    >
                        {{ $section['label'] }}
                    </x-filament::button>
                @endforeach
            </div>

            <div class="mt-4 overflow-hidden rounded-xl ring-1 ring-gray-950/5 dark:ring-white/10">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-white/10">
                    <tbody class="divide-y divide-gray-200 text-sm dark:divide-white/10">
                        @foreach ($setupRows as $row)
                            <tr>
                                <td class="w-16 px-4 py-3 align-top">
                                    <x-filament::badge color="gray">
                                        {{ $row['step'] }}
                                    </x-filament::badge>
                                </td>
                                <td class="px-4 py-3 align-top">
                                    <p class="font-medium text-gray-950 dark:text-white">{{ $row['title'] }}</p>
                                    <p class="mt-1 text-gray-600 dark:text-gray-400">{{ $row['description'] }}</p>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-filament::section>

        {{ $this->form }}

        <div class="sticky bottom-4 z-10 flex flex-wrap justify-end gap-2 rounded-xl border border-gray-200 bg-white/95 p-3 shadow-sm backdrop-blur dark:border-white/10 dark:bg-gray-900/95">
            <x-filament::button type="button" color="gray" icon="heroicon-o-cloud-arrow-up" size="sm" wire:click="testGoogleDriveConnection">
                Uji Koneksi Google Drive
            </x-filament::button>
            <x-filament::button type="submit" icon="heroicon-o-check-circle" size="sm">
                Simpan Pengaturan
            </x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
