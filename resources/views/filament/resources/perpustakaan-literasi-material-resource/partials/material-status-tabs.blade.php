<section class="literasi-material-status" aria-label="Status materi pada kategori aktif">
    <div class="literasi-material-status__copy">
        <span>Status Materi</span>
        <small>Pilih materi yang masih aktif atau sudah tidak aktif.</small>
    </div>

    <div class="literasi-material-status__tabs" role="tablist" aria-label="Status aktif materi">
        <button
            type="button"
            role="tab"
            wire:click="setMaterialStatus('active')"
            wire:loading.attr="disabled"
            wire:target="setMaterialStatus"
            @class(['is-active' => $activeStatus === 'active'])
            aria-selected="{{ $activeStatus === 'active' ? 'true' : 'false' }}"
        >
            <span>Aktif</span>
            <strong>{{ number_format((int) ($statusCounts['active'] ?? 0), 0, ',', '.') }}</strong>
        </button>

        <button
            type="button"
            role="tab"
            wire:click="setMaterialStatus('inactive')"
            wire:loading.attr="disabled"
            wire:target="setMaterialStatus"
            @class(['is-active' => $activeStatus === 'inactive'])
            aria-selected="{{ $activeStatus === 'inactive' ? 'true' : 'false' }}"
        >
            <span>Tidak Aktif</span>
            <strong>{{ number_format((int) ($statusCounts['inactive'] ?? 0), 0, ',', '.') }}</strong>
        </button>
    </div>
</section>
