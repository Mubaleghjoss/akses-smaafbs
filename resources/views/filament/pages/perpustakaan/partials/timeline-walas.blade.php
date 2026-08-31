@php
    $timeline = $analytics['class_submission_timeline'] ?? [];
    $angka = fn ($value): string => number_format((float) $value, 0, ',', '.');
    $persen = fn ($value): string => $value === null ? '-' : number_format((float) $value, 1, ',', '.').'%';
    $tanggal = fn ($value): string => $value ? $value->translatedFormat('d M Y H:i') : '-';
    $hari = fn ($value): string => $value ? \Illuminate\Support\Carbon::parse($value)->translatedFormat('d M Y') : '-';

    // Tautan rincian dibuat di sini supaya tabel tidak perlu tahu detail halaman
    // Filament. Filter aktif diteruskan agar rincian memakai lingkup yang sama.
    $tautanRincian = fn (string $kelas): ?string => \App\Filament\Pages\Perpustakaan\RincianHarianKelasPage::urlForClass(
        $kelas,
        $dari ?? null,
        $sampai ?? null,
        $kategori ?? null,
        $materi ?? null,
    );
@endphp

<x-filament::section collapsible collapsed>
    <x-slot name="heading">Timeline Pengisian Per Kelas</x-slot>
    <x-slot name="description">
        Kapan setiap kelas mulai dan terakhir mengisi pada rentang aktif, berapa hari kelas itu aktif, dan
        hari dengan pengisian terbanyak. Urut dari kelas yang paling awal mulai. Rincian harian &mdash; siapa yang
        mengisi tiap hari dan siapa yang belum &mdash; dibuka pada halaman tersendiri lewat tombol "Rincian harian"
        agar tabel ini tetap ringkas.
    </x-slot>

    @include('filament.pages.perpustakaan.partials.salin-bagian', [
        'teks' => $salinTeks ?? ($shareSections['timeline'] ?? ''),
        'catatan' => 'Menyalin timeline pengisian seluruh kelas, termasuk rincian per hari dan daftar siswa yang belum mengisi.',
    ])

    <p class="lit-note">
        Penyebut tiap kelas adalah <strong>basis responden</strong> = slot siswa aktif (siswa x materi pada rentang ini)
        dikurangi slot dispensasi terkonfirmasi. Siswa izin/sakit/tes MT tidak masuk pembilang maupun penyebut,
        sehingga kelas yang seluruh sisanya sudah mengisi tetap bisa mencapai 100%.
    </p>

    <div class="lit-tableWrap">
        <table class="lit-table">
            <thead>
                <tr>
                    <th>Kelas</th>
                    <th class="is-num">Pengisian</th>
                    <th class="is-num">Persen</th>
                    <th class="is-num">Materi</th>
                    <th class="is-num">Siswa</th>
                    <th class="is-num">Belum</th>
                    <th class="is-num">Dispensasi</th>
                    <th>Mulai</th>
                    <th>Terakhir</th>
                    <th class="is-num">Hari Aktif</th>
                    <th>Hari Tersibuk</th>
                    <th>Rincian</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($timeline as $row)
                    @php
                        $p = $row['percentage'] ?? null;
                        $urlRincian = $tautanRincian((string) $row['class']);
                    @endphp
                    <tr>
                        <td class="is-name" data-label="Kelas">{{ $row['class'] }}</td>
                        <td class="is-num" data-label="Pengisian">
                            {{ $angka($row['total'] ?? 0) }}/{{ $angka($row['respondent_base'] ?? 0) }}
                        </td>
                        <td class="is-num" data-label="Persen">
                            <span class="lit-pct {{ $p === null ? 'is-none' : ($p >= 80 ? 'is-good' : ($p >= 50 ? 'is-warn' : 'is-bad')) }}">
                                {{ $persen($p) }}
                            </span>
                        </td>
                        <td class="is-num" data-label="Materi">{{ $angka($row['material_count'] ?? 0) }}</td>
                        <td class="is-num" data-label="Siswa">{{ $angka($row['unique_students'] ?? 0) }}</td>
                        <td class="is-num" data-label="Belum">{{ $angka($row['missing_total'] ?? 0) }}</td>
                        <td class="is-num" data-label="Dispensasi">{{ $angka($row['excluded_total'] ?? 0) }}</td>
                        <td data-label="Mulai">{{ $tanggal($row['first_at'] ?? null) }}</td>
                        <td data-label="Terakhir">{{ $tanggal($row['last_at'] ?? null) }}</td>
                        <td class="is-num" data-label="Hari Aktif">
                            {{ $angka($row['active_days'] ?? 0) }}
                            <span class="lit-timeline__span">dari {{ $angka($row['span_days'] ?? 0) }} hari</span>
                        </td>
                        <td data-label="Hari Tersibuk">
                            {{ $hari($row['busiest_day'] ?? null) }}
                            <span class="lit-timeline__span">{{ $angka($row['busiest_day_total'] ?? 0) }} pengisian</span>
                        </td>
                        <td data-label="Rincian">
                            @if ($urlRincian)
                                <a class="lit-link" href="{{ $urlRincian }}">Rincian harian</a>
                            @else
                                <span class="lit-muted">Tidak tersedia</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="12" class="lit-empty">Belum ada pengisian pada rentang ini.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-filament::section>
