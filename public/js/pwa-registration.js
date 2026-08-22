(function () {
    'use strict';

    if (window.AksesPwa) {
        return;
    }

    const workerPath = '/service-worker.js';
    const updateKey = 'akses:pwa:last-update-at';
    const updateIntervalMs = 6 * 60 * 60 * 1000;
    let registrationPromise = null;

    const storedNumber = (key) => {
        try {
            return Number(window.localStorage.getItem(key) || 0);
        } catch (_) {
            return 0;
        }
    };

    const storeNumber = (key, value) => {
        try {
            window.localStorage.setItem(key, String(value));
        } catch (_) {
            // Private browsing may reject persistent storage.
        }
    };

    const register = () => {
        if (!('serviceWorker' in navigator) || !window.isSecureContext) {
            return Promise.resolve(null);
        }

        if (registrationPromise) {
            return registrationPromise;
        }

        registrationPromise = navigator.serviceWorker.getRegistration('/')
            .then(async (existing) => {
                const registration = existing || await navigator.serviceWorker.register(workerPath, {
                    scope: '/',
                    updateViaCache: 'imports',
                });

                if (!existing) {
                    storeNumber(updateKey, Date.now());

                    return registration;
                }

                const lastUpdateAt = storedNumber(updateKey);

                if (!Number.isFinite(lastUpdateAt) || Date.now() - lastUpdateAt >= updateIntervalMs) {
                    storeNumber(updateKey, Date.now());
                    registration.update().catch(() => {
                        // The installed worker remains usable when an update check fails.
                    });
                }

                return registration;
            })
            .catch(() => null)
            .finally(() => {
                registrationPromise = null;
            });

        return registrationPromise;
    };

    window.AksesPwa = { register };
})();
