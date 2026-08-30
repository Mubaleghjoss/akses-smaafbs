@php
    $kelasRows = $base['classes'] ?? [];
    $angka = fn ($value): string => number_format((float) $value, 0, ',', '.');
    $persen = fn ($value): string => $value === null ? '-' : number_format((float) $value, 1, ',', '.').'%';

    // Ambang warna partisipasi: >=85% baik, >=70% perlu dipantau, sisanya rendah.
    $kelasWarna = function ($pct): string {
        if ($pct === null) {
            return 'is-none';
        }

        return $pct >= 85 ? 'is-good' : ($pct >= 70 ? 'is-warn' : 'is-bad');
    };
@endphp

<x-filament::section>
    <x-slot name="heading">Partisipasi Per Kelas</x-slot>
    <x-slot name="description">
        Persentase dihitung dari jumlah yang mengisi dibagi basis responden kelas tersebut, sehingga tidak pernah
        melewati 100%. Kolom "Dikeluarkan" berisi izin, sakit, dan tes MT.
    </x-slot>

    @include('filament.pages.perpustakaan.partials.salin-bagian', [
        'teks' => $salinTeks ?? '',
        'catatan' => 'Menyalin tabel partisipasi per kelas sesuai filter aktif.',
    ])

    <div class="lit-tableWrap">
        <table class="lit-table">
            <thead>
                <tr>
                    <th>Kelas</th>
                    <th class="is-num">Mengisi</th>
                    <th class="is-num">Basis</th>
                    <th class="is-num">Partisipasi</th>
                    <th class="is-num">Belum Mengisi</th>
                    <th class="is-num">Dikeluarkan</th>
                    <th class="is-num">Di Sampah</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($kelasRows as $row)
                    @php($pct = $row['participation_percentage'] ?? null)
                    <tr>
                        <td class="is-name" data-label="Kelas">{{ $row['class'] }}</td>
                        <td class="is-num" data-label="Mengisi">{{ $angka($row['completed_total'] ?? 0) }}</td>
                        <td class="is-num" data-label="Basis">{{ $angka($row['respondent_base'] ?? 0) }}</td>
                        <td class="is-num" data-label="Partisipasi">
                            <span class="lit-pct {{ $kelasWarna($pct) }}">{{ $persen($pct) }}</span>
                        </td>
                        <td class="is-num" data-label="Belum Mengisi">{{ $angka($row['missing_total'] ?? 0) }}</td>
                        <td class="is-num" data-label="Dikeluarkan">{{ $angka($row['excluded_total'] ?? 0) }}</td>
                        <td class="is-num" data-label="Di Sampah">{{ $angka($row['trashed_total'] ?? 0) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="lit-empty">Tidak ada data pada rentang dan filter ini.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @php($kelasBelum = collect($kelasRows)->filter(fn ($row) => ($row['missing_total'] ?? 0) > 0))

    @if ($kelasBelum->isNotEmpty())
        <div class="lit-details">
            <p class="lit-subhead">Daftar Siswa Belum Mengisi</p>

            @include('filament.pages.perpustakaan.partials.salin-bagian', [
                'teks' => $salinBelumTeks ?? '',
                'catatan' => 'Menyalin daftar nama siswa yang belum mengisi, dikelompokkan per kelas.',
            ])

            @foreach ($kelasBelum as $row)
                <details class="lit-detail">
                    <summary>
                        <span>{{ $row['class'] }}</span>
                        <span class="lit-detail__count">{{ $angka($row['missing_total']) }} belum mengisi</span>
                    </summary>
                    <div class="lit-detail__body">
                        <ul class="lit-chips">
                            @foreach (($row['missing_students'] ?? []) as $siswa)
                                <li class="lit-chip">
                                    {{ $siswa['name'] }}
                                    <span>· {{ $siswa['material_title'] }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </details>
            @endforeach
        </div>
    @endif
</x-filament::section>
