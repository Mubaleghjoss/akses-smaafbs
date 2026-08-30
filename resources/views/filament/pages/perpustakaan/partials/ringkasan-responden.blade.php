@php
    $summary = $analytics['grading_summary'] ?? [];
    $angka = fn ($value): string => number_format((float) $value, 0, ',', '.');
    $persen = fn ($value): string => $value === null ? '-' : number_format((float) $value, 1, ',', '.').'%';

    // Kartu disusun agar hubungan angkanya terbaca berurutan:
    // siswa aktif -> dikeluarkan -> basis -> mengisi -> belum mengisi.
    $kartu = [
        [
            'label' => 'Slot Siswa Aktif',
            'value' => $angka($base['active_total'] ?? 0),
            'hint' => 'Siswa aktif x materi pada lingkup ini',
            'tone' => '',
        ],
        [
            'label' => 'Dikeluarkan',
            'value' => $angka($base['excluded_total'] ?? 0),
            'hint' => 'Izin, sakit, dan tes MT — tidak dihitung mengisi',
            'tone' => 'is-warning',
        ],
        [
            'label' => 'Basis Responden',
            'value' => $angka($base['respondent_base'] ?? 0),
            'hint' => 'Penyebut persentase partisipasi',
            'tone' => '',
        ],
        [
            'label' => 'Sudah Mengisi',
            'value' => $angka($base['completed_total'] ?? 0),
            'hint' => ($base['ratio'] ?? '0/0').' = '.$persen($base['participation_percentage'] ?? null),
            'tone' => 'is-success',
        ],
        [
            'label' => 'Belum Mengisi',
            'value' => $angka($base['missing_total'] ?? 0),
            'hint' => 'Masuk basis responden tetapi belum ada jawaban',
            'tone' => 'is-danger',
        ],
        [
            'label' => 'Jawaban di Sampah',
            'value' => $angka($base['trashed_total'] ?? 0),
            'hint' => 'Pernah mengisi lalu dihapus — tetap dihitung belum mengisi',
            'tone' => '',
        ],
    ];

    $penilaian = [
        ['label' => 'Jawaban Dinilai', 'value' => $angka($summary['graded_answers'] ?? 0).'/'.$angka($summary['total_answers'] ?? 0)],
        ['label' => 'Jawaban Benar', 'value' => $angka($summary['correct_answers'] ?? 0)],
        ['label' => 'Akurasi Dinilai', 'value' => $persen($summary['accuracy'] ?? 0)],
    ];
@endphp

<x-filament::section>
    <x-slot name="heading">Ringkasan Responden</x-slot>
    <x-slot name="description">
        Siswa berstatus izin, sakit, atau tes MT dikeluarkan dari basis responden — tidak dihitung sebagai
        pengisi maupun sebagai yang belum mengisi.
    </x-slot>

    @include('filament.pages.perpustakaan.partials.salin-bagian', [
        'teks' => $salinTeks ?? '',
        'catatan' => 'Menyalin ringkasan responden dan penilaian sesuai filter aktif.',
    ])

    <div class="lit-cards">
        @foreach ($kartu as $item)
            <div class="lit-card {{ $item['tone'] }}">
                <p class="lit-card__label">{{ $item['label'] }}</p>
                <p class="lit-card__value">{{ $item['value'] }}</p>
                <p class="lit-card__hint">{{ $item['hint'] }}</p>
            </div>
        @endforeach
    </div>

    <div class="lit-cards lit-cards--tight">
        @foreach ($penilaian as $item)
            <div class="lit-card">
                <p class="lit-card__label">{{ $item['label'] }}</p>
                <p class="lit-card__value">{{ $item['value'] }}</p>
            </div>
        @endforeach
    </div>
</x-filament::section>
