<div
    data-pwa-install-root
    data-dismiss-key="admin-login-install-dismissed-v3"
    data-installed-key="admin-login-install-installed-v1"
    class="admin-login-install mb-4 hidden"
    hidden
>
    <div class="admin-login-install__body">
        <div class="admin-login-install__icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                <rect x="4" y="3" width="16" height="18" rx="2.5" />
                <path d="M9 7h6M10 17h4" />
                <path d="M12 10v4m0 0 2-2m-2 2-2-2" />
            </svg>
        </div>
        <div class="admin-login-install__content">
            <strong>Gunakan aplikasi SMA AFBS</strong>
            <div class="admin-login-install__text">
                Install untuk akses admin yang lebih cepat dan nyaman.
            </div>
        </div>

        <div class="admin-login-install__actions">
            <button type="button" data-pwa-install-trigger class="admin-login-install__button">
                <span>Install App</span>
            </button>
            <button type="button" data-pwa-install-close class="admin-login-install__close" aria-label="Tutup">
                <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path d="m5 5 10 10M15 5 5 15" />
                </svg>
                <span class="sr-only">Tutup</span>
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

            window.adminLoginPwaInstall = { dismiss };
            root.querySelector('[data-pwa-install-close]')?.addEventListener('click', dismiss);

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
