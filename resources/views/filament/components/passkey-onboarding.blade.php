@php
    $showPasskeyOnboarding = config('webauthn.enabled')
        && request()->routeIs('filament.admin.pages.dashboard')
        && auth()->check()
        && ! auth()->user()->webAuthnCredentials()->whereNull('revoked_at')->whereNotNull('credential_public_key')->whereNotNull('verified_at')->exists();
@endphp

@if ($showPasskeyOnboarding)
    <section x-data="{ visible: localStorage.getItem('sma-afbs-passkey-onboarding-dismissed') !== '1' }" x-show="visible" x-cloak class="passkey-onboarding">
        <div class="passkey-onboarding__icon"><x-heroicon-o-finger-print /></div>
        <div class="passkey-onboarding__content">
            <strong>Login lebih cepat dengan sidik jari</strong>
            <p>Aktifkan passkey pada perangkat pribadi. Password tetap dapat dipakai kapan saja.</p>
        </div>
        <a href="{{ \App\Filament\Pages\ManagePasskeys::getUrl() }}" class="passkey-onboarding__action">Aktifkan Sekarang</a>
        <button type="button" class="passkey-onboarding__dismiss" aria-label="Tutup pengingat" x-on:click="visible = false; localStorage.setItem('sma-afbs-passkey-onboarding-dismissed', '1')"><x-heroicon-o-x-mark /></button>
    </section>
@endif
