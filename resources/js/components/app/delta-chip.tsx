import { ArrowDownRight, ArrowRight, ArrowUpRight } from 'lucide-react';
import { cn } from '@/lib/utils';
import { formatDelta } from '@/lib/format';

/**
 * Colour encodes *goodness*, never direction — a fall in RTO is green.
 */
export function DeltaChip({
    deltaPct,
    isGood,
    direction,
    className,
    size = 'default',
}: {
    deltaPct: number | null;
    isGood: boolean | null;
    direction: 'up' | 'down' | 'flat';
    className?: string;
    size?: 'default' | 'sm';
}) {
    if (deltaPct === null || deltaPct === undefined) {
        return <span className={cn('text-xs text-muted-foreground', className)}>no prior data</span>;
    }

    const Icon = direction === 'up' ? ArrowUpRight : direction === 'down' ? ArrowDownRight : ArrowRight;

    return (
        <span
            className={cn(
                'inline-flex items-center gap-0.5 rounded-full px-1.5 py-0.5 font-medium tnum',
                size === 'sm' ? 'text-[10px]' : 'text-[11px]',
                isGood === null && 'bg-muted text-muted-foreground',
                isGood === true && 'bg-good-soft text-good',
                isGood === false && 'bg-bad-soft text-bad',
                className,
            )}
        >
            <Icon className={size === 'sm' ? 'size-2.5' : 'size-3'} />
            {formatDelta(deltaPct)}
        </span>
    );
}
