@php
    $angka = fn ($value): string => number_format((float) $value, 0, ',', '.');
    $persen = fn ($value): string => $value === null ? '-' : number_format((float) $value, 1, ',', '.').'%';
    $hari = fn ($value): string => $value ? \Illuminate\Support\Carbon::parse($value)->translatedFormat('d M Y') : '-';
    $partisipasiTertinggi = $analytics['class_response_ranking'] ?? [];
    $partisipasiTerendah = $analytics['least_class_response_ranking'] ?? [];
    $kelasBenar = $analytics['class_correct_ranking'] ?? [];
    $siswaPerKelas = $analytics['student_correct_ranking_by_class'] ?? [];
    $siswaSalah = $analytics['student_wrong_ranking'] ?? [];
    $belumDinilai = $analytics['class_pending_grading'] ?? [];
    $peringkatBenar = $analytics['class_correct_ranking_full'] ?? [];
    $akurasiPerKelas = collect($kelasBenar)
        ->keyBy('class')
        ->union(collect(array_keys($belumDinilai))->mapWithKeys(fn (string $kelas): array => [
            $kelas => [
                'class' => $kelas,
                'correct_answers' => 0,
                'graded_answers' => 0,
                'accuracy' => null,
            ],
        ]))
        ->sortKeys(SORT_NATURAL);

    // Tautan penilaian dibuat sekali di sini supaya tabel akurasi tidak perlu
    // tahu detail resource Filament. Kelas diteruskan agar daftar jawaban di
    // halaman materi langsung terfilter ke kelas dan jawaban yang belum dinilai.
    $tautanMateri = fn (int $materialId, ?string $kelas = null): ?string => \App\Filament\Resources\PerpustakaanLiterasiMaterialResource::gradingUrl($materialId, $kelas);
@endphp

