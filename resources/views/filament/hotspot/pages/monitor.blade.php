<x-filament-panels::page>
    @if($error)
        <div class="fi-section rounded-xl border border-amber-300 bg-amber-50 p-5 dark:border-amber-600 dark:bg-amber-950">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-start gap-3">
                    <x-heroicon-o-wifi class="h-8 w-8 text-amber-500" />
                    <div>
                        <p class="font-semibold text-amber-800 dark:text-amber-200">Router tidak terhubung</p>
                        <p class="mt-1 max-w-xl text-sm text-amber-700 dark:text-amber-300">
                            Laptop ini sedang <strong>di luar jaringan sekolah</strong>, atau host/koneksi belum
                            benar. Pastikan laptop terhubung ke jaringan sekolah, lalu periksa pengaturan koneksi
                            (host, port, user, password).
                        </p>
                        @if ($error)
                            <p class="mt-2 break-all font-mono text-xs text-amber-500">{{ $error }}</p>
                        @endif
                    </div>
                </div>
                <a href="{{ \App\Filament\Pages\HotspotSettings::getUrl() }}"
                   class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">
                    ⚙️ Pengaturan Koneksi
                </a>
            </div>
        </div>
    @else
        <x-filament-widgets::widgets :widgets="$this->getWidgets()" />

        <div class="fi-section rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900">
            <div class="px-5 py-4">
                <h3 class="text-sm font-semibold text-gray-950 dark:text-white">Trafik per Interface (realtime)</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-800">
                        <tr class="text-left text-xs font-medium text-gray-500 dark:text-gray-400">
                            <th class="px-5 py-2">Interface</th>
                            <th class="px-5 py-2">Download (rx)</th>
                            <th class="px-5 py-2">Upload (tx)</th>
                            <th class="px-5 py-2">Total Rx</th>
                            <th class="px-5 py-2">Total Tx</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse ($traffic as $t)
                            <tr>
                                <td class="px-5 py-2 font-medium">{{ $t['name'] }}</td>
                                <td class="px-5 py-2 text-emerald-600">{{ \App\Filament\Pages\Monitor::fmtBps($t['rx']) }}</td>
                                <td class="px-5 py-2 text-blue-600">{{ \App\Filament\Pages\Monitor::fmtBps($t['tx']) }}</td>
                                <td class="px-5 py-2 text-gray-500">{{ \App\Filament\Pages\Monitor::fmtBytes($t['rx_total']) }}</td>
                                <td class="px-5 py-2 text-gray-500">{{ \App\Filament\Pages\Monitor::fmtBytes($t['tx_total']) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-5 py-4 text-center text-gray-500">Tidak ada data trafik.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="fi-section rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900">
            <div class="px-5 py-4">
                <h3 class="text-sm font-semibold text-gray-950 dark:text-white">User Online Sekarang ({{ count($active) }})</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-800">
                        <tr class="text-left text-xs font-medium text-gray-500 dark:text-gray-400">
                            <th class="px-5 py-2">User</th>
                            <th class="px-5 py-2">Alamat IP</th>
                            <th class="px-5 py-2">MAC</th>
                            <th class="px-5 py-2">Uptime</th>
                            <th class="px-5 py-2">Via</th>
                            <th class="px-5 py-2">Download</th>
                            <th class="px-5 py-2">Upload</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse ($active as $u)
                            <tr>
                                <td class="px-5 py-2 font-medium">{{ $u['user'] ?? '-' }}</td>
                                <td class="px-5 py-2">{{ $u['address'] ?? '-' }}</td>
                                <td class="px-5 py-2 font-mono text-xs">{{ $u['mac-address'] ?? '-' }}</td>
                                <td class="px-5 py-2">{{ $u['uptime'] ?? '-' }}</td>
                                <td class="px-5 py-2">{{ $u['login-by'] ?? '-' }}</td>
                                <td class="px-5 py-2 text-emerald-600">{{ \App\Filament\Pages\Monitor::fmtBytes((int) ($u['bytes-in'] ?? 0)) }}</td>
                                <td class="px-5 py-2 text-blue-600">{{ \App\Filament\Pages\Monitor::fmtBytes((int) ($u['bytes-out'] ?? 0)) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="px-5 py-4 text-center text-gray-500">Tidak ada user online saat ini.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</x-filament-panels::page>