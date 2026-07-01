const SW_PATH = '/service-worker.js';

const isStandaloneDisplay = () => {
    return window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
};

const registerServiceWorker = () => {
    if (!('serviceWorker' in navigator) || !window.isSecureContext) {
        return;
    }

    window.addEventListener('load', () => {
        navigator.serviceWorker
            .register(SW_PATH, { scope: '/' })
            .then((registration) => registration.update())
            .catch(() => {
                // Ignore registration failures to keep unsupported/blocked browsers graceful.
            });
    });
};

const setupInstallPrompt = () => {
    const roots = Array.from(document.querySelectorAll('[data-pwa-install-root]'));

    if (!roots.length) {
        return;
    }

    let deferredPrompt = null;

    const hideInstallUi = () => {
        roots.forEach((root) => root.classList.add('hidden'));
    };

    const showInstallUi = () => {
        roots.forEach((root) => root.classList.remove('hidden'));
    };

    roots.forEach((root) => {
        const trigger = root.querySelector('[data-pwa-install-trigger]');

        if (!trigger) {
            return;
        }

        trigger.addEventListener('click', async () => {
            if (!deferredPrompt) {
                return;
            }

            deferredPrompt.prompt();
            await deferredPrompt.userChoice;
            deferredPrompt = null;
            hideInstallUi();
        });
    });

    if (isStandaloneDisplay()) {
        hideInstallUi();

        return;
    }

    window.addEventListener('beforeinstallprompt', (event) => {
        event.preventDefault();
        deferredPrompt = event;
        showInstallUi();
    });

    window.addEventListener('appinstalled', () => {
        deferredPrompt = null;
        hideInstallUi();
    });
};

const bootPwaShell = () => {
    const hasInstallHooks = Boolean(document.querySelector('[data-pwa-install-root]'));
    const isPublicShell = document.documentElement.dataset.pwaShell === 'public';

    if (!hasInstallHooks && !isPublicShell) {
        return;
    }

    registerServiceWorker();
    setupInstallPrompt();
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bootPwaShell, { once: true });
} else {
    bootPwaShell();
}
