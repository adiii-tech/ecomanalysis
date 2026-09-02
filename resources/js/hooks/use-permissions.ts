import { usePage } from '@inertiajs/react';
import { useMemo } from 'react';
import type { SharedProps } from '@/types';

/**
 * The flattened permission list is shared on every Inertia response and cached
 * here; it busts whenever `permissions_version` changes server-side.
 */
export function usePermissions() {
    const { auth } = usePage<SharedProps>().props;

    return useMemo(() => {
        const set = new Set(auth.permissions ?? []);

        return {
            can: (permission: string) => set.has(permission),
            canAny: (...permissions: string[]) => permissions.some((p) => set.has(p)),
            canAll: (...permissions: string[]) => permissions.every((p) => set.has(p)),
            all: auth.permissions ?? [],
        };
    }, [auth.permissions, auth.user?.permissions_version]);
}
