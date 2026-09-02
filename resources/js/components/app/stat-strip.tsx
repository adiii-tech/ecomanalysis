import { Info } from 'lucide-react';
import { Card } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { DeltaChip } from '@/components/app/delta-chip';
import { formatMetric, type MetricFormat } from '@/lib/format';
import { cn } from '@/lib/utils';

export interface Stat {
    label: string;
    value: number;
    prev_value: number | null;
    format: MetricFormat;
    higher_is_better?: boolean;
    badge?: string;
    tooltip?: string;
}

function deltaOf(stat: Stat): { pct: number | null; direction: 'up' | 'down' | 'flat'; isGood: boolean | null } {
    const prev = stat.prev_value;

    if (prev === null || prev === undefined) {
        return { pct: null, direction: 'flat', isGood: null };
    }

    if (Math.abs(prev) < 1e-7) {
        return { pct: Math.abs(stat.value) < 1e-7 ? 0 : null, direction: 'flat', isGood: null };
    }

    const pct = Math.round(((stat.value - prev) / Math.abs(prev)) * 10000) / 100;
    const direction = pct > 0.05 ? 'up' : pct < -0.05 ? 'down' : 'flat';
    const higherIsBetter = stat.higher_is_better ?? true;

    return {
        pct,
        direction,
        isGood: direction === 'flat' ? null : higherIsBetter ? direction === 'up' : direction === 'down',
    };
}

/**
 * KPI strip for endpoints that return a keyed record of stats rather than the
 * full Metric shape with sparklines.
 */
export function StatStrip({
    stats,
    loading,
    columns = 4,
}: {
    stats: Record<string, unknown> | null;
    loading: boolean;
    columns?: number;
}) {
    const entries = stats
        ? Object.entries(stats).filter(
              (entry): entry is [string, Stat] =>
                  typeof entry[1] === 'object' && entry[1] !== null && 'value' in (entry[1] as Record<string, unknown>),
          )
        : [];

    const gridClass = cn(
        'grid gap-3',
        columns >= 6 ? 'grid-cols-2 md:grid-cols-3 xl:grid-cols-6' : columns === 5 ? 'grid-cols-2 md:grid-cols-3 xl:grid-cols-5' : 'grid-cols-2 lg:grid-cols-4',
    );

    if (loading || entries.length === 0) {
        return (
            <div className={gridClass}>
                {Array.from({ length: columns }).map((_, index) => (
                    <Card key={index} className="p-4">
                        <Skeleton className="h-3 w-24" />
                        <Skeleton className="mt-3 h-7 w-28" />
                        <Skeleton className="mt-2 h-4 w-16" />
                    </Card>
                ))}
            </div>
        );
    }

    return (
        <div className={gridClass}>
            {entries.map(([key, stat]) => {
                const delta = deltaOf(stat);

                return (
                    <Card key={key} className="p-4">
                        <div className="flex items-start justify-between gap-2">
                            <span className="text-[11px] font-medium uppercase tracking-wide text-muted-foreground">{stat.label}</span>
                            {stat.tooltip && (
                                <Tooltip>
                                    <TooltipTrigger asChild>
                                        <button type="button" className="text-muted-foreground/60 hover:text-muted-foreground" aria-label={`About ${stat.label}`}>
                                            <Info className="size-3" />
                                        </button>
                                    </TooltipTrigger>
                                    <TooltipContent>{stat.tooltip}</TooltipContent>
                                </Tooltip>
                            )}
                        </div>
                        <p className="mt-2 text-xl font-semibold leading-tight tracking-tight tnum">
                            {formatMetric(stat.value, stat.format)}
                        </p>
                        <div className="mt-1.5 flex flex-wrap items-center gap-1.5">
                            <DeltaChip deltaPct={delta.pct} isGood={delta.isGood} direction={delta.direction} />
                            {stat.badge && <span className="text-[10px] text-muted-foreground">{stat.badge}</span>}
                        </div>
                    </Card>
                );
            })}
        </div>
    );
}
