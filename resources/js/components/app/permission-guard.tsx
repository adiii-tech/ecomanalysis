import type { ReactNode } from 'react';
import { usePermissions } from '@/hooks/use-permissions';

/**
 * Hides a widget entirely when the user lacks its permission — no empty shell,
 * no "restricted" placeholder. If it is not theirs to see, it is not there.
 */
export function PermissionGuard({
    permission,
    anyOf,
    children,
    fallback = null,
}: {
    permission?: string;
    anyOf?: string[];
    children: ReactNode;
    fallback?: ReactNode;
}) {
    const { can, canAny } = usePermissions();
    const allowed = permission ? can(permission) : anyOf ? canAny(...anyOf) : true;

    return allowed ? <>{children}</> : <>{fallback}</>;
}
