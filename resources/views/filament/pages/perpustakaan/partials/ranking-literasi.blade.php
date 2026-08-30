@php
    $angka = fn ($value): string => number_format((float) $value, 0, ',', '.');
    $persen = fn ($value): string => $value === null ? '-' : number_format((float) $value, 1, ',', '.').'%';
    $partisipasiTertinggi = $analytics['class_response_ranking'] ?? [];
    $partisipasiTerendah = $analytics['least_class_response_ranking'] ?? [];
    $kelasBenar = $analytics['class_correct_ranking'] ?? [];
    $siswaPerKelas = $analytics['student_correct_ranking_by_class'] ?? [];
    $siswaSalah = $analytics['student_wrong_ranking'] ?? [];
@endphp

<x-filament::section>
    <x-slot name="heading">Ranking Kelas & Siswa</x-slot>
    <x-slot name="description">
        Empat sudut pandang pada lingkup yang sama: partisipasi tertinggi, kelas perlu perhatian, akurasi
        jawaban, dan siswa dengan jawaban salah terbanyak.
    </x-slot>

    @include('filament.pages.perpustakaan.partials.salin-bagian', [
        'teks' => $salinTeks ?? '',
        'catatan' => 'Menyalin seluruh ranking termasuk siswa terbaik per kelas.',
    ])

    <div class="lit-duo">
        <div>
            <p class="lit-subhead">7 Kelas Jawaban Terbanyak</p>
            <div class="lit-tableWrap">
                <table class="lit-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Kelas</th>
                            <th class="is-num">Jawaban</th>
                            <th class="is-num">Rasio</th>
                            <th class="is-num">Persen</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($partisipasiTertinggi as $index => $row)
                            <tr>
                                <td data-label="Peringkat"><span class="lit-rank">{{ $index + 1 }}</span></td>
                                <td class="is-name" data-label="Kelas">{{ $row['class'] }}</td>
                                <td class="is-num is-strong" data-label="Jawaban">{{ $angka($row['total'] ?? 0) }}</td>
                                <td class="is-num" data-label="Rasio">{{ $row['ratio'] ?? '-' }}</td>
                                <td class="is-num" data-label="Persen">
                                    <span class="lit-pct is-good">{{ $persen($row['percentage'] ?? null) }}</span>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="lit-empty">Belum ada data.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div>
            <p class="lit-subhead">Kelas Perlu Perhatian</p>
            <div class="lit-tableWrap">
                <table class="lit-table">
                    <thead>
                        <tr>
                            <th>Kelas</th>
                            <th class="is-num">Rasio</th>
                            <th class="is-num">Persen</th>
                            <th class="is-num">Belum Mengisi</th>
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
                            </tr>
                        @empty
                            <tr><td colspan="4" class="lit-empty">Belum ada data.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div>
            <p class="lit-subhead">Akurasi Per Kelas</p>
            <div class="lit-tableWrap">
                <table class="lit-table">
                    <thead>
                        <tr>
                            <th>Kelas</th>
                            <th class="is-num">Benar</th>
                            <th class="is-num">Dinilai</th>
                            <th class="is-num">Akurasi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($kelasBenar as $row)
                            <tr>
                                <td class="is-name" data-label="Kelas">{{ $row['class'] }}</td>
                                <td class="is-num" data-label="Benar">{{ $angka($row['correct_answers'] ?? 0) }}</td>
                                <td class="is-num" data-label="Dinilai">{{ $angka($row['graded_answers'] ?? 0) }}</td>
                                <td class="is-num is-strong" data-label="Akurasi">{{ $persen($row['accuracy'] ?? 0) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="lit-empty">Belum ada jawaban dinilai.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div>
            <p class="lit-subhead">Siswa Banyak Salah</p>
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
