@php
    $timeline = $analytics['class_submission_timeline'] ?? [];
    $angka = fn ($value): string => number_format((float) $value, 0, ',', '.');
    $persen = fn ($value): string => $value === null ? '-' : number_format((float) $value, 1, ',', '.').'%';
    $tanggal = fn ($value): string => $value ? $value->translatedFormat('d M Y H:i') : '-';
    $hari = fn ($value): string => $value ? \Illuminate\Support\Carbon::parse($value)->translatedFormat('d M Y') : '-';
@endphp

<x-filament::section collapsible>
    <x-slot name="heading">Timeline Pengisian Per Kelas</x-slot>
    <x-slot name="description">
        Kapan setiap kelas mulai dan terakhir mengisi pada rentang aktif, berapa hari kelas itu aktif, dan
        hari dengan pengisian terbanyak. Urut dari kelas yang paling awal mulai. Klik nama kelas untuk
        melihat rincian per hari: berapa yang mengisi, siapa saja, dan siapa yang belum.
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
                    <th class="is-num">Siswa</th>
                    <th class="is-num">Dispensasi</th>
                    <th>Mulai</th>
                    <th>Terakhir</th>
                    <th class="is-num">Hari Aktif</th>
                    <th>Hari Tersibuk</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($timeline as $row)
                    @php
                        $p = $row['percentage'] ?? null;
                        $days = $row['days'] ?? [];
                        $belum = $row['missing_students'] ?? [];
                        $dispensasi = $row['excluded_students'] ?? [];
                        $sampah = $row['trashed_students'] ?? [];
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
                        <td class="is-num" data-label="Siswa">{{ $angka($row['unique_students'] ?? 0) }}</td>
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
                    </tr>
                    <tr class="lit-drillRow">
                        <td colspan="9">
                            <details class="lit-detail">
                                <summary>
                                    <span>Rincian harian {{ $row['class'] }}</span>
                                    <span class="lit-detail__count">
                                        {{ $angka($row['response_records'] ?? 0) }} jawaban &middot;
                                        {{ $angka($row['active_days'] ?? 0) }} hari &middot;
                                        belum {{ $angka($row['missing_total'] ?? 0) }}
                                    </span>
                                </summary>
                                <div class="lit-detail__body">
                                    <p class="lit-note">
                                        Total pengisian {{ $angka($row['total'] ?? 0) }} dihitung dari
                                        {{ $angka($row['active_total'] ?? 0) }} slot siswa aktif
                                        &minus; {{ $angka($row['excluded_total'] ?? 0) }} slot dispensasi
                                        = {{ $angka($row['respondent_base'] ?? 0) }} basis responden
                                        ({{ $angka($row['unique_students'] ?? 0) }} siswa pada kelas ini).
                                        Jumlah baris jawaban pada rentang ini {{ $angka($row['response_records'] ?? 0) }};
                                        bila lebih besar dari total, selisihnya berasal dari siswa yang sudah tidak aktif
                                        atau berpindah rombel sehingga tidak masuk basis.
                                    </p>

                                    @if ($days !== [])
                                        <div class="lit-tableWrap">
                                            <table class="lit-table">
                                                <thead>
                                                    <tr>
                                                        <th>Tanggal</th>
                                                        <th class="is-num">Mengisi</th>
                                                        <th>Jam</th>
                                                        <th>Siswa yang mengisi</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @foreach ($days as $day)
                                                        <tr>
                                                            <td class="is-name" data-label="Tanggal">{{ $hari($day['date'] ?? null) }}</td>
                                                            <td class="is-num is-strong" data-label="Mengisi">{{ $angka($day['total'] ?? 0) }}</td>
                                                            <td class="is-muted" data-label="Jam">
                                                                {{ ($day['first_at'] ?? null) ? $day['first_at']->format('H:i') : '-' }}
                                                                &ndash;
                                                                {{ ($day['last_at'] ?? null) ? $day['last_at']->format('H:i') : '-' }}
                                                            </td>
                                                            <td data-label="Siswa yang mengisi">
                                                                @foreach ($day['students'] ?? [] as $student)
                                                                    <span class="lit-chip">
                                                                        {{ $student['name'] }}
                                                                        <span class="lit-chip__meta">{{ $student['time'] }} &middot; {{ $student['material_title'] }}</span>
                                                                    </span>
                                                                @endforeach
                                                            </td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                    @else
                                        <p class="lit-empty">Belum ada pengisian pada rentang ini.</p>
                                    @endif

                                    <p class="lit-subhead">Belum Mengisi ({{ $angka($row['missing_total'] ?? 0) }})</p>
                                    @if ($belum !== [])
                                        <ol class="lit-list">
                                            @foreach ($belum as $student)
                                                <li>{{ $student['name'] }} <span class="lit-muted">&mdash; {{ $student['material_title'] }}</span></li>
                                            @endforeach
                                        </ol>
                                    @else
                                        <p class="lit-empty">Semua siswa pada basis responden sudah mengisi.</p>
                                    @endif

                                    @if ($dispensasi !== [])
                                        <p class="lit-subhead">Dispensasi &mdash; tidak dihitung ({{ $angka($row['excluded_total'] ?? 0) }})</p>
                                        <ol class="lit-list">
                                            @foreach ($dispensasi as $student)
                                                <li>
                                                    {{ $student['name'] }}
                                                    <span class="lit-muted">&mdash; {{ $student['reason_label'] }} &middot; {{ $student['material_title'] }}</span>
                                                </li>
                                            @endforeach
                                        </ol>
                                    @endif

                                    @if ($sampah !== [])
                                        <p class="lit-subhead">Jawaban di Sampah ({{ $angka($row['trashed_total'] ?? 0) }})</p>
                                        <ol class="lit-list">
                                            @foreach ($sampah as $student)
                                                <li>{{ $student['name'] }} <span class="lit-muted">&mdash; {{ $student['material_title'] }}</span></li>
                                            @endforeach
                                        </ol>
                                    @endif
                                </div>
                            </details>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="lit-empty">Belum ada pengisian pada rentang ini.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-filament::section>
