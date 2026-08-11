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

        return error?.message || 'Passkey tidak dapat diproses. Gunakan password atau coba kembali.';
    };

    window.SmaAfbsPasskeys = { decode, encode, normalizeOptions, supported, friendlyError };

    window.adminPasskeyLoginBridge = function () {
        return {
            isProcessing: false,
            localMessage: '',
            async start($wire) {
                if (this.isProcessing) return;
                this.isProcessing = true;
                this.localMessage = supported() ? 'Memeriksa passkey pada perangkat...' : 'Browser atau koneksi ini belum mendukung passkey.';

                try {
                    const issue = await $wire.beginPasskeyLogin(supported());
                    if (!supported() || issue?.status !== 'issued' || !issue.publicKeyOptions) return;

                    this.localMessage = 'Menunggu sidik jari, PIN, atau pengunci layar...';
                    const assertion = await navigator.credentials.get({ publicKey: normalizeOptions(issue.publicKeyOptions) });

                    if (!assertion) throw new DOMException('Passkey dibatalkan.', 'AbortError');

                    this.localMessage = 'Memverifikasi passkey...';
                    await $wire.completePasskeyLogin(encode(assertion.rawId), issue.challengeId, {
                        credential_id: encode(assertion.rawId),
                        raw_id: encode(assertion.rawId),
                        client_data_json: encode(assertion.response.clientDataJSON),
                        authenticator_data: encode(assertion.response.authenticatorData),
                        signature: encode(assertion.response.signature),
                        user_handle: assertion.response.userHandle ? encode(assertion.response.userHandle) : '',
                    });
                } catch (error) {
                    this.localMessage = friendlyError(error);
                    if (error?.name === 'NotAllowedError' || error?.name === 'AbortError') {
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
