{{--
    Kartu KELENGKAPAN NILAI — muncul di halaman Cetak Rapor.

    Menjawab satu pertanyaan yang paling sering ditanyakan sebelum mencetak:
    "mapel apa saja yang belum ada nilainya?"

    Dua keadaan sengaja DIPISAH, karena yang perlu ditagih berbeda:
      merah   = belum ada nilainya      -> tagih GURU pengampu, rapor SEMENTARA
      kuning  = sudah ada, belum verif  -> tagih KURIKULUM, isi rapor sudah utuh
--}}
@php
    $barisKelengkapan = $this->getKelengkapanKelasTerpilih();
    $ringkasKelengkapan = $this->getRingkasanKelengkapan();
@endphp

@if (filled($barisKelengkapan))
    <x-filament::section
        :collapsible="true"
        :collapsed="! $ringkasKelengkapan['ada_sementara']"
        class="asmt-completeness"
    >
        <x-slot name="heading">
            <span class="asmt-completeness__title">
                Kelengkapan Nilai
                @if ($ringkasKelengkapan['ada_sementara'])
                    <span class="asmt-badge asmt-badge--danger">
                        {{ $ringkasKelengkapan['kelas_sementara'] }} kelas SEMENTARA
                    </span>
                @else
                    <span class="asmt-badge asmt-badge--success">Semua nilai lengkap</span>
                @endif
            </span>
        </x-slot>

        <x-slot name="description">
            Diperiksa untuk {{ $ringkasKelengkapan['jumlah_kelas'] }} kelas terpilih.
            Rapor tetap dapat dicetak meski belum lengkap — nilai yang kosong
            tertulis <strong>(belum diisi)</strong> dan rapornya ditandai SEMENTARA.
        </x-slot>

        <div class="asmt-completeness__list">
            @foreach ($barisKelengkapan as $kelas)
                <div class="asmt-kelas {{ $kelas['sementara'] ? 'asmt-kelas--warning' : '' }}">
                    <div class="asmt-kelas__head">
                        <div class="asmt-kelas__name">
                            {{ $kelas['rombel'] }}
                            @if ($kelas['sementara'])
                                <span class="asmt-badge asmt-badge--danger">SEMENTARA</span>
                            @elseif ($kelas['jumlah_belum_verifikasi'] > 0)
                                <span class="asmt-badge asmt-badge--warning">Menunggu verifikasi</span>
                            @else
                                <span class="asmt-badge asmt-badge--success">FINAL</span>
                            @endif
                        </div>
                        <div class="asmt-kelas__meta">
                            {{ $kelas['total_mapel'] }} mapel · {{ $kelas['total_siswa'] ?? 0 }} siswa
                        </div>
                    </div>

                    <p class="asmt-kelas__summary">{{ $kelas['ringkasan'] }}</p>

                    @if (filled($kelas['mapel_belum_diisi']))
                        <div class="asmt-group asmt-group--danger">
                            <div class="asmt-group__label">
                                Belum ada nilainya — tagih guru pengampu
                            </div>
                            <ul class="asmt-group__items">
                                @foreach ($kelas['mapel_belum_diisi'] as $mapel)
                                    <li>
                                        <span class="asmt-mapel">{{ $mapel['mapel'] }}</span>
                                        <span class="asmt-guru">{{ $mapel['guru'] }}</span>
                                        @if (! is_null($mapel['siswa_kosong']))
                                            <span class="asmt-count">
                                                {{ $mapel['siswa_kosong'] }} dari {{ $mapel['siswa_total'] }} siswa kosong
                                            </span>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    @if (filled($kelas['mapel_belum_verifikasi']))
                        <div class="asmt-group asmt-group--warning">
                            <div class="asmt-group__label">
                                Nilai sudah ada, menunggu verifikasi kurikulum
                            </div>
                            <ul class="asmt-group__items">
                                @foreach ($kelas['mapel_belum_verifikasi'] as $mapel)
                                    <li>
                                        <span class="asmt-mapel">{{ $mapel['mapel'] }}</span>
                                        <span class="asmt-guru">{{ $mapel['guru'] }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    </x-filament::section>
@endif
