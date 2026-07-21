const setupPublicMobileNavigation = () => {
    const navigation = document.getElementById('public-navigation');
    const menu = document.getElementById('public-mobile-menu');
    const toggle = document.getElementById('public-mobile-menu-toggle');
    const closeButton = document.getElementById('public-mobile-menu-close');
    const overlay = document.getElementById('public-mobile-menu-overlay');

    if (!menu || !toggle || !overlay) {
        return;
    }

    let isOpen = false;
    let focusTimer = null;

    const focusWithoutScrolling = (element) => {
        if (!element) {
            return;
        }

        try {
            element.focus({ preventScroll: true });
        } catch {
            element.focus();
        }
    };

    const setMenuOpen = (open, { restoreFocus = false } = {}) => {
        const nextOpen = Boolean(open);
        const stateChanged = isOpen !== nextOpen;
        isOpen = nextOpen;

        if (focusTimer !== null) {
            window.clearTimeout(focusTimer);
            focusTimer = null;
        }

        menu.classList.toggle('is-open', nextOpen);
        overlay.classList.toggle('is-open', nextOpen);
        toggle.classList.toggle('is-open', nextOpen);
        toggle.setAttribute('aria-expanded', String(nextOpen));
        toggle.setAttribute('aria-label', nextOpen ? 'Tutup menu navigasi' : 'Buka menu navigasi');
        menu.setAttribute('aria-hidden', String(!nextOpen));
        overlay.setAttribute('aria-hidden', String(!nextOpen));
        menu.inert = !nextOpen;
        document.documentElement.classList.toggle('public-mobile-menu-open', nextOpen);

        if (nextOpen && stateChanged) {
            focusTimer = window.setTimeout(() => {
                focusTimer = null;

                if (isOpen) {
                    focusWithoutScrolling(closeButton);
                }
            }, 280);
        } else if (!nextOpen && restoreFocus) {
            window.requestAnimationFrame(() => focusWithoutScrolling(toggle));
        }
    };

    toggle.addEventListener('click', () => setMenuOpen(!isOpen, { restoreFocus: isOpen }));
    closeButton?.addEventListener('click', () => setMenuOpen(false, { restoreFocus: true }));
    overlay.addEventListener('click', () => setMenuOpen(false, { restoreFocus: true }));
    menu.querySelectorAll('a[href]').forEach((link) => {
        link.addEventListener('click', () => setMenuOpen(false));
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && isOpen) {
            setMenuOpen(false, { restoreFocus: true });
        }
    });

    window.addEventListener('resize', () => {
        if (window.innerWidth >= 768 && isOpen) {
            setMenuOpen(false);
        }
    });

    const syncNavigationShadow = () => {
        navigation?.classList.toggle('is-scrolled', window.scrollY > 12);
    };

    window.addEventListener('scroll', syncNavigationShadow, { passive: true });
    syncNavigationShadow();
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', setupPublicMobileNavigation, { once: true });
} else {
    setupPublicMobileNavigation();
}
