import { Info, TriangleAlert } from 'lucide-react';
import type { Caveat } from '@/types';
import { cn } from '@/lib/utils';

/**
 * Data-honesty disclosure. If a number cannot be computed truthfully we print
 * this instead of fabricating one.
 */
export function CaveatNote({ caveat, className }: { caveat: Caveat | string | null | undefined; className?: string }) {
    if (!caveat) return null;

    const message = typeof caveat === 'string' ? caveat : caveat.message;
    const level = typeof caveat === 'string' ? 'info' : caveat.level;
    const Icon = level === 'warning' ? TriangleAlert : Info;

    return (
        <p
            className={cn(
                'flex items-start gap-1.5 text-[11px] leading-snug',
                level === 'warning' ? 'text-warn' : 'text-muted-foreground',
                className,
            )}
        >
            <Icon className="mt-px size-3 shrink-0" />
            <span>{message}</span>
        </p>
    );
}
