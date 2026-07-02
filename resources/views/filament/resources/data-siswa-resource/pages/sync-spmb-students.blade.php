<x-filament-panels::page>
    <div class="space-y-6">
        <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
            @foreach([
                ['label' => 'Ditemukan', 'value' => $stats['fetched'], 'class' => 'text-gray-700'],
                ['label' => 'Siswa Baru', 'value' => $stats['new'], 'class' => 'text-primary-600'],
                ['label' => 'Perlu Update', 'value' => $stats['update'], 'class' => 'text-warning-600'],
                ['label' => 'Tidak Berubah', 'value' => $stats['unchanged'], 'class' => 'text-success-600'],
                ['label' => 'Konflik', 'value' => $stats['conflict'], 'class' => 'text-danger-600'],
            ] as $metric)
                <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-900">
                    <div class="text-sm text-gray-500 dark:text-gray-400">{{ $metric['label'] }}</div>
                    <div class="mt-1 text-2xl font-semibold {{ $metric['class'] }}">{{ $metric['value'] }}</div>
                </div>
            @endforeach
        </section>

        @if($lastFetchedAt)
            <p class="text-sm text-gray-500 dark:text-gray-400">
                Preview terakhir: {{ $lastFetchedAt }}. Baris baru dan update dipilih otomatis.
            </p>
        @endif

        <section class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-gray-900">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[980px] text-left text-sm">
                    <thead class="bg-gray-50 text-xs uppercase text-gray-600 dark:bg-white/5 dark:text-gray-300">
                        <tr>
                            <th class="w-12 px-4 py-3">Pilih</th>
                            <th class="px-4 py-3">Siswa SPMB</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3">Data Lokal</th>
                            <th class="px-4 py-3">Perubahan / Konflik</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                        @forelse($rows as $row)
                            @php
                                $statusStyle = match($row['status']) {
                                    'baru' => 'bg-primary-50 text-primary-700 dark:bg-primary-500/10 dark:text-primary-300',
                                    'update' => 'bg-warning-50 text-warning-700 dark:bg-warning-500/10 dark:text-warning-300',
                                    'tidak_berubah' => 'bg-success-50 text-success-700 dark:bg-success-500/10 dark:text-success-300',
                                    default => 'bg-danger-50 text-danger-700 dark:bg-danger-500/10 dark:text-danger-300',
                                };
                                $statusLabel = match($row['status']) {
                                    'baru' => 'Baru',
                                    'update' => 'Update',
                                    'tidak_berubah' => 'Tidak berubah',
                                    default => 'Konflik',
                                };
                            @endphp
                            <tr wire:key="spmb-row-{{ $row['source_id'] }}" class="align-top">
                                <td class="px-4 py-4">
                                    <input
                                        type="checkbox"
                                        wire:model="selected"
                                        value="{{ $row['source_id'] }}"
                                        @disabled($row['status'] === 'tidak_berubah')
                                        class="rounded border-gray-300 text-primary-600 shadow-sm focus:ring-primary-500"
                                        aria-label="Pilih {{ $row['nama'] }}"
                                    >
                                </td>
                                <td class="px-4 py-4">
                                    <div class="font-semibold text-gray-950 dark:text-white">{{ $row['nama'] }}</div>
                                    <div class="mt-1 text-xs text-gray-500">{{ $row['nomor_pendaftaran'] }}</div>
                                    <div class="text-xs text-gray-500">NISN: {{ $row['nisn'] ?: '-' }} | JK: {{ $row['jk'] ?: '-' }}</div>
                                </td>
                                <td class="px-4 py-4">
                                    <span class="inline-flex rounded-md px-2 py-1 text-xs font-medium {{ $statusStyle }}">
                                        {{ $statusLabel }}
                                    </span>
                                </td>
                                <td class="px-4 py-4 text-gray-700 dark:text-gray-300">
                                    @if($row['target'])
                                        <div class="font-medium">{{ $row['target']['nama'] }}</div>
                                        <div class="text-xs text-gray-500">
                                            ID {{ $row['target']['id'] }} | NISN {{ $row['target']['nisn'] ?: '-' }} |
                                            {{ $row['target']['rombel'] ?: 'Belum ada rombel' }}
                                        </div>
                                    @else
                                        <span class="text-gray-400">Belum ada pasangan</span>
                                    @endif
                                </td>
                                <td class="px-4 py-4">
                                    @if($row['errors'])
                                        <ul class="mb-2 list-disc space-y-1 pl-4 text-xs text-danger-600">
                                            @foreach($row['errors'] as $error)
                                                <li>{{ $error }}</li>
                                            @endforeach
                                        </ul>
                                    @endif

                                    @if($row['status'] === 'konflik' && !$row['errors'])
                                        <label class="mb-1 block text-xs font-medium text-gray-700 dark:text-gray-300">
                                            Penyelesaian
                                        </label>
                                        <select
                                            wire:model="resolutions.{{ $row['source_id'] }}"
                                            class="w-full rounded-lg border-gray-300 text-sm dark:border-white/10 dark:bg-gray-900"
                                        >
                                            <option value="skip">Lewati</option>
                                            <option value="new">Buat sebagai siswa baru</option>
                                            @foreach($row['candidates'] as $candidate)
                                                <option value="{{ $candidate['id'] }}">
                                                    Hubungkan: {{ $candidate['nama'] }} | {{ $candidate['nisn'] ?: 'tanpa NISN' }}
                                                </option>
                                            @endforeach
                                        </select>
                                    @elseif($row['differences'])
                                        <ul class="space-y-1 text-xs text-gray-600 dark:text-gray-300">
                                            @foreach(array_slice($row['differences'], 0, 5) as $difference)
                                                <li>
                                                    <span class="font-medium">{{ str($difference['field'])->replace('_', ' ')->title() }}:</span>
                                                    {{ filled($difference['local']) ? $difference['local'] : '-' }}
                                                    <span aria-hidden="true">&rarr;</span>
                                                    {{ filled($difference['source']) ? $difference['source'] : '-' }}
                                                </li>
                                            @endforeach
                                            @if(count($row['differences']) > 5)
                                                <li class="text-gray-400">+{{ count($row['differences']) - 5 }} perubahan lain</li>
                                            @endif
                                        </ul>
                                    @else
                                        <span class="text-xs text-gray-400">Tidak ada perubahan</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-6 py-12 text-center text-gray-500">
                                    Tekan <strong>Ambil Preview</strong> untuk memeriksa siswa lulus dari SPMB.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-gray-900">
            <div class="border-b border-gray-200 px-4 py-3 dark:border-white/10">
                <h2 class="text-sm font-semibold text-gray-950 dark:text-white">Riwayat Sinkronisasi</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[760px] text-left text-sm">
                    <thead class="bg-gray-50 text-xs uppercase text-gray-600 dark:bg-white/5 dark:text-gray-300">
                        <tr>
                            <th class="px-4 py-3">Waktu</th>
                            <th class="px-4 py-3">Operator</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3">Ringkasan</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                        @forelse($recentRuns as $run)
                            <tr>
                                <td class="px-4 py-3">{{ $run->started_at?->format('d M Y H:i:s') }}</td>
                                <td class="px-4 py-3">{{ $run->user?->name ?: $run->user?->email ?: '-' }}</td>
                                <td class="px-4 py-3">{{ str($run->status)->title() }}</td>
                                <td class="px-4 py-3">{{ $run->message ?: '-' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-4 py-8 text-center text-gray-500">Belum ada riwayat sinkronisasi.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</x-filament-panels::page>
