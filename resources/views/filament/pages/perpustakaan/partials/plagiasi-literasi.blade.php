@php
    $angka = fn ($value): string => number_format((float) $value, 0, ',', '.');
    $kelasPlagiasi = $analytics['plagiarism_class_ranking'] ?? [];
    $siswaPlagiasi = $analytics['plagiarism_student_ranking'] ?? [];
    $summary = $analytics['grading_summary'] ?? [];
@endphp

<x-filament::section collapsible collapsed>
    <x-slot name="heading">Indikasi Kemiripan Jawaban</x-slot>
    <x-slot name="description">
        Total plagiasi terkonfirmasi pada lingkup ini: {{ $angka($summary['confirmed_plagiarism'] ?? 0) }}.
    </x-slot>

    @include('filament.pages.perpustakaan.partials.salin-bagian', [
        'teks' => $salinTeks ?? '',
        'catatan' => 'Menyalin indikasi kemiripan per kelas dan per siswa.',
    ])

    <div class="lit-split">
        <div>
            <p class="lit-subhead">Per Kelas</p>
            <div class="lit-tableWrap">
                <table class="lit-table">
                    <thead>
                        <tr>
                            <th>Kelas</th>
                            <th class="is-num">Kemiripan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($kelasPlagiasi as $row)
                            <tr>
                                <td class="is-name" data-label="Kelas">{{ $row['class'] }}</td>
                                <td class="is-num is-strong" data-label="Kemiripan">{{ $angka($row['total'] ?? 0) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="2" class="lit-empty">Tidak ada indikasi kemiripan.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div>
            <p class="lit-subhead">Per Siswa</p>
            <div class="lit-tableWrap">
                <table class="lit-table">
                    <thead>
                        <tr>
                            <th>Siswa</th>
                            <th>Kelas</th>
                            <th class="is-num">Kemiripan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($siswaPlagiasi as $row)
                            <tr>
                                <td class="is-name" data-label="Siswa">{{ $row['name'] }}</td>
                                <td class="is-muted" data-label="Kelas">{{ $row['class'] }}</td>
                                <td class="is-num is-strong" data-label="Kemiripan">{{ $angka($row['total'] ?? 0) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="lit-empty">Tidak ada indikasi kemiripan.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-filament::section>
