@if (config('webauthn.enabled'))
    <div class="admin-passkey-divider" aria-hidden="true"><span>atau masuk lebih cepat</span></div>

    <section x-data="adminPasskeyLoginBridge()" class="admin-passkey-login" aria-labelledby="passkey-login-title">
        <div class="admin-passkey-login__icon" aria-hidden="true">
            <x-heroicon-o-finger-print />
        </div>
        <div class="admin-passkey-login__content">
            <h2 id="passkey-login-title">Login dengan Sidik Jari / Passkey</h2>
            <p>Tidak perlu mengetik username. Pilih akun passkey yang tersimpan pada HP atau laptop ini.</p>
        </div>

        <button type="button" x-on:click="start($wire)" x-bind:disabled="isProcessing" class="admin-passkey-login__primary">
            <span x-show="!isProcessing"><x-heroicon-o-finger-print /> <span x-text="canRetry ? 'Coba Passkey Lagi' : 'Gunakan Passkey'">Gunakan Passkey</span></span>
            <span x-show="isProcessing" x-cloak><x-filament::loading-indicator /> Menunggu verifikasi...</span>
        </button>

        <p class="admin-passkey-login__message" x-bind:data-tone="messageTone" role="status" aria-live="polite" x-show="localMessage || @js(filled($this->passkeyMessage))" x-text="localMessage || @js((string) $this->passkeyMessage)"></p>
        <p class="admin-passkey-login__fallback">Passkey bermasalah? Form username dan password di atas tetap dapat digunakan.</p>
    </section>
@endif
