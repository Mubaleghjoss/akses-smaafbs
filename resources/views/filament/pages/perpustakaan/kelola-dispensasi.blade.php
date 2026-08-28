<x-filament-panels::page>
    <div class="space-y-6">
        <x-filament::section>
            <x-slot name="heading">Cara Kerja Dispensasi</x-slot>
            <x-slot name="description">
                Siswa dengan status Izin, Sakit, atau Tes MT dikeluarkan dari basis responden materi terkait —
                tidak dihitung mengisi maupun belum mengisi. Dispensasi berlaku per materi, jadi gunakan
                "Tetapkan Dispensasi" untuk menandai beberapa materi sekaligus.
            </x-slot>
        </x-filament::section>

        {{ $this->table }}
    </div>
</x-filament-panels::page>
