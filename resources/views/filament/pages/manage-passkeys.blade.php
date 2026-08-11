<x-filament-panels::page>
    <div x-data="adminPasskeySettingsBridge()" class="passkey-settings">
        <section class="passkey-hero">
            <div class="passkey-hero__icon"><x-heroicon-o-finger-print /></div>
            <div class="passkey-hero__content">
                <span class="passkey-status passkey-status--{{ str($this->passkeyStatus)->slug() }}">{{ $this->passkeyStatus }}</span>
                <h2>Masuk tanpa mengetik username</h2>
                <p>Gunakan sidik jari, pengenalan wajah, PIN Windows Hello, atau pengunci layar HP. Password tetap tersedia sebagai cadangan.</p>
            </div>
            <div class="passkey-hero__count">
                <strong>{{ $this->activeCredentials->count() }}</strong>
                <span>dari {{ config('webauthn.max_credentials_per_user', 5) }} perangkat aktif</span>
            </div>
        </section>

        <section class="passkey-card">
            <div class="passkey-card__heading">
                <div>
                    <span class="passkey-eyebrow">Aktivasi perangkat</span>
                    <h3>Tiga langkah singkat</h3>
                </div>
            </div>

            <ol class="passkey-steps">
                <li><span>1</span><div><strong>Beri nama perangkat</strong><p>Opsional, agar mudah mengenali HP atau laptop.</p></div></li>
                <li><span>2</span><div><strong>Tekan tombol aktivasi</strong><p>Browser akan membuka Windows Hello atau pengunci layar.</p></div></li>
                <li><span>3</span><div><strong>Verifikasi diri</strong><p>Kunci teknis dibuat otomatis dan diverifikasi server.</p></div></li>
            </ol>

            <div class="passkey-trust-note">
                <x-heroicon-o-shield-check />
                <p>Aktifkan hanya pada HP/laptop pribadi atau perangkat sekolah yang benar-benar tepercaya. Sidik jari tidak pernah dikirim atau disimpan oleh aplikasi.</p>
            </div>

            <div class="passkey-register-form">
                <label for="passkey-device-name">Nama perangkat <span>(opsional)</span></label>
                <input id="passkey-device-name" type="text" maxlength="100" wire:model="deviceName" placeholder="Contoh: HP Samsung pribadi" autocomplete="off">
                @error('deviceName') <p class="passkey-field-error">{{ $message }}</p> @enderror

                <button type="button" class="passkey-primary-button" x-on:click="register($wire)" x-bind:disabled="isProcessing || {{ $this->activeCredentials->count() >= config('webauthn.max_credentials_per_user', 5) ? 'true' : 'false' }}">
                    <span x-show="!isProcessing"><x-heroicon-o-finger-print /> Aktifkan Passkey di Perangkat Ini</span>
                    <span x-show="isProcessing" x-cloak><x-filament::loading-indicator /> Menunggu verifikasi...</span>
                </button>
                <p class="passkey-live-message" role="status" aria-live="polite" x-show="localMessage || @js(filled($registrationMessage))" x-text="localMessage || @js((string) $registrationMessage)"></p>
            </div>
        </section>

        <section class="passkey-card">
            <div class="passkey-card__heading">
                <div><span class="passkey-eyebrow">Perangkat tepercaya</span><h3>Passkey aktif</h3></div>
                <span class="passkey-count-badge">{{ $this->activeCredentials->count() }}</span>
            </div>

            <div class="passkey-device-list">
                @forelse ($this->activeCredentials as $credential)
                    <article class="passkey-device">
                        <div class="passkey-device__icon"><x-heroicon-o-device-phone-mobile /></div>
                        <div class="passkey-device__content">
                            <strong>{{ $credential->device_name ?: $credential->label ?: 'Perangkat passkey' }}</strong>
                            <span>Ditambahkan {{ $credential->verified_at?->format('d/m/Y H:i') ?? $credential->created_at?->format('d/m/Y H:i') }}</span>
                            <span>Terakhir digunakan: {{ $credential->last_used_at?->diffForHumans() ?? 'Belum pernah' }}</span>
                        </div>

                        <x-filament::modal id="revoke-passkey-{{ $credential->id }}" width="md">
                            <x-slot name="trigger">
                                <x-filament::button color="danger" outlined icon="heroicon-o-no-symbol">Nonaktifkan</x-filament::button>
                            </x-slot>
                            <x-slot name="heading">Nonaktifkan passkey?</x-slot>
                            <x-slot name="description">Perangkat ini tidak dapat digunakan untuk login lagi. Riwayatnya tetap disimpan.</x-slot>
                            <div class="passkey-modal-device">{{ $credential->device_name ?: $credential->label ?: 'Perangkat passkey' }}</div>
                            <x-slot name="footerActions">
                                <x-filament::button color="gray" x-on:click="$dispatch('close-modal', { id: 'revoke-passkey-{{ $credential->id }}' })">Batal</x-filament::button>
                                <x-filament::button color="danger" wire:click="revokePasskey('{{ $credential->credential_id }}')" x-on:click="$dispatch('close-modal', { id: 'revoke-passkey-{{ $credential->id }}' })">Ya, Nonaktifkan</x-filament::button>
                            </x-slot>
                        </x-filament::modal>
                    </article>
                @empty
                    <div class="passkey-empty"><x-heroicon-o-finger-print /><strong>Belum ada passkey aktif</strong><p>Daftarkan perangkat di atas. Sesudah berhasil, tombol login sidik jari dapat langsung digunakan.</p></div>
                @endforelse
            </div>
        </section>

        @if ($this->historyCredentials->isNotEmpty())
            <details class="passkey-card passkey-history">
                <summary><span>Riwayat & perangkat yang perlu daftar ulang</span><span>{{ $this->historyCredentials->count() }}</span></summary>
                <div class="passkey-device-list">
                    @foreach ($this->historyCredentials as $credential)
                        <article class="passkey-device passkey-device--history">
                            <div class="passkey-device__icon"><x-heroicon-o-clock /></div>
                            <div class="passkey-device__content">
                                <strong>{{ $credential->device_name ?: $credential->label ?: 'Credential lama' }}</strong>
                                <span>{{ $credential->isLegacy() ? 'Perlu daftar ulang — belum memiliki kunci attestation terverifikasi.' : 'Dinonaktifkan '.$credential->revoked_at?->format('d/m/Y H:i') }}</span>
                            </div>
                            <span class="passkey-status passkey-status--history">{{ $credential->isLegacy() ? 'Daftar ulang' : 'Nonaktif' }}</span>
                        </article>
                    @endforeach
                </div>
            </details>
        @endif
    </div>
</x-filament-panels::page>
