import { Info } from 'lucide-react';
import { Area, AreaChart, ResponsiveContainer } from 'recharts';
import { Card } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { DeltaChip } from '@/components/app/delta-chip';
import { CaveatNote } from '@/components/app/caveat-note';
import { formatMetric } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { Metric } from '@/types';

export function KpiCard({
    metric,
    onDrilldown,
    className,
}: {
    metric: Metric;
    onDrilldown?: (metric: Metric) => void;
    className?: string;
}) {
    const strokeColor =
        metric.is_good === null ? 'var(--chart-7)' : metric.is_good ? 'var(--good)' : 'var(--bad)';
    const clickable = Boolean(onDrilldown && metric.drilldown);

    return (
        <Card
            className={cn(
                'group relative flex flex-col justify-between overflow-hidden p-4 transition-shadow',
                clickable && 'cursor-pointer hover:shadow-md',
                className,
            )}
            onClick={clickable ? () => onDrilldown?.(metric) : undefined}
            role={clickable ? 'button' : undefined}
            tabIndex={clickable ? 0 : undefined}
            onKeyDown={
                clickable
                    ? (event) => {
                          if (event.key === 'Enter' || event.key === ' ') {
                              event.preventDefault();
                              onDrilldown?.(metric);
                          }
                      }
                    : undefined
            }
        >
            <div className="flex items-start justify-between gap-2">
                <div className="flex items-center gap-1">
                    <span className="text-[11px] font-medium uppercase tracking-wide text-muted-foreground">{metric.label}</span>
                    {metric.tooltip && (
                        <Tooltip>
                            <TooltipTrigger asChild>
                                <button type="button" className="text-muted-foreground/60 transition hover:text-muted-foreground" aria-label={`About ${metric.label}`}>
                                    <Info className="size-3" />
                                </button>
                            </TooltipTrigger>
                            <TooltipContent>{metric.tooltip}</TooltipContent>
                        </Tooltip>
                    )}
                </div>
                {metric.badge && (
                    <span className="rounded-full bg-muted px-1.5 py-0.5 text-[10px] font-medium text-muted-foreground tnum">
                        {metric.badge}
                    </span>
                )}
            </div>

            <div className="mt-2">
                <p className="text-xl font-semibold leading-tight tracking-tight tnum">
                    {formatMetric(metric.value, metric.format)}
                </p>
                <div className="mt-1.5 flex items-center gap-1.5">
                    <DeltaChip deltaPct={metric.delta_pct} isGood={metric.is_good} direction={metric.direction} />
                    <span className="text-[10px] text-muted-foreground">vs prev</span>
                </div>
            </div>

            {/*
              * The sparkline gets a band of its own under the number. Six KPI
              * cards across a row leaves ~180px each, and drawn behind the value
              * the line cut straight through a lakh-scale rupee figure — the
              * number is the point of the card, so nothing crosses it.
              */}
            {metric.sparkline.length > 1 && (
                <div className="pointer-events-none mt-2 h-8">
                    <ResponsiveContainer width="100%" height="100%">
                        <AreaChart data={metric.sparkline} margin={{ top: 2, right: 0, bottom: 0, left: 0 }}>
                            <defs>
                                <linearGradient id={`spark-${metric.key}`} x1="0" y1="0" x2="0" y2="1">
                                    <stop offset="0%" stopColor={strokeColor} stopOpacity={0.35} />
                                    <stop offset="100%" stopColor={strokeColor} stopOpacity={0} />
                                </linearGradient>
                            </defs>
                            <Area
                                type="monotone"
                                dataKey="value"
                                stroke={strokeColor}
                                strokeWidth={1.5}
                                fill={`url(#spark-${metric.key})`}
                                isAnimationActive={false}
                                dot={false}
                            />
                        </AreaChart>
                    </ResponsiveContainer>
                </div>
            )}

            {metric.caveat && <CaveatNote caveat={metric.caveat} className="mt-2" />}
        </Card>
    );
}

export function KpiCardSkeleton() {
    return (
        <Card className="p-4">
            <Skeleton className="h-3 w-24" />
            <div className="mt-3 space-y-2">
                <Skeleton className="h-7 w-32" />
                <Skeleton className="h-4 w-20" />
            </div>
        </Card>
    );
}

export function KpiStrip({
    metrics,
    loading,
    columns = 6,
    onDrilldown,
}: {
    metrics: Metric[] | null;
    loading: boolean;
    columns?: number;
    onDrilldown?: (metric: Metric) => void;
}) {
    const gridClass = cn(
        'grid gap-3',
        columns >= 6 ? 'grid-cols-2 md:grid-cols-3 xl:grid-cols-6' : columns === 5 ? 'grid-cols-2 md:grid-cols-3 xl:grid-cols-5' : 'grid-cols-2 lg:grid-cols-4',
    );

    if (loading || !metrics) {
        return (
            <div className={gridClass}>
                {Array.from({ length: columns }).map((_, index) => (
                    <KpiCardSkeleton key={index} />
                ))}
            </div>
        );
    }

    return (
        <div className={gridClass}>
            {metrics.map((metric) => (
                <KpiCard key={metric.key} metric={metric} onDrilldown={onDrilldown} />
            ))}
        </div>
    );
}
