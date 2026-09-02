import { useEffect, useRef, useState } from 'react';
import { ApiError, apiGet } from '@/lib/api';
import { useFilters } from '@/hooks/use-filters';
import type { ApiMeta } from '@/types';

export interface WidgetState<T> {
    data: T | null;
    meta: ApiMeta | null;
    loading: boolean;
    error: string | null;
    forbidden: boolean;
    reload: () => void;
}

/**
 * Fetches one widget's payload with the current global filters attached.
 * A 403 is not an error state — the widget simply is not visible to this user,
 * so it renders nothing rather than an empty shell.
 */
export function useWidget<T>(path: string, extraParams: Record<string, unknown> = {}, enabled = true): WidgetState<T> {
    const { queryParams, filterKey } = useFilters();
    const [data, setData] = useState<T | null>(null);
    const [meta, setMeta] = useState<ApiMeta | null>(null);
    const [loading, setLoading] = useState(enabled);
    const [error, setError] = useState<string | null>(null);
    const [forbidden, setForbidden] = useState(false);
    const [nonce, setNonce] = useState(0);
    const extraKey = JSON.stringify(extraParams);
    const controllerRef = useRef<AbortController | null>(null);

    useEffect(() => {
        if (!enabled) {
            setLoading(false);
            return;
        }

        controllerRef.current?.abort();
        const controller = new AbortController();
        controllerRef.current = controller;

        setLoading(true);
        setError(null);

        apiGet<T>(path, { ...queryParams, ...extraParams }, controller.signal)
            .then((body) => {
                setData(body.data);
                setMeta(body.meta);
                setForbidden(false);
            })
            .catch((err: unknown) => {
                if (controller.signal.aborted) return;
                if (err instanceof ApiError && err.status === 403) {
                    setForbidden(true);
                    return;
                }
                setError(err instanceof Error ? err.message : 'Something went wrong.');
            })
            .finally(() => {
                if (!controller.signal.aborted) setLoading(false);
            });

        return () => controller.abort();
    }, [path, filterKey, extraKey, enabled, nonce]);

    return { data, meta, loading, error, forbidden, reload: () => setNonce((n) => n + 1) };
}
