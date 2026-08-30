@php
    // Semua bagian teks dibangun di server, lalu Alpine hanya memilih mana yang
    // ditampilkan. Dengan begitu tidak ada round-trip Livewire saat ganti tab.
    $tabs = collect($sectionLabels)
        ->filter(fn ($label, $key) => array_key_exists($key, $sections))
        ->all();
    $tabs['bulanan'] = 'Rekap Bulanan Lengkap';
    $payload = $sections + ['bulanan' => $monthlyText];
    $firstKey = array_key_first($tabs);
@endphp

<div
    class="lit-share"
    x-data="{
        aktif: @js($firstKey),
        teks: @js($payload),
        tersalin: false,
        get nilai() { return this.teks[this.aktif] ?? ''; },
        get jumlahBaris() { return this.nilai === '' ? 0 : this.nilai.split('\n').length; },
        async salin() {
            const isi = this.nilai;

            try {
                await navigator.clipboard.writeText(isi);
            } catch (e) {
                // Fallback untuk browser/konteks tanpa Clipboard API (mis. http).
                const area = this.$refs.area;
                area.removeAttribute('readonly');
                area.select();
                document.execCommand('copy');
                area.setAttribute('readonly', 'readonly');
                window.getSelection()?.removeAllRanges();
            }

            this.tersalin = true;
            setTimeout(() => { this.tersalin = false; }, 2000);
        },
        bukaWhatsApp() {
            window.open('https://wa.me/?text=' + encodeURIComponent(this.nilai), '_blank', 'noopener');
        },
    }"
>
    <p class="lit-share__hint">
        Periode <strong>{{ $periodeLabel }}</strong> · Lingkup <strong>{{ $lingkupLabel }}</strong>.
        Pilih bagian, salin, lalu tempel di WhatsApp. Tanda bintang akan otomatis menjadi teks tebal di WhatsApp.
    </p>

    <div class="lit-share__tabs">
        @foreach ($tabs as $key => $label)
            <button
                type="button"
                class="lit-share__tab"
                x-bind:class="aktif === @js($key) ? 'is-active' : ''"
                x-on:click="aktif = @js($key); tersalin = false"
            >{{ $label }}</button>
        @endforeach
    </div>

    <textarea
        x-ref="area"
        class="lit-share__text"
        readonly
        spellcheck="false"
        x-bind:value="nilai"
        x-on:focus="$el.select()"
    ></textarea>

    <div class="lit-share__actions">
        <x-filament::button color="success" icon="heroicon-m-clipboard" x-on:click="salin()">
            <span x-text="tersalin ? 'Tersalin' : 'Salin Teks'">Salin Teks</span>
        </x-filament::button>

        <x-filament::button color="gray" icon="heroicon-m-arrow-top-right-on-square" x-on:click="bukaWhatsApp()">
            Buka di WhatsApp
        </x-filament::button>

        <p class="lit-share__count">
            <span x-text="jumlahBaris"></span> baris · <span x-text="nilai.length"></span> karakter
        </p>
    </div>
</div>
