@php
    $angka = fn ($value): string => number_format((float) $value, 0, ',', '.');
    $persen = fn ($value): string => $value === null ? '-' : number_format((float) $value, 1, ',', '.').'%';
    $partisipasiTertinggi = $analytics['class_response_ranking'] ?? [];
    $partisipasiTerendah = $analytics['least_class_response_ranking'] ?? [];
    $kelasBenar = $analytics['class_correct_ranking'] ?? [];
    $siswaPerKelas = $analytics['student_correct_ranking_by_class'] ?? [];
    $siswaSalah = $analytics['student_wrong_ranking'] ?? [];
@endphp

<div class="grid gap-6 xl:grid-cols-2">
    <x-filament::section>
        <x-slot name="heading">Kelas Partisipasi Tertinggi</x-slot>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="border-b border-gray-200 text-left text-xs uppercase tracking-wide text-gray-500 dark:border-gray-700 dark:text-gray-400">
                    <tr>
                        <th class="py-2 pr-3">#</th>
                        <th class="py-2 pr-3">Kelas</th>
                        <th class="py-2 pr-3 text-right">Rasio</th>
                        <th class="py-2 text-right">Persen</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse ($partisipasiTertinggi as $index => $row)
                        <tr>
                            <td class="py-2 pr-3 text-gray-500">{{ $index + 1 }}</td>
                            <td class="py-2 pr-3 font-medium text-gray-900 dark:text-gray-100">{{ $row['class'] }}</td>
                            <td class="py-2 pr-3 text-right">{{ $row['ratio'] ?? '-' }}</td>
                            <td class="py-2 text-right font-semibold">{{ $persen($row['percentage'] ?? null) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="py-6 text-center text-gray-500 dark:text-gray-400">Belum ada data.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-filament::section>

    <x-filament::section>
        <x-slot name="heading">Kelas Perlu Perhatian</x-slot>
        <x-slot name="description">Partisipasi terendah pada lingkup ini.</x-slot>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="border-b border-gray-200 text-left text-xs uppercase tracking-wide text-gray-500 dark:border-gray-700 dark:text-gray-400">
                    <tr>
                        <th class="py-2 pr-3">Kelas</th>
                        <th class="py-2 pr-3 text-right">Rasio</th>
                        <th class="py-2 pr-3 text-right">Persen</th>
                        <th class="py-2 text-right">Belum Mengisi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse ($partisipasiTerendah as $row)
                        <tr>
                            <td class="py-2 pr-3 font-medium text-gray-900 dark:text-gray-100">{{ $row['class'] }}</td>
                            <td class="py-2 pr-3 text-right">{{ $row['ratio'] ?? '-' }}</td>
                            <td class="py-2 pr-3 text-right font-semibold text-danger-600 dark:text-danger-400">{{ $persen($row['percentage'] ?? null) }}</td>
                            <td class="py-2 text-right">{{ $angka($row['missing_total'] ?? 0) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="py-6 text-center text-gray-500 dark:text-gray-400">Belum ada data.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-filament::section>

    <x-filament::section>
        <x-slot name="heading">Akurasi Per Kelas</x-slot>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="border-b border-gray-200 text-left text-xs uppercase tracking-wide text-gray-500 dark:border-gray-700 dark:text-gray-400">
                    <tr>
                        <th class="py-2 pr-3">Kelas</th>
                        <th class="py-2 pr-3 text-right">Benar</th>
                        <th class="py-2 pr-3 text-right">Dinilai</th>
                        <th class="py-2 text-right">Akurasi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse ($kelasBenar as $row)
                        <tr>
                            <td class="py-2 pr-3 font-medium text-gray-900 dark:text-gray-100">{{ $row['class'] }}</td>
                            <td class="py-2 pr-3 text-right">{{ $angka($row['correct_answers'] ?? 0) }}</td>
                            <td class="py-2 pr-3 text-right">{{ $angka($row['graded_answers'] ?? 0) }}</td>
                            <td class="py-2 text-right font-semibold">{{ $persen($row['accuracy'] ?? 0) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="py-6 text-center text-gray-500 dark:text-gray-400">Belum ada jawaban dinilai.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-filament::section>

    <x-filament::section>
        <x-slot name="heading">Siswa Banyak Salah</x-slot>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="border-b border-gray-200 text-left text-xs uppercase tracking-wide text-gray-500 dark:border-gray-700 dark:text-gray-400">
                    <tr>
                        <th class="py-2 pr-3">Siswa</th>
                        <th class="py-2 pr-3">Kelas</th>
                        <th class="py-2 text-right">Salah</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse ($siswaSalah as $row)
                        <tr>
                            <td class="py-2 pr-3 text-gray-900 dark:text-gray-100">{{ $row['name'] }}</td>
                            <td class="py-2 pr-3 text-gray-500 dark:text-gray-400">{{ $row['class'] }}</td>
                            <td class="py-2 text-right font-semibold">{{ $angka($row['wrong_answers'] ?? 0) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="py-6 text-center text-gray-500 dark:text-gray-400">Belum ada jawaban salah.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-filament::section>
</div>

<x-filament::section collapsible collapsed>
    <x-slot name="heading">Siswa Terbaik Per Kelas</x-slot>

    <div class="space-y-3">
        @forelse ($siswaPerKelas as $kelas => $rows)
            <details class="rounded-xl border border-gray-200 bg-white p-3 dark:border-gray-700 dark:bg-gray-900">
                <summary class="cursor-pointer text-sm font-medium text-gray-800 dark:text-gray-100">{{ $kelas }}</summary>
                <div class="mt-3 overflow-x-auto">
                    <table class="w-full text-xs">
                        <thead class="text-left uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            <tr>
                                <th class="py-1 pr-3">Siswa</th>
                                <th class="py-1 pr-3 text-right">Benar</th>
                                <th class="py-1 pr-3 text-right">Dinilai</th>
                                <th class="py-1 text-right">Akurasi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @foreach ($rows as $row)
                                <tr>
                                    <td class="py-1 pr-3 text-gray-900 dark:text-gray-100">{{ $row['name'] }}</td>
                                    <td class="py-1 pr-3 text-right">{{ $angka($row['correct_answers'] ?? 0) }}</td>
                                    <td class="py-1 pr-3 text-right">{{ $angka($row['graded_answers'] ?? 0) }}</td>
                                    <td class="py-1 text-right">{{ $persen($row['accuracy'] ?? 0) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </details>
        @empty
            <p class="text-sm text-gray-500 dark:text-gray-400">Belum ada jawaban yang dinilai pada lingkup ini.</p>
        @endforelse
    </div>
</x-filament::section>
