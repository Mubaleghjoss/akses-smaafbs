<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Filter rentang tanggal --}}
        <x-filament::section>
            <x-slot name="heading">Rentang Tanggal</x-slot>
            <x-slot name="description">
                Pilih rentang tanggal untuk melihat kelas mana yang terkena catatan SIGAP dan kelas mana yang bersih.
            </x-slot>

            <div class="grid gap-4 md:grid-cols-4">
                <div>
                    <label for="rekap-dari" class="block text-sm font-medium text-gray-700 dark:text-gray-200">Dari Tanggal</label>
                    <input
                        id="rekap-dari"
                        type="date"
                        wire:model.live="dari"
                        class="mt-1 block w-full rounded-lg border-gray-300 bg-white text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100"
                    />
                </div>
                <div>
                    <label for="rekap-sampai" class="block text-sm font-medium text-gray-700 dark:text-gray-200">Sampai Tanggal</label>
                    <input
                        id="rekap-sampai"
                        type="date"
                        wire:model.live="sampai"
                        class="mt-1 block w-full rounded-lg border-gray-300 bg-white text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100"
                    />
                </div>
                <div>
                    <label for="rekap-kategori" class="block text-sm font-medium text-gray-700 dark:text-gray-200">Kategori</label>
                    <select
                        id="rekap-kategori"
                        wire:model.live="kategori"
                        class="mt-1 block w-full rounded-lg border-gray-300 bg-white text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100"
                    >
                        <option value="">Semua kategori</option>
                        @foreach ($kategoriOptions as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="rekap-tingkat" class="block text-sm font-medium text-gray-700 dark:text-gray-200">Tingkat</label>
                    <select
                        id="rekap-tingkat"
                        wire:model.live="tingkat"
                        class="mt-1 block w-full rounded-lg border-gray-300 bg-white text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100"
                    >
                        <option value="">Semua tingkat</option>
                        @foreach ($tingkatOptions as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="mt-4 flex flex-wrap gap-2">
                <x-filament::button size="sm" color="gray" wire:click="terapkanBulanIni">Bulan Ini</x-filament::button>
                <x-filament::button size="sm" color="gray" wire:click="terapkanBulanLalu">Bulan Lalu</x-filament::button>
                <x-filament::button size="sm" color="gray" wire:click="terapkanSemester">Semester Berjalan</x-filament::button>
                <x-filament::button size="sm" color="danger" outlined wire:click="resetFilter">Reset Kategori &amp; Tingkat</x-filament::button>
            </div>

            <p class="mt-4 text-sm text-gray-600 dark:text-gray-300">
                Periode aktif: <span class="font-semibold">{{ $periodeLabel }}</span>
            </p>
        </x-filament::section>

        {{-- Ringkasan angka --}}
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            @php
                $cards = [
                    ['label' => 'Total Kasus', 'value' => $recap['ringkasan']['total_kasus'], 'hint' => 'Laporan SIGAP pada periode ini'],
                    ['label' => 'Siswa Terlibat', 'value' => $recap['ringkasan']['total_siswa'], 'hint' => $recap['ringkasan']['total_keterlibatan'].' total keterlibatan'],
                    ['label' => 'Kelas Terkena', 'value' => $recap['ringkasan']['kelas_terdampak'], 'hint' => 'dari '.$recap['ringkasan']['kelas_aktif'].' kelas aktif'],
                    ['label' => 'Kelas Bersih', 'value' => $recap['ringkasan']['kelas_bersih'], 'hint' => 'Tidak ada catatan SIGAP'],
                ];
            @endphp

            @foreach ($cards as $card)
                <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                    <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ $card['label'] }}</p>
                    <p class="mt-1 text-3xl font-bold text-gray-900 dark:text-gray-50">{{ $card['value'] }}</p>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $card['hint'] }}</p>
                </div>
            @endforeach
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div class="rounded-xl border border-danger-200 bg-danger-50 p-4 dark:border-danger-800 dark:bg-danger-950/40">
                <p class="text-sm font-semibold text-danger-700 dark:text-danger-300">Belum Ditindak</p>
                <p class="mt-1 text-2xl font-bold text-danger-700 dark:text-danger-200">{{ $recap['ringkasan']['belum_ditindak'] }} kasus</p>
            </div>
            <div class="rounded-xl border border-success-200 bg-success-50 p-4 dark:border-success-800 dark:bg-success-950/40">
                <p class="text-sm font-semibold text-success-700 dark:text-success-300">Tindak Lanjut Selesai</p>
                <p class="mt-1 text-2xl font-bold text-success-700 dark:text-success-200">{{ $recap['ringkasan']['selesai'] }} kasus</p>
            </div>
        </div>

        {{-- Kelas yang terkena catatan SIGAP --}}
        <x-filament::section>
            <x-slot name="heading">Kelas yang Terkena Catatan SIGAP</x-slot>
            <x-slot name="description">Nama siswa dikelompokkan per kelas berdasarkan rombel saat kasus dicatat.</x-slot>

            @if (empty($recap['kelas_terdampak']))
                <p class="rounded-lg bg-gray-50 p-4 text-sm text-gray-600 dark:bg-gray-800 dark:text-gray-300">
                    Tidak ada catatan SIGAP pada rentang tanggal ini. Seluruh kelas aktif bersih.
                </p>
            @else
                <div class="space-y-4">
                    @foreach ($recap['kelas_terdampak'] as $kelas)
                        <div class="overflow-hidden rounded-xl border border-gray-200 dark:border-gray-700">
                            <div class="flex flex-wrap items-center justify-between gap-2 bg-gray-50 px-4 py-3 dark:bg-gray-800">
                                <p class="text-base font-semibold text-gray-900 dark:text-gray-50">{{ $kelas['kelas'] }}</p>
                                <div class="flex flex-wrap gap-2 text-xs">
                                    <span class="rounded-full bg-primary-100 px-2.5 py-1 font-semibold text-primary-700 dark:bg-primary-900/50 dark:text-primary-200">
                                        {{ $kelas['jumlah_siswa'] }} siswa
                                    </span>
                                    <span class="rounded-full bg-warning-100 px-2.5 py-1 font-semibold text-warning-700 dark:bg-warning-900/50 dark:text-warning-200">
                                        {{ $kelas['jumlah_kasus'] }} kasus
                                    </span>
                                </div>
                            </div>

                            <div class="overflow-x-auto">
                                <table class="w-full text-sm">
                                    <thead class="bg-white text-left text-xs uppercase tracking-wide text-gray-500 dark:bg-gray-900 dark:text-gray-400">
                                        <tr>
                                            <th class="px-4 py-2 font-semibold">Nama Siswa</th>
                                            <th class="px-4 py-2 font-semibold">Jumlah Kasus</th>
                                            <th class="px-4 py-2 font-semibold">Rincian Kasus</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                        @foreach ($kelas['siswa'] as $siswa)
                                            <tr class="bg-white dark:bg-gray-900">
                                                <td class="px-4 py-2 font-medium text-gray-900 dark:text-gray-100">{{ $siswa['nama'] }}</td>
                                                <td class="px-4 py-2 text-gray-700 dark:text-gray-300">{{ $siswa['jumlah_kasus'] }}</td>
                                                <td class="px-4 py-2 text-gray-600 dark:text-gray-400">
                                                    <ul class="space-y-1">
                                                        @foreach ($siswa['kasus'] as $kasus)
                                                            <li>
                                                                <span class="font-medium">{{ \Illuminate\Support\Carbon::parse($kasus['tanggal'])->format('d/m/Y') }}</span>
                                                                &mdash; {{ $kasus['judul'] }}
                                                                <span class="text-xs text-gray-500 dark:text-gray-500">({{ $kasus['kategori'] }} &middot; {{ $kasus['tingkat'] }} &middot; {{ $kasus['status'] }})</span>
                                                            </li>
                                                        @endforeach
                                                    </ul>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>

                            <div class="border-t border-gray-200 bg-gray-50/60 px-4 py-3 dark:border-gray-700 dark:bg-gray-800/40">
                                <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Tindak Lanjut per Kasus</p>
                                <ul class="space-y-2 text-sm">
                                    @foreach ($kelas['kasus'] as $kasus)
                                        <li class="rounded-lg bg-white p-3 dark:bg-gray-900">
                                            <p class="font-medium text-gray-900 dark:text-gray-100">
                                                {{ \Illuminate\Support\Carbon::parse($kasus['tanggal'])->format('d/m/Y') }} &mdash; {{ $kasus['judul'] }}
                                                <span class="ml-1 text-xs font-normal text-gray-500 dark:text-gray-400">[{{ $kasus['status_label'] }}]</span>
                                            </p>
                                            <p class="mt-1 text-gray-600 dark:text-gray-300">
                                                {{ filled($kasus['tindak_lanjut']) ? $kasus['tindak_lanjut'] : 'Belum ada tindak lanjut yang dicatat.' }}
                                            </p>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </x-filament::section>

        {{-- Kelas tanpa kasus --}}
        <x-filament::section>
            <x-slot name="heading">Kelas Tanpa Catatan SIGAP</x-slot>
            <x-slot name="description">Kelas aktif yang tidak memiliki catatan SIGAP pada rentang tanggal terpilih.</x-slot>

            @if (empty($recap['kelas_bersih']))
                <p class="rounded-lg bg-warning-50 p-4 text-sm text-warning-700 dark:bg-warning-950/40 dark:text-warning-200">
                    Semua kelas aktif memiliki catatan SIGAP pada rentang tanggal ini.
                </p>
            @else
                <div class="flex flex-wrap gap-2">
                    @foreach ($recap['kelas_bersih'] as $kelas)
                        <span class="rounded-full border border-success-200 bg-success-50 px-3 py-1 text-sm font-semibold text-success-700 dark:border-success-800 dark:bg-success-950/40 dark:text-success-200">
                            {{ $kelas }}
                        </span>
                    @endforeach
                </div>
            @endif

            @if (! empty($recap['kelas_tanpa_master']))
                <div class="mt-4 rounded-lg border border-warning-200 bg-warning-50 p-3 text-sm text-warning-800 dark:border-warning-800 dark:bg-warning-950/40 dark:text-warning-200">
                    <p class="font-semibold">Kelas di luar master rombel aktif:</p>
                    <p class="mt-1">{{ implode(', ', $recap['kelas_tanpa_master']) }}</p>
                    <p class="mt-1 text-xs">Nama kelas ini muncul pada data kasus tetapi tidak ada di daftar rombel aktif — biasanya siswa sudah mutasi/lulus atau penulisan rombel berbeda.</p>
                </div>
            @endif
        </x-filament::section>
    </div>
</x-filament-panels::page>
