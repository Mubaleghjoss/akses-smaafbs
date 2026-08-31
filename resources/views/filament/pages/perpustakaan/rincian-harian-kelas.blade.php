@php
    $angka = fn ($value): string => number_format((float) $value, 0, ',', '.');
    $persen = fn ($value): string => $value === null ? '-' : number_format((float) $value, 1, ',', '.').'%';
    $hari = fn ($value): string => $value ? \Illuminate\Support\Carbon::parse($value)->translatedFormat('l, d F Y') : '-';

    $days = $rincian['days'] ?? [];
    $adaKelas = filled($rincian['class'] ?? null);
@endphp

<x-filament-panels::page>
    <div class="lit-stack">
        <x-filament::section>
            <x-slot name="heading">Lingkup Rincian</x-slot>
            <x-slot name="description">
                Halaman ini memisahkan rincian harian dari tabel timeline supaya daftar nama tidak lagi memanjang
                ke bawah dan menggeser baris kelas lain. Ganti kelas untuk melihat rincian kelas berikutnya tanpa
                kembali ke halaman analisis.
            </x-slot>

            <div class="lit-filter__grid">
                <label class="lit-field">
                    <span class="lit-field__label">Kelas</span>
                    <select class="lit-field__control" wire:model.live="kelas">
                        <option value="">Pilih kelas</option>
                        @foreach ($kelasOptions as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="lit-field">
                    <span class="lit-field__label">Dari</span>
                    <input type="date" class="lit-field__control" wire:model.live="dari">
                </label>

                <label class="lit-field">
                    <span class="lit-field__label">Sampai</span>
                    <input type="date" class="lit-field__control" wire:model.live="sampai">
                </label>
            </div>

            <div class="lit-meta">
                <span class="lit-meta__item">Periode: {{ $periodeLabel }}</span>
                <span class="lit-meta__item">Lingkup: {{ $lingkupLabel }}</span>
            </div>
        </x-filament::section>

        @if (! $adaKelas)
            <x-filament::section>
                <p class="lit-empty">Pilih kelas terlebih dahulu untuk menampilkan rincian harian.</p>
            </x-filament::section>
        @else
            <x-filament::section>
                <x-slot name="heading">Ringkasan {{ $rincian['class'] }}</x-slot>
                <x-slot name="description">
                    Penyebut persentase adalah basis responden kelas ini: slot siswa aktif dikurangi slot
                    dispensasi terkonfirmasi.
                </x-slot>

                <div class="lit-cards lit-cards--tight">
                    <div class="lit-card">
                        <span class="lit-card__label">Pengisian</span>
                        <span class="lit-card__value">{{ $rincian['ratio'] ?? '0/0' }}</span>
                        <span class="lit-card__hint">{{ $persen($rincian['percentage'] ?? null) }}</span>
                    </div>
                    <div class="lit-card">
                        <span class="lit-card__label">Materi Wajib</span>
                        <span class="lit-card__value">{{ $angka($rincian['material_count'] ?? 0) }}</span>
                        <span class="lit-card__hint">materi pada rentang ini</span>
                    </div>
                    <div class="lit-card">
                        <span class="lit-card__label">Siswa</span>
                        <span class="lit-card__value">{{ $angka($rincian['unique_students'] ?? 0) }}</span>
                        <span class="lit-card__hint">{{ $angka($rincian['active_total'] ?? 0) }} slot aktif</span>
                    </div>
                    <div class="lit-card">
                        <span class="lit-card__label">Belum Mengisi</span>
                        <span class="lit-card__value">{{ $angka($rincian['missing_total'] ?? 0) }}</span>
                        <span class="lit-card__hint">slot kosong</span>
                    </div>
                    <div class="lit-card">
                        <span class="lit-card__label">Dispensasi</span>
                        <span class="lit-card__value">{{ $angka($rincian['excluded_total'] ?? 0) }}</span>
                        <span class="lit-card__hint">tidak dihitung</span>
                    </div>
                </div>

                @if (($rincian['materials'] ?? []) !== [])
                    <p class="lit-subhead">Materi pada rentang ini</p>
                    <ul class="lit-chips">
                        @foreach ($rincian['materials'] as $materi)
                            <li class="lit-chip">{{ $materi['title'] }}</li>
                        @endforeach
                    </ul>
                @endif
            </x-filament::section>

            <x-filament::section>
                <x-slot name="heading">Rincian Per Hari</x-slot>
                <x-slot name="description">
                    Tiap hari ditampilkan sebagai kartu dengan dua kolom sejajar: siswa yang mengisi pada hari itu
                    dan siswa yang sampai akhir hari itu masih belum mengisi. Kolom "belum" bersifat kumulatif,
                    sehingga nama akan hilang begitu slotnya terisi pada hari berikutnya.
                </x-slot>

                @if ($days === [])
                    <p class="lit-empty">Belum ada pengisian pada rentang ini.</p>
                @else
                    <div class="lit-dayGrid">
                        @foreach ($days as $day)
                            <article class="lit-day">
                                <header class="lit-day__head">
                                    <span class="lit-day__date">{{ $hari($day['date'] ?? null) }}</span>
                                    <span class="lit-day__stats">
                                        <span class="lit-day__badge is-good">{{ $angka($day['total'] ?? 0) }} mengisi</span>
                                        <span class="lit-day__badge is-bad">{{ $angka($day['pending_total'] ?? 0) }} belum</span>
                                        <span class="lit-day__time">
                                            {{ ($day['first_at'] ?? null) ? $day['first_at']->format('H:i') : '-' }}
                                            &ndash;
                                            {{ ($day['last_at'] ?? null) ? $day['last_at']->format('H:i') : '-' }}
                                        </span>
                                    </span>
                                </header>

                                <div class="lit-day__cols">
                                    <div class="lit-day__col">
                                        <p class="lit-day__colHead">Sudah mengisi ({{ $angka($day['total'] ?? 0) }})</p>
                                        @if (($day['students'] ?? []) === [])
                                            <p class="lit-empty">Tidak ada.</p>
                                        @else
                                            <ol class="lit-nameList">
                                                @foreach ($day['students'] as $student)
                                                    <li>
                                                        <span class="lit-nameList__name">{{ $student['name'] }}</span>
                                                        <span class="lit-nameList__meta">{{ $student['time'] }} &middot; {{ $student['material_title'] }}</span>
                                                    </li>
                                                @endforeach
                                            </ol>
                                        @endif
                                    </div>

                                    <div class="lit-day__col">
                                        <p class="lit-day__colHead">Belum mengisi ({{ $angka($day['pending_total'] ?? 0) }})</p>
                                        @if (($day['pending_students'] ?? []) === [])
                                            <p class="lit-empty">Semua slot sudah terisi sampai hari ini.</p>
                                        @else
                                            <ol class="lit-nameList is-pending">
                                                @foreach ($day['pending_students'] as $student)
                                                    <li>
                                                        <span class="lit-nameList__name">{{ $student['name'] }}</span>
                                                        <span class="lit-nameList__meta">{{ $student['material_title'] }}</span>
                                                    </li>
                                                @endforeach
                                            </ol>
                                        @endif
                                    </div>
                                </div>
                            </article>
                        @endforeach
                    </div>
                @endif
            </x-filament::section>

            @if (($rincian['missing_students'] ?? []) !== [])
                <x-filament::section collapsible collapsed>
                    <x-slot name="heading">Belum Mengisi Sampai Akhir Rentang ({{ $angka($rincian['missing_total'] ?? 0) }})</x-slot>

                    <ol class="lit-nameList is-pending">
                        @foreach ($rincian['missing_students'] as $student)
                            <li>
                                <span class="lit-nameList__name">{{ $student['name'] }}</span>
                                <span class="lit-nameList__meta">{{ $student['material_title'] }}</span>
                            </li>
                        @endforeach
                    </ol>
                </x-filament::section>
            @endif

            @if (($rincian['excluded_students'] ?? []) !== [])
                <x-filament::section collapsible collapsed>
                    <x-slot name="heading">Dispensasi &mdash; Tidak Dihitung ({{ $angka($rincian['excluded_total'] ?? 0) }})</x-slot>

                    <ol class="lit-nameList">
                        @foreach ($rincian['excluded_students'] as $student)
                            <li>
                                <span class="lit-nameList__name">{{ $student['name'] }}</span>
                                <span class="lit-nameList__meta">{{ $student['reason_label'] }} &middot; {{ $student['material_title'] }}</span>
                            </li>
                        @endforeach
                    </ol>
                </x-filament::section>
            @endif
        @endif
    </div>
</x-filament-panels::page>
