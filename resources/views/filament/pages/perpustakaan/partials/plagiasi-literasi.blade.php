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

    <div class="grid gap-6 xl:grid-cols-2">
        <div class="overflow-x-auto">
            <p class="mb-2 text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Per Kelas</p>
            <table class="w-full text-sm">
                <thead class="border-b border-gray-200 text-left text-xs uppercase tracking-wide text-gray-500 dark:border-gray-700 dark:text-gray-400">
                    <tr>
                        <th class="py-2 pr-3">Kelas</th>
                        <th class="py-2 text-right">Kemiripan</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse ($kelasPlagiasi as $row)
                        <tr>
                            <td class="py-2 pr-3 font-medium text-gray-900 dark:text-gray-100">{{ $row['class'] }}</td>
                            <td class="py-2 text-right font-semibold">{{ $angka($row['total'] ?? 0) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="2" class="py-6 text-center text-gray-500 dark:text-gray-400">Tidak ada indikasi kemiripan.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="overflow-x-auto">
            <p class="mb-2 text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Per Siswa</p>
            <table class="w-full text-sm">
                <thead class="border-b border-gray-200 text-left text-xs uppercase tracking-wide text-gray-500 dark:border-gray-700 dark:text-gray-400">
                    <tr>
                        <th class="py-2 pr-3">Siswa</th>
                        <th class="py-2 pr-3">Kelas</th>
                        <th class="py-2 text-right">Kemiripan</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse ($siswaPlagiasi as $row)
                        <tr>
                            <td class="py-2 pr-3 text-gray-900 dark:text-gray-100">{{ $row['name'] }}</td>
                            <td class="py-2 pr-3 text-gray-500 dark:text-gray-400">{{ $row['class'] }}</td>
                            <td class="py-2 text-right font-semibold">{{ $angka($row['total'] ?? 0) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="py-6 text-center text-gray-500 dark:text-gray-400">Tidak ada indikasi kemiripan.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-filament::section>
