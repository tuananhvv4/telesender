(function () {
    const root = document.documentElement;
    const toggles = document.querySelectorAll('[data-theme-toggle]');
    const themeKey = 'tele_sender_theme';
    const media = window.matchMedia ? window.matchMedia('(prefers-color-scheme: dark)') : null;

    if (!toggles.length) {
        return;
    }

    function getStoredTheme() {
        try {
            const stored = localStorage.getItem(themeKey);
            return stored === 'dark' || stored === 'light' ? stored : null;
        } catch (error) {
            return null;
        }
    }

    function getSystemTheme() {
        return media && media.matches ? 'dark' : 'light';
    }

    function getCurrentTheme() {
        const value = root.getAttribute('data-theme');
        return value === 'dark' || value === 'light' ? value : getStoredTheme() ?? getSystemTheme();
    }

    function nextThemeLabel(theme) {
        return theme === 'dark' ? 'Chuyển sang giao diện sáng' : 'Chuyển sang giao diện tối';
    }

    function nextThemeShortLabel(theme) {
        return theme === 'dark' ? 'Giao diện sáng' : 'Giao diện tối';
    }

    function nextThemeIcon(theme) {
        return theme === 'dark' ? 'fa-solid fa-sun' : 'fa-solid fa-moon';
    }

    function applyTheme(theme, persist) {
        root.setAttribute('data-theme', theme);

        if (persist) {
            try {
                localStorage.setItem(themeKey, theme);
            } catch (error) {
            }
        }

        syncThemeButtons(theme);
    }

    function syncThemeButtons(theme) {
        const label = nextThemeLabel(theme);
        const shortLabel = nextThemeShortLabel(theme);
        const iconClass = nextThemeIcon(theme);

        toggles.forEach((button) => {
            button.setAttribute('aria-label', label);
            button.setAttribute('title', label);
            button.setAttribute('data-theme-current', theme);

            const icon = button.querySelector('[data-theme-icon]');
            if (icon) {
                icon.className = iconClass;
            }

            const text = button.querySelector('[data-theme-label]');
            if (text) {
                text.textContent = shortLabel;
            }
        });
    }

    toggles.forEach((button) => {
        button.addEventListener('click', () => {
            const theme = getCurrentTheme() === 'dark' ? 'light' : 'dark';
            applyTheme(theme, true);
        });
    });

    if (media) {
        const syncSystemTheme = () => {
            if (getStoredTheme() === null) {
                applyTheme(getSystemTheme(), false);
            }
        };

        if (typeof media.addEventListener === 'function') {
            media.addEventListener('change', syncSystemTheme);
        } else if (typeof media.addListener === 'function') {
            media.addListener(syncSystemTheme);
        }
    }

    syncThemeButtons(getCurrentTheme());
})();

(function () {
    const reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const spotlightSelector = [
        '.card',
        '.panel',
        '.builder-block',
        '.group-card',
        '.admin-user-card',
        '.quick-card',
        '.emoji-library-card',
        '.log-card',
        '.list-item',
    ].join(',');

    function bindSpotlights(scope) {
        scope.querySelectorAll(spotlightSelector).forEach((element) => {
            if (element.dataset.spotlightBound === '1') {
                return;
            }

            element.dataset.spotlightBound = '1';
            element.classList.add('interactive-surface');
            element.addEventListener('pointermove', (event) => {
                const bounds = element.getBoundingClientRect();
                element.style.setProperty('--spotlight-x', `${event.clientX - bounds.left}px`);
                element.style.setProperty('--spotlight-y', `${event.clientY - bounds.top}px`);
            });
        });
    }

    function bindReveals(scope) {
        const elements = scope.querySelectorAll('.main-content > .stack > *, .auth-card > *');

        elements.forEach((element, index) => {
            if (element.dataset.revealBound === '1') {
                return;
            }

            element.dataset.revealBound = '1';
            element.classList.add('ui-reveal');
            element.style.setProperty('--reveal-delay', `${Math.min(index * 55, 330)}ms`);
        });

        if (reduceMotion || !('IntersectionObserver' in window)) {
            elements.forEach((element) => element.classList.add('is-visible'));
            return;
        }

        const observer = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('is-visible');
                    observer.unobserve(entry.target);
                }
            });
        }, { threshold: 0 });

        elements.forEach((element) => {
            const bounds = element.getBoundingClientRect();

            if (bounds.bottom >= 0 && bounds.top <= window.innerHeight) {
                element.classList.add('is-visible');
                return;
            }

            observer.observe(element);
        });

        // Visual effects must never leave application content permanently hidden.
        window.setTimeout(() => {
            elements.forEach((element) => {
                element.classList.add('is-visible');
                observer.unobserve(element);
            });
        }, 1200);
    }

    function initializeVisualEffects() {
        bindSpotlights(document);
        bindReveals(document);

        const observer = new MutationObserver((mutations) => {
            mutations.forEach((mutation) => {
                mutation.addedNodes.forEach((node) => {
                    if (node instanceof HTMLElement) {
                        bindSpotlights(node.matches(spotlightSelector) ? node.parentElement : node);
                    }
                });
            });
        });

        observer.observe(document.body, { childList: true, subtree: true });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initializeVisualEffects, { once: true });
    } else {
        initializeVisualEffects();
    }
})();
