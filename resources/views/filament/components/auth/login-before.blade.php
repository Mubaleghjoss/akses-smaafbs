<div
    data-pwa-install-root
    data-dismiss-key="admin-login-install-dismissed-v3"
    data-installed-key="admin-login-install-installed-v1"
    class="admin-login-install mb-4 hidden"
    hidden
>
    <div class="admin-login-install__body">
        <div class="admin-login-install__content">
            <div class="admin-login-install__text">
                Install aplikasi admin di perangkat ini untuk akses yang lebih cepat.
            </div>
        </div>

        <div class="admin-login-install__actions">
            <button
                type="button"
                data-pwa-install-trigger
                class="admin-login-install__button"
            >
                Install App
            </button>
            <button
                type="button"
                data-pwa-install-close
                class="admin-login-install__close"
                aria-label="Tutup"
                onclick="if (window.adminLoginPwaInstall?.dismiss) { window.adminLoginPwaInstall.dismiss(this); } else { this.closest('[data-pwa-install-root]')?.classList.add('hidden'); this.closest('[data-pwa-install-root]')?.setAttribute('hidden', 'hidden'); try { window.localStorage.setItem('admin-login-install-dismissed-v3', '1'); } catch (_) {} }"
            >
                Tutup
            </button>
        </div>
    </div>
</div>

@once
    <script>
        (() => {
            const root = document.querySelector('[data-pwa-install-root]');
            const trigger = document.querySelector('[data-pwa-install-trigger]');
            const dismissedKey = root?.dataset.dismissKey || 'admin-login-install-dismissed-v3';
            const installedKey = root?.dataset.installedKey || 'admin-login-install-installed-v1';

            if (!root || !trigger) {
                return;
            }

            const hide = () => {
                root.classList.add('hidden');
                root.setAttribute('hidden', 'hidden');
            };

            const show = () => {
                root.classList.remove('hidden');
                root.removeAttribute('hidden');
            };

            const isStandalone = () => window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;

            const isInstalled = () => {
                try {
                    return isStandalone() || window.localStorage.getItem(installedKey) === '1';
                } catch (_) {
                    return isStandalone();
                }
            };

            const dismiss = () => {
                hide();

                try {
                    window.localStorage.setItem(dismissedKey, '1');
                } catch (_) {}
            };

            window.adminLoginPwaInstall = {
                dismiss,
            };

            if ('serviceWorker' in navigator && window.isSecureContext) {
                window.addEventListener('load', () => {
                    window.AksesPwa?.register();
                });
            }

            let deferredPrompt = null;

            if (isInstalled()) {
                hide();

                return;
            }

            window.addEventListener('beforeinstallprompt', (event) => {
                event.preventDefault();
                deferredPrompt = event;

                try {
                    if (window.localStorage.getItem(dismissedKey) !== '1') {
                        show();
                    }
                } catch (_) {
                    show();
                }
            });

            window.addEventListener('appinstalled', () => {
                deferredPrompt = null;
                hide();

                try {
                    window.localStorage.removeItem(dismissedKey);
                    window.localStorage.setItem(installedKey, '1');
                } catch (_) {}
            });

            trigger.addEventListener('click', async () => {
                if (!deferredPrompt || isInstalled()) {
                    hide();

                    return;
                }

                root.classList.add('is-loading');

                try {
                    deferredPrompt.prompt();
                    const choice = await deferredPrompt.userChoice;

                    if (choice?.outcome === 'accepted') {
                        try {
                            window.localStorage.removeItem(dismissedKey);
                            window.localStorage.setItem(installedKey, '1');
                        } catch (_) {}
                    }
                } catch (_) {
                    // Keep graceful fallback on unsupported browsers or cancelled prompts.
                } finally {
                    deferredPrompt = null;
                    root.classList.remove('is-loading');
                    hide();
                }
            });

            hide();
        })();
    </script>
@endonce
