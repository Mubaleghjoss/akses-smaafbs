<x-filament::section>
    <x-slot name="heading">Filter Analisis</x-slot>
    <x-slot name="description">
        Pilih rentang tanggal, kategori program, materi, dan kelas. Semua filter tersimpan di alamat halaman,
        jadi tautan hasil filter bisa disimpan atau dibagikan.
    </x-slot>

    <div class="grid gap-4 md:grid-cols-3 xl:grid-cols-5">
        <div>
            <label for="analisis-dari" class="block text-sm font-medium text-gray-700 dark:text-gray-200">Dari Tanggal</label>
            <input
                id="analisis-dari"
                type="date"
                wire:model.live="dari"
                class="mt-1 block w-full rounded-lg border-gray-300 bg-white text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100"
            />
        </div>

        <div>
            <label for="analisis-sampai" class="block text-sm font-medium text-gray-700 dark:text-gray-200">Sampai Tanggal</label>
            <input
                id="analisis-sampai"
                type="date"
                wire:model.live="sampai"
                class="mt-1 block w-full rounded-lg border-gray-300 bg-white text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100"
            />
        </div>

        <div>
            <label for="analisis-kategori" class="block text-sm font-medium text-gray-700 dark:text-gray-200">Kategori Program</label>
            <select
                id="analisis-kategori"
                wire:model.live="kategori"
                class="mt-1 block w-full rounded-lg border-gray-300 bg-white text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100"
            >
                <option value="">Semua kategori</option>
                @foreach ($kategoriOptions as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label for="analisis-materi" class="block text-sm font-medium text-gray-700 dark:text-gray-200">Materi</label>
            <select
                id="analisis-materi"
                wire:model.live="materi"
                class="mt-1 block w-full rounded-lg border-gray-300 bg-white text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100"
            >
                <option value="">Semua materi pada rentang ini</option>
                @foreach ($materiOptions as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label for="analisis-kelas" class="block text-sm font-medium text-gray-700 dark:text-gray-200">Kelas</label>
            <select
                id="analisis-kelas"
                wire:model.live="kelas"
                class="mt-1 block w-full rounded-lg border-gray-300 bg-white text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100"
            >
                <option value="">Semua kelas aktif</option>
                @foreach ($kelasOptions as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="mt-4 flex flex-wrap gap-2">
        <x-filament::button size="sm" color="gray" wire:click="terapkanBulanIni">Bulan Ini</x-filament::button>
        <x-filament::button size="sm" color="gray" wire:click="terapkanSemesterIni">Semester Berjalan</x-filament::button>
        <x-filament::button size="sm" color="danger" outlined wire:click="bersihkanFilter">Bersihkan Filter</x-filament::button>
    </div>

    <div class="mt-4 space-y-1 text-sm text-gray-600 dark:text-gray-300">
        <p>Periode aktif: <span class="font-semibold">{{ $periodeLabel }}</span></p>
        <p>Lingkup: <span class="font-semibold">{{ $lingkupLabel }}</span></p>
        <p>Materi tercakup: <span class="font-semibold">{{ number_format((int) ($base['material_count'] ?? 0), 0, ',', '.') }}</span></p>
    </div>
</x-filament::section>
