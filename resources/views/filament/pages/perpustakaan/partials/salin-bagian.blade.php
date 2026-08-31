@php
    // Bar salin per bagian: satu tombol yang menyalin teks WhatsApp untuk
    // bagian yang sedang dilihat, tanpa membuka modal.
    $teks = $teks ?? '';
    $catatan = $catatan ?? 'Teks mengikuti filter yang aktif.';
    // Livewire mempertahankan state Alpine saat morph. Key berbasis payload
    // memaksa clipboard memakai teks baru setiap kali filter berubah.
    $payloadKey = hash('sha256', $teks);
@endphp

<div
    wire:key="lit-copybar-{{ $payloadKey }}"
    class="lit-copybar"
    x-data="{
        tersalin: false,
        teks: @js($teks),
        async salin() {
            try {
                await navigator.clipboard.writeText(this.teks);
            } catch (e) {
                const area = document.createElement('textarea');
                area.value = this.teks;
                area.setAttribute('readonly', 'readonly');
                area.style.position = 'fixed';
                area.style.opacity = '0';
                document.body.appendChild(area);
                area.select();
                document.execCommand('copy');
                area.remove();
            }

            this.tersalin = true;
            setTimeout(() => { this.tersalin = false; }, 2000);
        },
    }"
>
    <x-filament::button size="sm" color="success" icon="heroicon-m-clipboard" x-on:click="salin()">
        <span x-text="tersalin ? 'Tersalin' : 'Salin Bagian Ini'">Salin Bagian Ini</span>
    </x-filament::button>

    <p class="lit-copybar__hint">{{ $catatan }}</p>
</div>
