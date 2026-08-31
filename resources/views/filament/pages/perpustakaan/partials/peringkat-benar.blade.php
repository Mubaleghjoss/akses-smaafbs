@php
    $angka = fn ($value): string => number_format((float) $value, 0, ',', '.');
    $persen = fn ($value): string => $value === null ? '-' : number_format((float) $value, 1, ',', '.').'%';
    $peringkat = $analytics['class_correct_ranking_full'] ?? [];
    $adaTertunda = collect($peringkat)->sum('pending_answers') > 0;

    // Tautan penilaian per materi, sudah terfilter ke kelas dan jawaban tertunda.
    $tautanMateri = fn (int $materialId, ?string $kelas = null): ?string => \App\Filament\Resources\PerpustakaanLiterasiMaterialResource::gradingUrl($materialId, $kelas);
@endphp

<x-filament::section>
    <x-slot name="heading">Peringkat Kelas: Jawaban Benar Terbanyak</x-slot>
    <x-slot name="description">
        Seluruh kelas pada lingkup aktif diurutkan dari jumlah poin benar terbanyak. Berbeda dengan tabel
        "Akurasi Per Kelas" yang mengurutkan menurut persentase, peringkat ini memakai volume jawaban benar,
        dan menyertakan kelas yang jawabannya masih menunggu penilaian.
    </x-slot>

    @include('filament.pages.perpustakaan.partials.salin-bagian', [
        'teks' => $salinTeks ?? '',
        'catatan' => 'Menyalin peringkat kelas berdasarkan jawaban benar beserta catatan penilaian tertunda.',
    ])

    @if ($adaTertunda)
        <p class="lit-warn">
            Peringkat ini <strong>belum final</strong>. Masih ada jawaban yang belum dinilai, sehingga urutan
            dapat berubah setelah penilaiannya diselesaikan. Kolom <strong>Potensi</strong> menunjukkan jumlah
            benar maksimum bila seluruh jawaban tertunda kelas tersebut ternyata benar.
        </p>
    @endif

    <div class="lit-tableWrap">
        <table class="lit-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Kelas</th>
                    <th class="is-num">Benar</th>
                    <th class="is-num">Dinilai</th>
                    <th class="is-num">Akurasi</th>
                    <th class="is-num">Belum Dinilai</th>
                    <th class="is-num">Potensi</th>
                    <th class="is-num">Partisipasi</th>
                    <th>Catatan</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($peringkat as $row)
                    @php($pendingMateri = $row['pending_materials'] ?? [])
                    <tr>
                        <td data-label="Peringkat"><span class="lit-rank">{{ $row['rank'] ?? '-' }}</span></td>
                        <td class="is-name" data-label="Kelas">{{ $row['class'] }}</td>
                        <td class="is-num is-strong" data-label="Benar">{{ $angka($row['correct_answers'] ?? 0) }}</td>
                        <td class="is-num" data-label="Dinilai">{{ $angka($row['graded_answers'] ?? 0) }}</td>
                        <td class="is-num" data-label="Akurasi">{{ $persen($row['accuracy'] ?? null) }}</td>
                        <td class="is-num" data-label="Belum Dinilai">{{ $angka($row['pending_answers'] ?? 0) }}</td>
                        <td class="is-num" data-label="Potensi">
                            {{ $angka($row['potential_correct'] ?? 0) }}
                            @if (($row['pending_answers'] ?? 0) > 0)
                                <span class="lit-timeline__span">bila semua tertunda benar</span>
                            @endif
                        </td>
                        <td class="is-num" data-label="Partisipasi">
                            {{ $row['participation_ratio'] ?? '0/0' }}
                            <span class="lit-timeline__span">{{ $persen($row['participation_percentage'] ?? null) }}</span>
                        </td>
                        <td data-label="Catatan">
                            @if (($row['rank_provisional'] ?? false) === true)
                                <span class="lit-flag">Masih bisa berubah</span>
                            @else
                                <span class="lit-muted">Penilaian lengkap</span>
                            @endif
                        </td>
                    </tr>

                    @if ($pendingMateri !== [])
                        <tr class="lit-drillRow">
                            <td colspan="9">
                                <details class="lit-detail">
                                    <summary>
                                        <span>Materi {{ $row['class'] }} yang menunggu penilaian</span>
                                        <span class="lit-detail__count">
                                            {{ $angka($row['pending_answers'] ?? 0) }} jawaban &middot;
                                            {{ $angka($row['pending_students'] ?? 0) }} siswa
                                        </span>
                                    </summary>
                                    <div class="lit-detail__body">
                                        <p class="lit-note">
                                            Selesaikan penilaian pada materi di bawah agar peringkat kelas ini final.
                                            Tautan sudah membawa filter kelas <strong>{{ $row['class'] }}</strong> dan
                                            status <strong>belum lengkap</strong>, jadi daftar yang terbuka langsung
                                            berisi jawaban yang perlu dinilai.
                                        </p>
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
                                                    @foreach ($pendingMateri as $item)
                                                        @php($url = $tautanMateri((int) ($item['material_id'] ?? 0), $row['class']))
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
                    <tr><td colspan="9" class="lit-empty">Belum ada jawaban pada lingkup ini.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-filament::section>
