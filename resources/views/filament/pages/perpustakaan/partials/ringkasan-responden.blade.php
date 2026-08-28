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
            'tone' => 'default',
        ],
        [
            'label' => 'Dikeluarkan',
            'value' => $angka($base['excluded_total'] ?? 0),
            'hint' => 'Izin, sakit, dan tes MT — tidak dihitung mengisi',
            'tone' => 'warning',
        ],
        [
            'label' => 'Basis Responden',
            'value' => $angka($base['respondent_base'] ?? 0),
            'hint' => 'Penyebut persentase partisipasi',
            'tone' => 'default',
        ],
        [
            'label' => 'Sudah Mengisi',
            'value' => $angka($base['completed_total'] ?? 0),
            'hint' => ($base['ratio'] ?? '0/0').' = '.$persen($base['participation_percentage'] ?? null),
            'tone' => 'success',
        ],
        [
            'label' => 'Belum Mengisi',
            'value' => $angka($base['missing_total'] ?? 0),
            'hint' => 'Masuk basis responden tetapi belum ada jawaban',
            'tone' => 'danger',
        ],
        [
            'label' => 'Jawaban di Sampah',
            'value' => $angka($base['trashed_total'] ?? 0),
            'hint' => 'Pernah mengisi lalu dihapus — tetap dihitung belum mengisi',
            'tone' => 'default',
        ],
    ];

    $tones = [
        'default' => 'border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900',
        'success' => 'border-success-200 bg-success-50 dark:border-success-800 dark:bg-success-950/40',
        'warning' => 'border-warning-200 bg-warning-50 dark:border-warning-800 dark:bg-warning-950/40',
        'danger' => 'border-danger-200 bg-danger-50 dark:border-danger-800 dark:bg-danger-950/40',
    ];
@endphp

<x-filament::section>
    <x-slot name="heading">Ringkasan Responden</x-slot>
    <x-slot name="description">
        Siswa berstatus izin, sakit, atau tes MT dikeluarkan dari basis responden — tidak dihitung sebagai
        pengisi maupun sebagai yang belum mengisi.
    </x-slot>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        @foreach ($kartu as $item)
            <div class="rounded-xl border p-4 shadow-sm {{ $tones[$item['tone']] }}">
                <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ $item['label'] }}</p>
                <p class="mt-1 text-3xl font-bold text-gray-900 dark:text-gray-50">{{ $item['value'] }}</p>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $item['hint'] }}</p>
            </div>
        @endforeach
    </div>

    <div class="mt-6 grid gap-4 sm:grid-cols-3">
        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
            <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Jawaban Dinilai</p>
            <p class="mt-1 text-2xl font-bold text-gray-900 dark:text-gray-50">
                {{ $angka($summary['graded_answers'] ?? 0) }}/{{ $angka($summary['total_answers'] ?? 0) }}
            </p>
        </div>
        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
            <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Jawaban Benar</p>
            <p class="mt-1 text-2xl font-bold text-gray-900 dark:text-gray-50">{{ $angka($summary['correct_answers'] ?? 0) }}</p>
        </div>
        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
            <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Akurasi Dinilai</p>
            <p class="mt-1 text-2xl font-bold text-gray-900 dark:text-gray-50">{{ $persen($summary['accuracy'] ?? 0) }}</p>
        </div>
    </div>
</x-filament::section>
