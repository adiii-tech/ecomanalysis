import { createContext, useCallback, useContext, useEffect, useMemo, useState, type ReactNode } from 'react';

export type ReturnsBasis = 'order_date' | 'return_date';

export interface FilterState {
    preset: string | null;
    from: string | null;
    to: string | null;
    channel: string;
    returns_basis: ReturnsBasis;
    payment_mode: string | null;
}

const STORAGE_KEY = 'analytics-filters';

const DEFAULTS: FilterState = {
    preset: 'last_30_days',
    from: null,
    to: null,
    channel: 'all',
    returns_basis: 'order_date',
    payment_mode: null,
};

/**
 * Filters live in the URL query first (so a view is shareable) and fall back to
 * localStorage (so a reload keeps the last window). Every analytics request
 * carries them.
 */
function readInitial(): FilterState {
    if (typeof window === 'undefined') return DEFAULTS;

    const params = new URLSearchParams(window.location.search);
    const fromUrl: Partial<FilterState> = {};

    (['preset', 'from', 'to', 'channel', 'returns_basis', 'payment_mode'] as const).forEach((key) => {
        const value = params.get(key);
        if (value) (fromUrl as Record<string, string>)[key] = value;
    });

    if (Object.keys(fromUrl).length > 0) {
        return { ...DEFAULTS, ...fromUrl } as FilterState;
    }

    try {
        const stored = window.localStorage.getItem(STORAGE_KEY);
        if (stored) return { ...DEFAULTS, ...(JSON.parse(stored) as Partial<FilterState>) };
    } catch {
        /* private mode or cleared storage — defaults are fine */
    }

    return DEFAULTS;
}

interface FilterContextValue {
    filters: FilterState;
    setFilters: (patch: Partial<FilterState>) => void;
    resetFilters: () => void;
    queryParams: Record<string, string>;
    /** Changes whenever the filters do — use as a fetch dependency key. */
    filterKey: string;
}

const FilterContext = createContext<FilterContextValue | null>(null);

export function FilterProvider({ children }: { children: ReactNode }) {
    const [filters, setState] = useState<FilterState>(readInitial);

    useEffect(() => {
        try {
            window.localStorage.setItem(STORAGE_KEY, JSON.stringify(filters));
        } catch {
            /* ignore */
        }

        const params = new URLSearchParams(window.location.search);
        Object.entries(filters).forEach(([key, value]) => {
            if (value === null || value === '' || value === DEFAULTS[key as keyof FilterState]) {
                params.delete(key);
            } else {
                params.set(key, String(value));
            }
        });

        const query = params.toString();
        window.history.replaceState({}, '', `${window.location.pathname}${query ? `?${query}` : ''}`);
    }, [filters]);

    const setFilters = useCallback((patch: Partial<FilterState>) => {
        setState((current) => {
            const next = { ...current, ...patch };
            // A custom range and a preset are mutually exclusive.
            if (patch.from || patch.to) next.preset = null;
            if (patch.preset) {
                next.from = null;
                next.to = null;
            }
            return next;
        });
    }, []);

    const resetFilters = useCallback(() => setState(DEFAULTS), []);

    const queryParams = useMemo(() => {
        const params: Record<string, string> = {};
        Object.entries(filters).forEach(([key, value]) => {
            if (value !== null && value !== '') params[key] = String(value);
        });
        return params;
    }, [filters]);

    const value = useMemo<FilterContextValue>(
        () => ({ filters, setFilters, resetFilters, queryParams, filterKey: JSON.stringify(queryParams) }),
        [filters, setFilters, resetFilters, queryParams],
    );

    return <FilterContext.Provider value={value}>{children}</FilterContext.Provider>;
}

export function useFilters(): FilterContextValue {
    const context = useContext(FilterContext);
    if (!context) throw new Error('useFilters must be used inside a <FilterProvider>');
    return context;
}
