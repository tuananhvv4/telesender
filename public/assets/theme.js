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
