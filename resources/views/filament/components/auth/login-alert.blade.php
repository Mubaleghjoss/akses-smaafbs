@php
    $loginErrorMessage = $getLivewire()->loginErrorMessage ?? null;
@endphp

@if (filled($loginErrorMessage))
    <div
        class="admin-login-alert"
        role="alert"
        aria-live="assertive"
        aria-atomic="true"
        tabindex="-1"
        wire:key="admin-login-error-alert"
        x-init="$nextTick(() => $el.focus())"
    >
        <span class="admin-login-alert__icon" aria-hidden="true">!</span>
        <span class="admin-login-alert__content">
            <strong>Login belum berhasil</strong>
            <span>{{ $loginErrorMessage }}</span>
        </span>
    </div>
@endif
