(function () {
    'use strict';

    if (window.SmaAfbsPasskeys) {
        return;
    }

    const decode = (value) => {
        const normalized = String(value || '').replace(/-/g, '+').replace(/_/g, '/');
        const padded = normalized + '='.repeat((4 - (normalized.length % 4)) % 4);
        const binary = window.atob(padded);
        const bytes = new Uint8Array(binary.length);

        for (let index = 0; index < binary.length; index += 1) {
            bytes[index] = binary.charCodeAt(index);
        }

        return bytes;
    };

    const encode = (buffer) => {
        const bytes = buffer instanceof Uint8Array ? buffer : new Uint8Array(buffer || []);
        let binary = '';

        for (const byte of bytes) {
            binary += String.fromCharCode(byte);
        }

        return window.btoa(binary).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/g, '');
    };

    const normalizeOptions = (source) => {
        const options = JSON.parse(JSON.stringify(source || {}));

        if (options.challenge) {
            options.challenge = decode(options.challenge);
        }

        if (options.user?.id) {
            options.user.id = decode(options.user.id);
        }

        for (const key of ['excludeCredentials', 'allowCredentials']) {
            if (Array.isArray(options[key])) {
                options[key] = options[key].map((item) => ({ ...item, id: decode(item.id) }));
            }
        }

        return options;
    };

    const supported = () => Boolean(window.isSecureContext && window.PublicKeyCredential && navigator.credentials);

    const isCredentialManagerUnknownError = (error) => {
        const message = String(error?.message || '').toLowerCase();

        return error?.name === 'UnknownError'
            || message.includes('credential manager')
            || message.includes('pengelola kredensial');
    };

    const recoverCredentialManager = async () => {
        try {
            await navigator.credentials?.preventSilentAccess?.();
        } catch (error) {
            console.debug('Credential Manager silent access reset was skipped', error);
        }

        await new Promise((resolve) => window.setTimeout(resolve, 500));
    };

    const reportClientFailure = async ($wire, challengeId, errorCode) => {
        try {
            await $wire.reportPasskeyClientFailure(challengeId, errorCode);
        } catch (error) {
            console.debug('Passkey client failure report was skipped', error);
        }
    };

    const friendlyError = (error) => {
        if (error?.name === 'NotAllowedError' || error?.name === 'AbortError') {
            return 'Permintaan passkey dibatalkan atau waktunya habis. Anda dapat mencoba lagi.';
        }

        if (error?.name === 'InvalidStateError') {
            return 'Passkey perangkat ini sudah terdaftar pada akun.';
        }

        if (error?.name === 'SecurityError') {
            return 'Passkey hanya dapat digunakan melalui alamat HTTPS resmi app.smaafbs.sch.id.';
        }

        if (isCredentialManagerUnknownError(error)) {
            return 'Pengelola passkey perangkat belum merespons. Tutup dialog sidik jari lain yang masih terbuka, pastikan layar perangkat sudah terbuka, lalu coba kembali. Jika membuka dari WhatsApp, gunakan Chrome atau browser utama.';
        }

        return error?.message || 'Passkey tidak dapat diproses. Gunakan password atau coba kembali.';
    };

    window.SmaAfbsPasskeys = {
        decode,
        encode,
        normalizeOptions,
        supported,
        friendlyError,
        isCredentialManagerUnknownError,
        recoverCredentialManager,
        reportClientFailure,
    };

    window.adminPasskeyLoginBridge = function () {
        return {
            isProcessing: false,
            localMessage: '',
            messageTone: 'info',
            canRetry: false,
            async start($wire) {
                if (this.isProcessing) return;
                this.isProcessing = true;
                this.canRetry = false;
                this.messageTone = 'info';
                this.localMessage = supported() ? 'Memeriksa passkey pada perangkat...' : 'Browser atau koneksi ini belum mendukung passkey.';

                try {
                    for (let attempt = 0; attempt <= 1; attempt += 1) {
                        const issue = await $wire.beginPasskeyLogin(supported());

                        if (!supported() || issue?.status !== 'issued' || !issue.publicKeyOptions) {
                            this.messageTone = 'error';
                            this.canRetry = supported();
                            this.localMessage = issue?.status === 'disabled'
                                ? 'Login passkey sedang dinonaktifkan. Gunakan username dan password.'
                                : 'Browser atau perangkat ini belum mendukung passkey. Gunakan username dan password.';
                            return;
                        }

                        this.localMessage = attempt === 0
                            ? 'Menunggu sidik jari, PIN, atau pengunci layar...'
                            : 'Mencoba kembali pengelola passkey perangkat...';

                        const credentialRequest = {
                            publicKey: normalizeOptions(issue.publicKeyOptions),
                        };

                        if (attempt > 0) {
                            credentialRequest.mediation = 'required';
                        }

                        try {
                            const assertion = await navigator.credentials.get(credentialRequest);

                            if (!assertion) throw new DOMException('Passkey dibatalkan.', 'AbortError');

                            this.localMessage = 'Memverifikasi passkey...';
                            const loginResult = await $wire.completePasskeyLogin(encode(assertion.rawId), issue.challengeId, {
                                credential_id: encode(assertion.rawId),
                                raw_id: encode(assertion.rawId),
                                client_data_json: encode(assertion.response.clientDataJSON),
                                authenticator_data: encode(assertion.response.authenticatorData),
                                signature: encode(assertion.response.signature),
                                user_handle: assertion.response.userHandle ? encode(assertion.response.userHandle) : '',
                            });

                            if (!loginResult) {
                                this.localMessage = '';
                                this.messageTone = 'error';
                                this.canRetry = true;
                            }

                            return;
                        } catch (error) {
                            if (!isCredentialManagerUnknownError(error)) {
                                throw error;
                            }

                            await reportClientFailure(
                                $wire,
                                issue.challengeId,
                                'client_credential_manager_unknown',
                            );

                            if (attempt === 0) {
                                this.localMessage = 'Pengelola passkey belum merespons. Sistem menyiapkan satu percobaan aman...';
                                await recoverCredentialManager();
                                continue;
                            }

                            throw error;
                        }
                    }
                } catch (error) {
                    this.localMessage = friendlyError(error);
                    this.messageTone = 'error';
                    this.canRetry = true;
                    if (error?.name === 'NotAllowedError' || error?.name === 'AbortError') {
                        this.messageTone = 'info';
                        await $wire.cancelPasskeyLogin();
                    }
                } finally {
                    this.isProcessing = false;
                }
            },
        };
    };

    window.adminPasskeySettingsBridge = function () {
        return {
            isProcessing: false,
            localMessage: '',
            async register($wire) {
                if (this.isProcessing) return;
                this.isProcessing = true;
                this.localMessage = supported() ? 'Menyiapkan pendaftaran perangkat...' : 'Browser atau perangkat ini belum mendukung passkey.';

                try {
                    const issue = await $wire.beginPasskeyRegistration(supported());
                    if (!supported() || issue?.status !== 'issued' || !issue.publicKeyOptions) {
                        this.localMessage = issue?.message || this.localMessage;
                        return;
                    }

                    this.localMessage = 'Sentuh sensor sidik jari atau ikuti pengunci layar...';
                    const credential = await navigator.credentials.create({ publicKey: normalizeOptions(issue.publicKeyOptions) });
                    if (!credential) throw new DOMException('Pendaftaran dibatalkan.', 'AbortError');

                    this.localMessage = 'Memverifikasi perangkat dengan server...';
                    await $wire.completePasskeyRegistration(issue.challengeId, {
                        credential_id: encode(credential.rawId),
                        client_data_json: encode(credential.response.clientDataJSON),
                        attestation_object: encode(credential.response.attestationObject),
                        transports: typeof credential.response.getTransports === 'function'
                            ? credential.response.getTransports()
                            : [],
                    });
                    this.localMessage = 'Passkey berhasil diaktifkan. Perangkat siap digunakan untuk login.';
                } catch (error) {
                    this.localMessage = friendlyError(error);
                } finally {
                    this.isProcessing = false;
                }
            },
        };
    };
})();
