@php
    $kelasRows = $base['classes'] ?? [];
    $angka = fn ($value): string => number_format((float) $value, 0, ',', '.');
    $persen = fn ($value): string => $value === null ? '-' : number_format((float) $value, 1, ',', '.').'%';
@endphp

<x-filament::section>
    <x-slot name="heading">Partisipasi Per Kelas</x-slot>
    <x-slot name="description">
        Persentase dihitung dari jumlah yang mengisi dibagi basis responden kelas tersebut, sehingga tidak pernah
        melewati 100%. Kolom "Dikeluarkan" berisi izin, sakit, dan tes MT.
    </x-slot>

    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="border-b border-gray-200 text-left text-xs uppercase tracking-wide text-gray-500 dark:border-gray-700 dark:text-gray-400">
                <tr>
                    <th class="py-2 pr-3">Kelas</th>
                    <th class="py-2 pr-3 text-right">Mengisi</th>
                    <th class="py-2 pr-3 text-right">Basis</th>
                    <th class="py-2 pr-3 text-right">Partisipasi</th>
                    <th class="py-2 pr-3 text-right">Belum Mengisi</th>
                    <th class="py-2 pr-3 text-right">Dikeluarkan</th>
                    <th class="py-2 text-right">Di Sampah</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                @forelse ($kelasRows as $row)
                    @php
                        $pct = $row['participation_percentage'] ?? null;
                        $warna = $pct === null
                            ? 'text-gray-500 dark:text-gray-400'
                            : ($pct >= 85 ? 'text-success-600 dark:text-success-400' : ($pct >= 70 ? 'text-warning-600 dark:text-warning-400' : 'text-danger-600 dark:text-danger-400'));
                    @endphp
                    <tr>
                        <td class="py-2 pr-3 font-medium text-gray-900 dark:text-gray-100">{{ $row['class'] }}</td>
                        <td class="py-2 pr-3 text-right">{{ $angka($row['completed_total'] ?? 0) }}</td>
                        <td class="py-2 pr-3 text-right">{{ $angka($row['respondent_base'] ?? 0) }}</td>
                        <td class="py-2 pr-3 text-right font-semibold {{ $warna }}">{{ $persen($pct) }}</td>
                        <td class="py-2 pr-3 text-right">{{ $angka($row['missing_total'] ?? 0) }}</td>
                        <td class="py-2 pr-3 text-right">{{ $angka($row['excluded_total'] ?? 0) }}</td>
                        <td class="py-2 text-right">{{ $angka($row['trashed_total'] ?? 0) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="py-6 text-center text-gray-500 dark:text-gray-400">
                            Tidak ada data pada rentang dan filter ini.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if (! empty($kelasRows))
        <div class="mt-6 space-y-3">
            @foreach ($kelasRows as $row)
                @if (($row['missing_total'] ?? 0) > 0)
                    <details class="rounded-xl border border-gray-200 bg-white p-3 dark:border-gray-700 dark:bg-gray-900">
                        <summary class="cursor-pointer text-sm font-medium text-gray-800 dark:text-gray-100">
                            {{ $row['class'] }} — {{ $angka($row['missing_total']) }} belum mengisi
                        </summary>
                        <ul class="mt-3 grid gap-1 text-xs text-gray-600 sm:grid-cols-2 dark:text-gray-300">
                            @foreach (($row['missing_students'] ?? []) as $siswa)
                                <li class="rounded-lg bg-gray-50 px-2 py-1 dark:bg-white/5">
                                    {{ $siswa['name'] }}
                                    <span class="opacity-70">· {{ $siswa['material_title'] }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </details>
                @endif
            @endforeach
        </div>
    @endif
</x-filament::section>
