@php
    $timeline = $analytics['class_submission_timeline'] ?? [];
    $angka = fn ($value): string => number_format((float) $value, 0, ',', '.');
    $persen = fn ($value): string => $value === null ? '-' : number_format((float) $value, 1, ',', '.').'%';
    $tanggal = fn ($value): string => $value ? $value->translatedFormat('d M Y H:i') : '-';
    $hari = fn ($value): string => $value ? \Illuminate\Support\Carbon::parse($value)->translatedFormat('d M Y') : '-';
@endphp

<x-filament::section collapsible>
    <x-slot name="heading">Timeline Pengisian Per Kelas</x-slot>
    <x-slot name="description">
        Kapan setiap kelas mulai dan terakhir mengisi pada rentang aktif, berapa hari kelas itu aktif, dan
        hari dengan pengisian terbanyak. Urut dari kelas yang paling awal mulai.
    </x-slot>

    @include('filament.pages.perpustakaan.partials.salin-bagian', [
        'teks' => $shareSections['timeline'] ?? '',
        'catatan' => 'Menyalin timeline pengisian seluruh kelas pada rentang ini.',
    ])

    <div class="lit-tableWrap">
        <table class="lit-table">
            <thead>
                <tr>
                    <th>Kelas</th>
                    <th class="is-num">Pengisian</th>
                    <th class="is-num">Persen</th>
                    <th>Mulai</th>
                    <th>Terakhir</th>
                    <th class="is-num">Hari Aktif</th>
                    <th>Hari Tersibuk</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($timeline as $row)
                    <tr>
                        <td class="is-name" data-label="Kelas">{{ $row['class'] }}</td>
                        <td class="is-num" data-label="Pengisian">
                            {{ $angka($row['total'] ?? 0) }}/{{ $angka($row['respondent_base'] ?? 0) }}
                        </td>
                        <td class="is-num" data-label="Persen">
                            @php $p = $row['percentage'] ?? null; @endphp
                            <span class="lit-pct {{ $p === null ? 'is-none' : ($p >= 80 ? 'is-good' : ($p >= 50 ? 'is-warn' : 'is-bad')) }}">
                                {{ $persen($p) }}
                            </span>
                        </td>
                        <td data-label="Mulai">{{ $tanggal($row['first_at'] ?? null) }}</td>
                        <td data-label="Terakhir">{{ $tanggal($row['last_at'] ?? null) }}</td>
                        <td class="is-num" data-label="Hari Aktif">
                            {{ $angka($row['active_days'] ?? 0) }}
                            <span class="lit-timeline__span">dari {{ $angka($row['span_days'] ?? 0) }} hari</span>
                        </td>
                        <td data-label="Hari Tersibuk">
                            {{ $hari($row['busiest_day'] ?? null) }}
                            <span class="lit-timeline__span">{{ $angka($row['busiest_day_total'] ?? 0) }} pengisian</span>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="lit-empty">Belum ada pengisian pada rentang ini.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-filament::section>
