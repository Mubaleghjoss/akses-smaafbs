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
            @class([
                'literasi-status-button',
                'literasi-status-button--active',
                'is-selected' => $activeStatus === 'active',
            ])
            aria-selected="{{ $activeStatus === 'active' ? 'true' : 'false' }}"
        >
            <span class="literasi-status-button__icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m5 12 4 4L19 6" />
                </svg>
            </span>
            <span class="literasi-status-button__label">Aktif</span>
            <strong class="literasi-status-button__count">{{ number_format((int) ($statusCounts['active'] ?? 0), 0, ',', '.') }}</strong>
        </button>

        <button
            type="button"
            role="tab"
            wire:click="setMaterialStatus('inactive')"
            wire:loading.attr="disabled"
            wire:target="setMaterialStatus"
            @class([
                'literasi-status-button',
                'literasi-status-button--inactive',
                'is-selected' => $activeStatus === 'inactive',
            ])
            aria-selected="{{ $activeStatus === 'inactive' ? 'true' : 'false' }}"
        >
            <span class="literasi-status-button__icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18 18 6M6 6l12 12" />
                </svg>
            </span>
            <span class="literasi-status-button__label">Tidak Aktif</span>
            <strong class="literasi-status-button__count">{{ number_format((int) ($statusCounts['inactive'] ?? 0), 0, ',', '.') }}</strong>
        </button>
    </div>
</section>
