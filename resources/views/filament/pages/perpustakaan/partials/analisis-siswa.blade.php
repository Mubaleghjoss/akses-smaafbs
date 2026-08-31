@php
    $angka = fn ($value): string => number_format((float) $value, 0, ',', '.');
    $persen = fn ($value): string => $value === null ? '-' : number_format((float) $value, 1, ',', '.').'%';
    $terbaikPerKelas = $analytics['student_correct_ranking_by_class'] ?? [];
    $banyakSalah = $analytics['student_wrong_ranking'] ?? [];
    $seringKosong = $analytics['frequent_missing_students'] ?? [];
@endphp

<x-filament::section>
    <x-slot name="heading">Analisis Siswa</x-slot>
    <x-slot name="description">
        Siswa terbaik per kelas, siswa dengan jawaban salah terbanyak, dan siswa yang paling sering tidak
        mengisi pada lingkup filter aktif. Dispensasi terkonfirmasi tidak dihitung sebagai tidak mengisi.
    </x-slot>

    @include('filament.pages.perpustakaan.partials.salin-bagian', [
        'teks' => $salinTeks ?? '',
        'catatan' => 'Menyalin seluruh analisis siswa sesuai rentang, kategori, materi, dan kelas yang aktif.',
    ])

    <div class="lit-duo">
        <div>
            <p class="lit-subhead">Siswa Terbaik Per Kelas</p>
            <p class="lit-note">Diurutkan dari poin benar, akurasi, lalu jumlah poin yang sudah dinilai.</p>
            <div class="lit-details">
                @forelse ($terbaikPerKelas as $kelas => $rows)
                    <details class="lit-detail" open>
                        <summary><span>{{ $kelas }}</span><span class="lit-detail__count">{{ count($rows) }} siswa</span></summary>
                        <div class="lit-detail__body">
                            <div class="lit-tableWrap">
                                <table class="lit-table">
                                    <thead><tr><th>Siswa</th><th class="is-num">Benar</th><th class="is-num">Dinilai</th><th class="is-num">Akurasi</th></tr></thead>
                                    <tbody>
                                        @foreach ($rows as $row)
                                            <tr>
                                                <td class="is-name" data-label="Siswa">{{ $row['name'] }}</td>
                                                <td class="is-num" data-label="Benar">{{ $angka($row['correct_answers'] ?? 0) }}</td>
                                                <td class="is-num" data-label="Dinilai">{{ $angka($row['graded_answers'] ?? 0) }}</td>
                                                <td class="is-num is-strong" data-label="Akurasi">{{ $persen($row['accuracy'] ?? null) }}</td>
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
        </div>

        <div>
            <p class="lit-subhead">Siswa dengan Jawaban Salah Terbanyak</p>
            <p class="lit-note">Salah adalah selisih poin tersedia dan poin diperoleh; jawaban belum dinilai tidak dihitung.</p>
            <div class="lit-tableWrap">
                <table class="lit-table">
                    <thead><tr><th>Siswa</th><th>Kelas</th><th class="is-num">Salah</th></tr></thead>
                    <tbody>
                        @forelse ($banyakSalah as $row)
                            <tr>
                                <td class="is-name" data-label="Siswa">{{ $row['name'] }}</td>
                                <td data-label="Kelas">{{ $row['class'] }}</td>
                                <td class="is-num is-strong" data-label="Salah">{{ $angka($row['wrong_answers'] ?? 0) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="lit-empty">Belum ada jawaban salah.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @if ($seringKosong !== [])
            <div>
                <p class="lit-subhead">Siswa yang Sering Tidak Mengisi</p>
                <p class="lit-note">Satu hitungan adalah satu materi wajib yang tidak diisi pada filter aktif.</p>
                <div class="lit-tableWrap">
                    <table class="lit-table">
                        <thead><tr><th>Siswa</th><th>Kelas</th><th class="is-num">Tidak Diisi</th><th>Materi</th></tr></thead>
                        <tbody>
                            @foreach ($seringKosong as $row)
                                <tr>
                                    <td class="is-name" data-label="Siswa">{{ $row['name'] }}</td>
                                    <td data-label="Kelas">{{ $row['class'] }}</td>
                                    <td class="is-num is-strong" data-label="Tidak Diisi">{{ $angka($row['missing_total'] ?? 0) }} slot</td>
                                    <td data-label="Materi">{{ implode(', ', $row['materials'] ?? []) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </div>
</x-filament::section>
