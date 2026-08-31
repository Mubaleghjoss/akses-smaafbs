<x-filament::section collapsible collapsed>
    <x-slot name="heading">Filter Analisis</x-slot>
    <x-slot name="description">
        Pilih rentang tanggal, kategori program, materi, dan kelas. Semua filter tersimpan di alamat halaman,
        jadi tautan hasil filter bisa disimpan atau dibagikan.
    </x-slot>

    <div class="lit-filter__grid">
        <div class="lit-field">
            <label for="analisis-dari" class="lit-field__label">Dari Tanggal</label>
            <input id="analisis-dari" type="date" class="lit-field__control" wire:model.live="dari" />
        </div>

        <div class="lit-field">
            <label for="analisis-sampai" class="lit-field__label">Sampai Tanggal</label>
            <input id="analisis-sampai" type="date" class="lit-field__control" wire:model.live="sampai" />
        </div>

        <div class="lit-field">
            <label for="analisis-kategori" class="lit-field__label">Kategori Program</label>
            <select id="analisis-kategori" class="lit-field__control" wire:model.live="kategori">
                <option value="">Semua kategori</option>
                @foreach ($kategoriOptions as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <div class="lit-field">
            <label for="analisis-materi" class="lit-field__label">Materi</label>
            <select id="analisis-materi" class="lit-field__control" wire:model.live="materi">
                <option value="">Semua materi pada rentang ini</option>
                @foreach ($materiOptions as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <div class="lit-field">
            <label for="analisis-kelas" class="lit-field__label">Kelas</label>
            <select id="analisis-kelas" class="lit-field__control" wire:model.live="kelas">
                <option value="">Semua kelas aktif</option>
                @foreach ($kelasOptions as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="lit-actions">
        <x-filament::button size="sm" color="gray" wire:click="terapkanBulanIni">Bulan Ini</x-filament::button>
        <x-filament::button size="sm" color="gray" wire:click="terapkanSemesterIni">Semester Berjalan</x-filament::button>
        <x-filament::button size="sm" color="danger" outlined wire:click="bersihkanFilter">Bersihkan Filter</x-filament::button>
    </div>

    <div class="lit-meta">
        <div class="lit-meta__item">Periode aktif <strong>{{ $periodeLabel }}</strong></div>
        <div class="lit-meta__item">Lingkup <strong>{{ $lingkupLabel }}</strong></div>
        <div class="lit-meta__item">Materi tercakup <strong>{{ number_format((int) ($base['material_count'] ?? 0), 0, ',', '.') }}</strong></div>
    </div>
</x-filament::section>
