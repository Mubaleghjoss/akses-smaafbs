<x-filament-panels::page>
    <div class="sigap-page">
        <x-filament::section>
            <x-slot name="heading">Rentang Tanggal</x-slot>
            <x-slot name="description">
                Pilih rentang tanggal untuk melihat kelas mana yang terkena catatan SIGAP dan kelas mana yang bersih.
            </x-slot>

            <div class="sigap-filter-grid">
                <div class="sigap-field">
                    <label for="rekap-dari">Dari Tanggal</label>
                    <input id="rekap-dari" type="date" wire:model.live="dari" />
                </div>
                <div class="sigap-field">
                    <label for="rekap-sampai">Sampai Tanggal</label>
                    <input id="rekap-sampai" type="date" wire:model.live="sampai" />
                </div>
                <div class="sigap-field">
                    <label for="rekap-kategori">Kategori</label>
                    <select id="rekap-kategori" wire:model.live="kategori">
                        <option value="">Semua kategori</option>
                        @foreach ($kategoriOptions as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="sigap-field">
                    <label for="rekap-tingkat">Tingkat</label>
                    <select id="rekap-tingkat" wire:model.live="tingkat">
                        <option value="">Semua tingkat</option>
                        @foreach ($tingkatOptions as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="sigap-filter-actions">
                <x-filament::button size="sm" color="gray" wire:click="terapkanBulanIni">Bulan Ini</x-filament::button>
                <x-filament::button size="sm" color="gray" wire:click="terapkanBulanLalu">Bulan Lalu</x-filament::button>
                <x-filament::button size="sm" color="gray" wire:click="terapkanSemester">Semester Berjalan</x-filament::button>
                <x-filament::button size="sm" color="danger" outlined wire:click="resetFilter">Reset Kategori &amp; Tingkat</x-filament::button>
            </div>

            <p class="sigap-period">Periode aktif: <strong>{{ $periodeLabel }}</strong></p>
        </x-filament::section>

        @php
            $cards = [
                ['label' => 'Total Kasus', 'value' => $recap['ringkasan']['total_kasus'], 'hint' => 'Laporan SIGAP pada periode ini', 'tone' => 'primary'],
                ['label' => 'Siswa Terlibat', 'value' => $recap['ringkasan']['total_siswa'], 'hint' => $recap['ringkasan']['total_keterlibatan'].' total keterlibatan', 'tone' => 'info'],
                ['label' => 'Kelas Terkena', 'value' => $recap['ringkasan']['kelas_terdampak'], 'hint' => 'dari '.$recap['ringkasan']['kelas_aktif'].' kelas aktif', 'tone' => 'warning'],
                ['label' => 'Kelas Bersih', 'value' => $recap['ringkasan']['kelas_bersih'], 'hint' => 'Tidak ada catatan SIGAP', 'tone' => 'success'],
            ];
        @endphp

        <div class="sigap-summary-grid">
            @foreach ($cards as $card)
                <article class="sigap-summary-card sigap-summary-card--{{ $card['tone'] }}">
                    <p class="sigap-summary-label">{{ $card['label'] }}</p>
                    <p class="sigap-summary-value">{{ $card['value'] }}</p>
                    <p class="sigap-summary-hint">{{ $card['hint'] }}</p>
                </article>
            @endforeach
        </div>

        <div class="sigap-status-grid">
            <article class="sigap-status-card sigap-status-card--danger">
                <p>Belum Ditindak</p>
                <strong>{{ $recap['ringkasan']['belum_ditindak'] }} kasus</strong>
            </article>
            <article class="sigap-status-card sigap-status-card--success">
                <p>Tindak Lanjut Selesai</p>
                <strong>{{ $recap['ringkasan']['selesai'] }} kasus</strong>
            </article>
        </div>

        <x-filament::section>
            <x-slot name="heading">Kelas yang Terkena Catatan SIGAP</x-slot>
            <x-slot name="description">Nama siswa dikelompokkan per kelas berdasarkan rombel saat kasus dicatat.</x-slot>

            @if (empty($recap['kelas_terdampak']))
                <p class="sigap-empty">Tidak ada catatan SIGAP pada rentang tanggal ini. Seluruh kelas aktif bersih.</p>
            @else
                <div class="sigap-class-list">
                    @foreach ($recap['kelas_terdampak'] as $kelas)
                        <article class="sigap-class-card">
                            <header class="sigap-class-header">
                                <p class="sigap-class-title">{{ $kelas['kelas'] }}</p>
                                <div class="sigap-badge-row">
                                    <span class="sigap-badge sigap-badge--primary">{{ $kelas['jumlah_siswa'] }} siswa</span>
                                    <span class="sigap-badge sigap-badge--warning">{{ $kelas['jumlah_kasus'] }} kasus</span>
                                </div>
                            </header>

                            <div class="sigap-table-wrap">
                                <table class="sigap-table">
                                    <thead>
                                        <tr>
                                            <th>Nama Siswa</th>
                                            <th>Jumlah Kasus</th>
                                            <th>Rincian Kasus</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($kelas['siswa'] as $siswa)
                                            <tr>
                                                <td data-label="Nama Siswa" class="sigap-student-name">{{ $siswa['nama'] }}</td>
                                                <td data-label="Jumlah Kasus">{{ $siswa['jumlah_kasus'] }}</td>
                                                <td data-label="Rincian Kasus">
                                                    <ul class="sigap-detail-list">
                                                        @foreach ($siswa['kasus'] as $kasus)
                                                            <li>
                                                                <strong>{{ \Illuminate\Support\Carbon::parse($kasus['tanggal'])->format('d/m/Y') }}</strong>
                                                                &mdash; {{ $kasus['judul'] }}
                                                                <small>({{ $kasus['kategori'] }} &middot; {{ $kasus['tingkat'] }} &middot; {{ $kasus['status'] }})</small>
                                                            </li>
                                                        @endforeach
                                                    </ul>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>

                            <div class="sigap-follow-up">
                                <p class="sigap-follow-up-title">Tindak Lanjut per Kasus</p>
                                <ul class="sigap-follow-up-list">
                                    @foreach ($kelas['kasus'] as $kasus)
                                        <li>
                                            <p class="sigap-case-title">
                                                {{ \Illuminate\Support\Carbon::parse($kasus['tanggal'])->format('d/m/Y') }} &mdash; {{ $kasus['judul'] }}
                                                <span>[{{ $kasus['status_label'] }}]</span>
                                            </p>
                                            <p class="sigap-case-note">{{ filled($kasus['tindak_lanjut']) ? $kasus['tindak_lanjut'] : 'Belum ada tindak lanjut yang dicatat.' }}</p>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        </article>
                    @endforeach
                </div>
            @endif
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">Kelas Tanpa Catatan SIGAP</x-slot>
            <x-slot name="description">Kelas aktif yang tidak memiliki catatan SIGAP pada rentang tanggal terpilih.</x-slot>

            @if (empty($recap['kelas_bersih']))
                <p class="sigap-notice sigap-notice--warning">Semua kelas aktif memiliki catatan SIGAP pada rentang tanggal ini.</p>
            @else
                <div class="sigap-clean-list">
                    @foreach ($recap['kelas_bersih'] as $kelas)
                        <span class="sigap-clean-badge">{{ $kelas }}</span>
                    @endforeach
                </div>
            @endif

            @if (! empty($recap['kelas_tanpa_master']))
                <div class="sigap-notice sigap-notice--warning">
                    <p><strong>Kelas di luar master rombel aktif:</strong></p>
                    <p>{{ implode(', ', $recap['kelas_tanpa_master']) }}</p>
                    <small>Nama kelas ini muncul pada data kasus tetapi tidak ada di daftar rombel aktif — biasanya siswa sudah mutasi/lulus atau penulisan rombel berbeda.</small>
                </div>
            @endif
        </x-filament::section>
    </div>
</x-filament-panels::page>
