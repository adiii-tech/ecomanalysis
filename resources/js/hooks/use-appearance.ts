import { useCallback, useEffect, useState } from 'react';

export type Theme = 'light' | 'dark' | 'system';
export const ACCENTS = ['indigo', 'emerald', 'rose', 'amber', 'cyan', 'violet'] as const;
export type Accent = (typeof ACCENTS)[number];

function applyTheme(theme: Theme) {
    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
    document.documentElement.classList.toggle('dark', theme === 'dark' || (theme === 'system' && prefersDark));
}

function applyAccent(accent: Accent) {
    if (accent === 'indigo') {
        delete document.documentElement.dataset.accent;
    } else {
        document.documentElement.dataset.accent = accent;
    }
}

export function useAppearance(initialTheme: Theme = 'system', initialAccent: Accent = 'indigo') {
    const [theme, setThemeState] = useState<Theme>(() => (localStorage.getItem('theme') as Theme) ?? initialTheme);
    const [accent, setAccentState] = useState<Accent>(() => (localStorage.getItem('accent') as Accent) ?? initialAccent);

    useEffect(() => {
        applyTheme(theme);
        const media = window.matchMedia('(prefers-color-scheme: dark)');
        const listener = () => theme === 'system' && applyTheme('system');
        media.addEventListener('change', listener);
        return () => media.removeEventListener('change', listener);
    }, [theme]);

    useEffect(() => applyAccent(accent), [accent]);

    const setTheme = useCallback((next: Theme) => {
        localStorage.setItem('theme', next);
        setThemeState(next);
    }, []);

    const setAccent = useCallback((next: Accent) => {
        localStorage.setItem('accent', next);
        setAccentState(next);
    }, []);

    return { theme, setTheme, accent, setAccent };
}
