<div
    x-data="adminPasskeyLoginBridge()"
    class="admin-passkey-login mt-3"
>
    <div class="admin-passkey-login__panel">
        <div class="admin-passkey-login__title">Login biometrik</div>
        <p class="admin-passkey-login__hint">Gunakan fingerprint atau face unlock jika perangkat ini sudah terdaftar.</p>

        <button
            type="button"
            x-on:click="start($wire)"
            x-bind:disabled="isProcessing"
            class="admin-passkey-login__primary"
        >
            Login biometrik
        </button>

        @if (filled($this->passkeyMessage))
            <p class="admin-passkey-login__message">
                {{ $this->passkeyMessage }}
            </p>
        @endif

        <div class="admin-passkey-login__actions">
            @if (filled($this->passkeyMessage))
                <button
                    type="button"
                    wire:click="dismissPasskeyState"
                    x-bind:disabled="isProcessing"
                    class="admin-passkey-login__secondary"
                >
                    Batal
                </button>
            @endif
            @if (! empty($this->rememberedUsernames))
                <button
                    type="button"
                    wire:click="clearRememberedUsernames"
                    class="admin-passkey-login__secondary"
                >
                    Hapus username
                </button>
            @endif
        </div>
    </div>
</div>

@once
    <script>
        function adminPasskeyLoginBridge() {
            return {
                isProcessing: false,
                decodeCredentialId(value) {
                    if (typeof value !== 'string' || value.trim() === '') {
                        return new Uint8Array();
                    }

                    const normalized = value.trim().replace(/-/g, '+').replace(/_/g, '/');
                    const padded = normalized + '='.repeat((4 - (normalized.length % 4)) % 4);

                    try {
                        const binary = window.atob(padded);
                        const bytes = new Uint8Array(binary.length);

                        for (let index = 0; index < binary.length; index++) {
                            bytes[index] = binary.charCodeAt(index);
                        }

                        return bytes;
                    } catch (_) {
                        return new TextEncoder().encode(value);
                    }
                },
                decodeChallenge(value) {
                    return this.decodeCredentialId(value);
                },
                encodeBufferToBase64Url(buffer) {
                    const bytes = buffer instanceof Uint8Array ? buffer : new Uint8Array(buffer);
                    let binary = '';

                    for (const byte of bytes) {
                        binary += String.fromCharCode(byte);
                    }

                    return window.btoa(binary)
                        .replace(/\+/g, '-')
                        .replace(/\//g, '_')
                        .replace(/=+$/g, '');
                },
                extractSignCount(assertion) {
                    const authenticatorData = assertion?.response?.authenticatorData;

                    if (!(authenticatorData instanceof ArrayBuffer) || authenticatorData.byteLength < 37) {
                        return null;
                    }

                    const view = new DataView(authenticatorData);

                    return view.getUint32(33, false);
                },
                async start($wire) {
                    if (this.isProcessing) {
                        return;
                    }

                    this.isProcessing = true;

                    try {
                        const browserSupported = !!(window.PublicKeyCredential && navigator.credentials?.get);
                        const payload = await $wire.beginPasskeyLogin(browserSupported);

                        if (!browserSupported || !payload || payload.status !== 'issued' || !payload.challenge) {
                            return;
                        }

                        const assertion = await navigator.credentials.get({
                            publicKey: {
                                challenge: this.decodeChallenge(payload.challenge),
                                allowCredentials: (payload.allowCredentialIds ?? []).map((credentialId) => ({
                                    id: this.decodeCredentialId(credentialId),
                                    type: 'public-key',
                                })),
                                userVerification: 'preferred',
                                timeout: 60000,
                            },
                        });

                        if (!assertion) {
                            await $wire.cancelPasskeyLogin();

                            return;
                        }

                        const credentialId = this.encodeBufferToBase64Url(assertion.rawId)
                            || assertion.id;

                        if (!credentialId) {
                            await $wire.cancelPasskeyLogin();

                            return;
                        }

                        const signCount = this.extractSignCount(assertion);
                        const assertionPayload = {
                            credential_id: credentialId,
                            raw_id: this.encodeBufferToBase64Url(assertion.rawId),
                            client_data_json: this.encodeBufferToBase64Url(assertion.response?.clientDataJSON ?? new Uint8Array()),
                            authenticator_data: this.encodeBufferToBase64Url(assertion.response?.authenticatorData ?? new Uint8Array()),
                            signature: this.encodeBufferToBase64Url(assertion.response?.signature ?? new Uint8Array()),
                        };

                        if (assertion.response?.userHandle instanceof ArrayBuffer) {
                            assertionPayload.user_handle = this.encodeBufferToBase64Url(assertion.response.userHandle);
                        }

                        await $wire.completePasskeyLogin(
                            credentialId,
                            signCount,
                            payload.challengeId ?? null,
                            payload.challenge ?? null,
                            assertionPayload,
                        );
                    } catch (error) {
                        if (error?.name === 'NotAllowedError' || error?.name === 'AbortError') {
                            await $wire.cancelPasskeyLogin();

                            return;
                        }

                        await $wire.cancelPasskeyLogin();
                    } finally {
                        this.isProcessing = false;
                    }
                },
            };
        }
    </script>
@endonce
