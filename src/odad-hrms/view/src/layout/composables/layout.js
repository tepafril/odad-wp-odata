import { computed, reactive } from 'vue';

const STORAGE_KEY_DARK = 'odad-attendance-dark-theme';
const STORAGE_KEY_LAYOUT = 'odad-attendance-layout-config';

function getStoredDarkTheme() {
    try {
        const stored = localStorage.getItem(STORAGE_KEY_DARK);
        return stored === 'true';
    } catch {
        return false;
    }
}

function setStoredDarkTheme(value) {
    try {
        localStorage.setItem(STORAGE_KEY_DARK, String(value));
    } catch (_) {
        // ignore
    }
}

function getStoredLayoutConfig() {
    try {
        const raw = localStorage.getItem(STORAGE_KEY_LAYOUT);
        if (!raw) return null;
        const parsed = JSON.parse(raw);
        return {
            preset: 'Lara',
            primary: typeof parsed.primary === 'string' ? parsed.primary : 'emerald',
            surface: parsed.surface !== undefined && parsed.surface !== null ? parsed.surface : null
        };
    } catch {
        return null;
    }
}

function persistLayoutConfig() {
    try {
        localStorage.setItem(
            STORAGE_KEY_LAYOUT,
            JSON.stringify({
                preset: layoutConfig.preset,
                primary: layoutConfig.primary,
                surface: layoutConfig.surface
            })
        );
    } catch (_) {
        // ignore
    }
}

const storedLayout = getStoredLayoutConfig();
const layoutConfig = reactive({
    preset: 'Lara',
    primary: storedLayout?.primary ?? 'emerald',
    surface: storedLayout?.surface ?? null,
    darkTheme: getStoredDarkTheme(),
    menuMode: 'static'
});

// Apply saved theme to document on load (avoids flash)
if (typeof document !== 'undefined') {
    if (layoutConfig.darkTheme) {
        document.documentElement.classList.add('app-dark');
    }
}

const layoutState = reactive({
    staticMenuInactive: false,
    overlayMenuActive: false,
    profileSidebarVisible: false,
    configSidebarVisible: false,
    sidebarExpanded: false,
    menuHoverActive: false,
    activeMenuItem: null,
    activePath: null
});

export function useLayout() {
    const toggleDarkMode = () => {
        if (!document.startViewTransition) {
            executeDarkModeToggle();

            return;
        }

        document.startViewTransition(() => executeDarkModeToggle(event));
    };

    const executeDarkModeToggle = () => {
        layoutConfig.darkTheme = !layoutConfig.darkTheme;
        document.documentElement.classList.toggle('app-dark');
        setStoredDarkTheme(layoutConfig.darkTheme);
    };

    const toggleMenu = () => {
        if (isDesktop()) {
            if (layoutConfig.menuMode === 'static') {
                layoutState.staticMenuInactive = !layoutState.staticMenuInactive;
            }

            if (layoutConfig.menuMode === 'overlay') {
                layoutState.overlayMenuActive = !layoutState.overlayMenuActive;
            }
        } else {
            layoutState.mobileMenuActive = !layoutState.mobileMenuActive;
        }
    };

    const toggleConfigSidebar = () => {
        layoutState.configSidebarVisible = !layoutState.configSidebarVisible;
    };

    const hideMobileMenu = () => {
        layoutState.mobileMenuActive = false;
    };

    const changeMenuMode = (event) => {
        layoutConfig.menuMode = event.value;
        layoutState.staticMenuInactive = false;
        layoutState.mobileMenuActive = false;
        layoutState.sidebarExpanded = false;
        layoutState.menuHoverActive = false;
        layoutState.anchored = false;
    };

    const isDarkTheme = computed(() => layoutConfig.darkTheme);
    const isDesktop = () => window.innerWidth > 991;

    const hasOpenOverlay = computed(() => layoutState.overlayMenuActive);

    return {
        layoutConfig,
        layoutState,
        isDarkTheme,
        toggleDarkMode,
        toggleConfigSidebar,
        toggleMenu,
        hideMobileMenu,
        changeMenuMode,
        isDesktop,
        hasOpenOverlay,
        persistLayoutConfig
    };
}
