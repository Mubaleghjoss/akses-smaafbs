@php
    $alasanTotal = $base['excluded_by_reason'] ?? [];
    $kelasRows = $base['classes'] ?? [];
    $angka = fn ($value): string => number_format((float) $value, 0, ',', '.');
    $adaDispensasi = ($base['excluded_total'] ?? 0) > 0;
    $kelasDispensasi = collect($kelasRows)->filter(fn ($row) => ($row['excluded_total'] ?? 0) > 0);
@endphp

<x-filament::section collapsible collapsed>
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
        @include('filament.pages.perpustakaan.partials.salin-bagian', [
            'teks' => $salinTeks ?? '',
            'catatan' => 'Menyalin rekap dispensasi beserta nama dan alasannya.',
        ])

        <div class="lit-cards">
            @foreach ($alasanLabels as $kode => $label)
                <div class="lit-card is-warning">
                    <p class="lit-card__label">{{ $label }}</p>
                    <p class="lit-card__value">{{ $angka($alasanTotal[$kode] ?? 0) }}</p>
                </div>
            @endforeach
        </div>

        @if ($kelasDispensasi->isNotEmpty())
            <div class="lit-details">
                @foreach ($kelasDispensasi as $row)
                    <details class="lit-detail">
                        <summary>
                            <span>{{ $row['class'] }}</span>
                            <span class="lit-detail__count">{{ $angka($row['excluded_total']) }} dikeluarkan</span>
                        </summary>
                        <div class="lit-detail__body">
                            <ul class="lit-chips">
                                @foreach (($row['excluded_students'] ?? []) as $siswa)
                                    <li class="lit-chip">
                                        {{ $siswa['name'] }}
                                        <span>· {{ $siswa['reason_label'] }}</span>
                                        <span>· {{ $siswa['material_title'] }}</span>
                                        @if (filled($siswa['confirmed_at'] ?? null))
                                            <span>· {{ $siswa['confirmed_at'] }}</span>
                                        @endif
                                        @if (filled($siswa['note'] ?? null))
                                            <span>· {{ $siswa['note'] }}</span>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    </details>
                @endforeach
            </div>
        @endif
    @else
        <p class="lit-empty">Belum ada dispensasi pada rentang dan filter ini.</p>
    @endif
</x-filament::section>