<x-filament::section>
    <x-slot name="heading">Ranking Kelas &amp; Siswa</x-slot>
    <x-slot name="description">
        Empat sudut pandang pada lingkup yang sama: partisipasi tertinggi, kelas perlu perhatian, akurasi
        jawaban, dan siswa dengan jawaban salah terbanyak. Setiap kelas bisa dibuka untuk melihat asal
        angkanya per hari.
    </x-slot>

    @include('filament.pages.perpustakaan.partials.salin-bagian', [
        'teks' => $salinTeks ?? '',
        'catatan' => 'Menyalin seluruh ranking termasuk rincian harian per kelas, siswa terbaik, dan materi yang belum dinilai.',
    ])

    <div class="lit-duo">
        <div>
            <p class="lit-subhead">7 Kelas Jawaban Terbanyak</p>
            <p class="lit-note">
                Perbandingan sudah adil antar kelas: kolom <strong>Jawaban</strong> adalah slot terisi
                (siswa &times; materi), dan <strong>Rasio</strong> membandingkannya dengan basis responden kelas itu
                sendiri &mdash; yaitu slot siswa aktif dikurangi slot dispensasi terkonfirmasi. Jumlah siswa yang
                berbeda antar kelas karena itu tidak membuat peringkat persen menjadi timpang; kolom Jawaban tetap
                dipakai untuk mengurutkan volume. Klik kelas untuk rincian per hari.
            </p>
            <div class="lit-tableWrap">
                <table class="lit-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Kelas</th>
                            <th class="is-num">Jawaban</th>
                            <th class="is-num">Siswa</th>
                            <th class="is-num">Materi</th>
                            <th class="is-num">Slot Dispensasi</th>
                            <th class="is-num">Rasio</th>
                            <th class="is-num">Persen</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($partisipasiTertinggi as $index => $row)
                            @php
                                $days = $row['days'] ?? [];
                                $belum = $row['missing_students'] ?? [];
                                $dispensasi = $row['excluded_students'] ?? [];
                            @endphp
                            <tr>
                                <td data-label="Peringkat"><span class="lit-rank">{{ $index + 1 }}</span></td>
                                <td class="is-name" data-label="Kelas">{{ $row['class'] }}</td>
                                <td class="is-num is-strong" data-label="Jawaban">{{ $angka($row['total'] ?? 0) }}</td>
                                <td class="is-num" data-label="Siswa">{{ $angka($row['unique_students'] ?? 0) }}</td>
                                <td class="is-num" data-label="Materi">{{ $angka($row['material_count'] ?? 0) }}</td>
                                <td class="is-num" data-label="Slot Dispensasi">{{ $angka($row['excluded_total'] ?? 0) }}</td>
                                <td class="is-num" data-label="Rasio">{{ $row['ratio'] ?? '-' }}</td>
                                <td class="is-num" data-label="Persen">
                                    <span class="lit-pct is-good">{{ $persen($row['percentage'] ?? null) }}</span>
                                </td>
                            </tr>
                            <tr class="lit-drillRow">
                                <td colspan="8">
                                    <details class="lit-detail">
                                        <summary>
                                            <span>Dari mana angka {{ $angka($row['total'] ?? 0) }} pada {{ $row['class'] }}?</span>
                                            <span class="lit-detail__count">
                                                {{ $angka($row['unique_students'] ?? 0) }} siswa &times;
                                                {{ $angka($row['material_count'] ?? 0) }} materi &middot;
                                                {{ $angka($row['active_days'] ?? 0) }} hari aktif
                                            </span>
                                        </summary>
                                        <div class="lit-detail__body">
                                            <p class="lit-note">
                                                {{ $angka($row['active_total'] ?? 0) }} slot siswa aktif
                                                &minus; {{ $angka($row['excluded_total'] ?? 0) }} slot dispensasi
                                                = {{ $angka($row['respondent_base'] ?? 0) }} basis responden,
                                                terisi {{ $angka($row['total'] ?? 0) }}
                                                ({{ $persen($row['percentage'] ?? null) }}), belum
                                                {{ $angka($row['missing_total'] ?? 0) }}. Baris jawaban tercatat pada
                                                rentang ini: {{ $angka($row['response_records'] ?? 0) }}.
                                            </p>

                                            @if ($days !== [])
                                                <div class="lit-tableWrap">
                                                    <table class="lit-table">
                                                        <thead>
                                                            <tr>
                                                                <th>Tanggal</th>
                                                                <th class="is-num">Mengisi</th>
                                                                <th>Siswa yang mengisi</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            @foreach ($days as $day)
                                                                <tr>
                                                                    <td class="is-name" data-label="Tanggal">{{ $hari($day['date'] ?? null) }}</td>
                                                                    <td class="is-num is-strong" data-label="Mengisi">{{ $angka($day['total'] ?? 0) }}</td>
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
                                        </div>
                                    </details>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="lit-empty">Belum ada data.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div>
            <p class="lit-subhead">Kelas Perlu Perhatian</p>
            <p class="lit-note">
                Diurutkan dari persen terkecil, bukan dari jumlah terkecil, supaya kelas kecil tidak otomatis
                terlihat paling buruk. Siswa dispensasi sudah dikeluarkan dari penyebut.
            </p>
            <div class="lit-tableWrap">
                <table class="lit-table">
                    <thead>
                        <tr>
                            <th>Kelas</th>
                            <th class="is-num">Rasio</th>
                            <th class="is-num">Persen</th>
                            <th class="is-num">Belum Mengisi</th>
                            <th class="is-num">Slot Dispensasi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($partisipasiTerendah as $row)
                            <tr>
                                <td class="is-name" data-label="Kelas">{{ $row['class'] }}</td>
                                <td class="is-num" data-label="Rasio">{{ $row['ratio'] ?? '-' }}</td>
                                <td class="is-num" data-label="Persen">
                                    <span class="lit-pct is-bad">{{ $persen($row['percentage'] ?? null) }}</span>
                                </td>
                                <td class="is-num" data-label="Belum Mengisi">{{ $angka($row['missing_total'] ?? 0) }}</td>
                                <td class="is-num" data-label="Slot Dispensasi">{{ $angka($row['excluded_total'] ?? 0) }}</td>
                            </tr>
                            @if (($row['missing_students'] ?? []) !== [])
                                <tr class="lit-drillRow">
                                    <td colspan="5">
                                        <details class="lit-detail">
                                            <summary>
                                                <span>Siapa yang belum mengisi di {{ $row['class'] }}?</span>
                                                <span class="lit-detail__count">{{ $angka($row['missing_total'] ?? 0) }} slot</span>
                                            </summary>
                                            <div class="lit-detail__body">
                                                <ol class="lit-list">
                                                    @foreach ($row['missing_students'] as $student)
                                                        <li>{{ $student['name'] }} <span class="lit-muted">&mdash; {{ $student['material_title'] }}</span></li>
                                                    @endforeach
                                                </ol>
                                            </div>
                                        </details>
                                    </td>
                                </tr>
                            @endif
                        @empty
                            <tr><td colspan="5" class="lit-empty">Belum ada data.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div>
            <p class="lit-subhead">Akurasi Per Kelas</p>
            <p class="lit-note">
                Akurasi = poin benar / poin yang <em>sudah dinilai</em>. Jawaban yang masih menunggu penilaian tidak
                memengaruhi persentase dan ditampilkan pada kolom terpisah. Buka rincian di bawah untuk melompat
                langsung ke materi yang belum dinilai.
            </p>
            <div class="lit-tableWrap">
                <table class="lit-table">
                    <thead>
                        <tr>
                            <th>Kelas</th>
                            <th class="is-num">Benar</th>
                            <th class="is-num">Dinilai</th>
                            <th class="is-num">Belum Dinilai</th>
                            <th class="is-num">Akurasi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($akurasiPerKelas as $row)
                            @php
                                $pending = $belumDinilai[$row['class']] ?? [];
                                $pendingTotal = collect($pending)->sum('pending_answers');
                            @endphp
                            <tr>
                                <td class="is-name" data-label="Kelas">{{ $row['class'] }}</td>
                                <td class="is-num" data-label="Benar">{{ $angka($row['correct_answers'] ?? 0) }}</td>
                                <td class="is-num" data-label="Dinilai">{{ $angka($row['graded_answers'] ?? 0) }}</td>
                                <td class="is-num" data-label="Belum Dinilai">{{ $angka($pendingTotal) }}</td>
                                <td class="is-num is-strong" data-label="Akurasi">{{ $persen($row['accuracy'] ?? null) }}</td>
                            </tr>
                            @if ($pending !== [])
                                <tr class="lit-drillRow">
                                    <td colspan="5">
                                        <details class="lit-detail">
                                            <summary>
                                                <span>Materi yang belum dinilai di {{ $row['class'] }}</span>
                                                <span class="lit-detail__count">{{ $angka($pendingTotal) }} jawaban menunggu</span>
                                            </summary>
                                            <div class="lit-detail__body">
                                                <div class="lit-tableWrap">
                                                    <table class="lit-table">
                                                        <thead>
                                                            <tr>
                                                                <th>Materi</th>
                                                                <th class="is-num">Jawaban</th>
                                                                <th class="is-num">Siswa</th>
                                                                <th>Aksi</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            @foreach ($pending as $item)
                                                                @php $url = $tautanMateri((int) ($item['material_id'] ?? 0), $row['class']); @endphp
                                                                <tr>
                                                                    <td class="is-name" data-label="Materi">{{ $item['material_title'] }}</td>
                                                                    <td class="is-num is-strong" data-label="Jawaban">{{ $angka($item['pending_answers'] ?? 0) }}</td>
                                                                    <td class="is-num" data-label="Siswa">{{ $angka($item['pending_students'] ?? 0) }}</td>
                                                                    <td data-label="Aksi">
                                                                        @if ($url)
                                                                            <a class="lit-link" href="{{ $url }}">Nilai sekarang</a>
                                                                        @else
                                                                            <span class="lit-muted">Materi tidak ditemukan</span>
                                                                        @endif
                                                                    </td>
                                                                </tr>
                                                            @endforeach
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>
                                        </details>
                                    </td>
                                </tr>
                            @endif
                        @empty
                            <tr><td colspan="5" class="lit-empty">Belum ada jawaban untuk dinilai.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div>
            <p class="lit-subhead">Siswa Banyak Salah</p>
            <p class="lit-note">
                Kolom Salah adalah selisih poin (poin tersedia &minus; poin diperoleh) pada jawaban yang sudah dinilai,
                bukan jumlah soal. Jawaban yang belum dinilai tidak masuk hitungan.
            </p>
            <div class="lit-tableWrap">
                <table class="lit-table">
                    <thead>
                        <tr>
                            <th>Siswa</th>
                            <th>Kelas</th>
                            <th class="is-num">Salah</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($siswaSalah as $row)
                            <tr>
                                <td class="is-name" data-label="Siswa">{{ $row['name'] }}</td>
                                <td class="is-muted" data-label="Kelas">{{ $row['class'] }}</td>
                                <td class="is-num is-strong" data-label="Salah">{{ $angka($row['wrong_answers'] ?? 0) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="lit-empty">Belum ada jawaban salah.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-filament::section>

<x-filament::section collapsible collapsed>
    <x-slot name="heading">Siswa Terbaik Per Kelas</x-slot>
    <x-slot name="description">
        Lima siswa dengan akurasi tertinggi tiap kelas. Penyebutnya poin yang sudah dinilai pada siswa tersebut,
        sehingga siswa yang jawabannya belum selesai dinilai tidak dirugikan.
    </x-slot>

    <div class="lit-details">
        @forelse ($siswaPerKelas as $kelas => $rows)
            <details class="lit-detail">
                <summary>
                    <span>{{ $kelas }}</span>
                    <span class="lit-detail__count">{{ count($rows) }} siswa</span>
                </summary>
                <div class="lit-detail__body">
                    <div class="lit-tableWrap">
                        <table class="lit-table">
                            <thead>
                                <tr>
                                    <th>Siswa</th>
                                    <th class="is-num">Benar</th>
                                    <th class="is-num">Dinilai</th>
                                    <th class="is-num">Akurasi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($rows as $row)
                                    <tr>
                                        <td class="is-name" data-label="Siswa">{{ $row['name'] }}</td>
                                        <td class="is-num" data-label="Benar">{{ $angka($row['correct_answers'] ?? 0) }}</td>
                                        <td class="is-num" data-label="Dinilai">{{ $angka($row['graded_answers'] ?? 0) }}</td>
                                        <td class="is-num is-strong" data-label="Akurasi">{{ $persen($row['accuracy'] ?? 0) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </details>
        @empty
            <p class="lit-empty">Belum ada jawaban yang dinilai pada lingkup ini.</p>
        @endforelse
    </div>
</x-filament::section>
