@php
    $alasanTotal = $base['excluded_by_reason'] ?? [];
    $kelasRows = $base['classes'] ?? [];
    $angka = fn ($value): string => number_format((float) $value, 0, ',', '.');
    $adaDispensasi = ($base['excluded_total'] ?? 0) > 0;
@endphp

<x-filament::section>
    <x-slot name="heading">Dispensasi Pada Lingkup Ini</x-slot>
    <x-slot name="description">
        Rincian siswa yang dikeluarkan dari basis responden. Untuk menetapkan atau membatalkan status,
        gunakan halaman Kelola Dispensasi.
    </x-slot>

    <x-slot name="headerEnd">
        <x-filament::button
            size="sm"
            color="gray"
            tag="a"
            href="{{ \App\Filament\Pages\Perpustakaan\KelolaDispensasiPage::getUrl() }}"
        >
            Kelola Dispensasi
        </x-filament::button>
    </x-slot>

    @if ($adaDispensasi)
        <div class="grid gap-4 sm:grid-cols-3">
            @foreach ($alasanLabels as $kode => $label)
                <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                    <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ $label }}</p>
                    <p class="mt-1 text-2xl font-bold text-gray-900 dark:text-gray-50">{{ $angka($alasanTotal[$kode] ?? 0) }}</p>
                </div>
            @endforeach
        </div>

        <div class="mt-6 space-y-3">
            @foreach ($kelasRows as $row)
                @if (($row['excluded_total'] ?? 0) > 0)
                    <details class="rounded-xl border border-gray-200 bg-white p-3 dark:border-gray-700 dark:bg-gray-900">
                        <summary class="cursor-pointer text-sm font-medium text-gray-800 dark:text-gray-100">
                            {{ $row['class'] }} — {{ $angka($row['excluded_total']) }} dikeluarkan
                        </summary>
                        <ul class="mt-3 space-y-1 text-xs text-gray-600 dark:text-gray-300">
                            @foreach (($row['excluded_students'] ?? []) as $siswa)
                                <li class="rounded-lg bg-gray-50 px-2 py-1 dark:bg-white/5">
                                    <span class="font-medium">{{ $siswa['name'] }}</span>
                                    <span class="opacity-70">· {{ $siswa['reason_label'] }}</span>
                                    <span class="opacity-70">· {{ $siswa['material_title'] }}</span>
                                    @if (filled($siswa['confirmed_at'] ?? null))
                                        <span class="opacity-70">· {{ $siswa['confirmed_at'] }}</span>
                                    @endif
                                    @if (filled($siswa['note'] ?? null))
                                        <span class="block opacity-70">Keterangan: {{ $siswa['note'] }}</span>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    </details>
                @endif
            @endforeach
        </div>
    @else
        <p class="text-sm text-gray-500 dark:text-gray-400">
            Belum ada dispensasi pada rentang dan filter ini.
        </p>
    @endif
</x-filament::section>
