import type { ReactNode } from 'react';
import { formatCompactCurrency, formatCompactNumber, formatDate, formatMetric, type MetricFormat } from '@/lib/format';
import { cn } from '@/lib/utils';

export const CHART_COLORS = [
    'var(--chart-1)',
    'var(--chart-2)',
    'var(--chart-3)',
    'var(--chart-4)',
    'var(--chart-5)',
    'var(--chart-6)',
    'var(--chart-7)',
];

export const AXIS_PROPS = {
    tick: { fontSize: 11, fill: 'var(--muted-foreground)' },
    tickLine: false,
    axisLine: false,
} as const;

export const GRID_PROPS = {
    stroke: 'var(--border)',
    strokeDasharray: '3 3',
    vertical: false,
} as const;

export function axisCurrency(value: number): string {
    return formatCompactCurrency(value);
}

export function axisNumber(value: number): string {
    return formatCompactNumber(value);
}

export function axisPercent(value: number): string {
    return `${Math.round(value)}%`;
}

export function axisDate(value: string): string {
    return formatDate(value);
}

interface TooltipEntry {
    name?: string;
    dataKey?: string | number;
    value?: number;
    color?: string;
    payload?: Record<string, unknown>;
}

/**
 * One tooltip for every chart in the product, so a number always reads the same
 * way regardless of which widget it came from.
 */
export function ChartTooltip({
    active,
    payload,
    label,
    format = 'currency',
    labelFormatter = formatDate,
    formats,
    footer,
}: {
    active?: boolean;
    payload?: TooltipEntry[];
    label?: string;
    format?: MetricFormat;
    labelFormatter?: (label: string) => string;
    formats?: Record<string, MetricFormat>;
    footer?: (payload: TooltipEntry[]) => ReactNode;
}) {
    if (!active || !payload?.length) return null;

    return (
        <div className="rounded-lg border border-border bg-popover px-2.5 py-2 text-xs shadow-lg">
            {label !== undefined && <p className="mb-1 font-medium text-popover-foreground">{labelFormatter(String(label))}</p>}
            <div className="space-y-0.5">
                {payload.map((entry, index) => (
                    <div key={index} className="flex items-center justify-between gap-4">
                        <span className="flex items-center gap-1.5 text-muted-foreground">
                            <span className="size-2 rounded-full" style={{ background: entry.color }} />
                            {entry.name}
                        </span>
                        <span className="font-medium tnum text-popover-foreground">
                            {formatMetric(entry.value ?? 0, formats?.[String(entry.dataKey)] ?? format, true)}
                        </span>
                    </div>
                ))}
            </div>
            {footer?.(payload)}
        </div>
    );
}

export function ChartLegend({
    items,
    className,
}: {
    items: { label: string; color: string; value?: string }[];
    className?: string;
}) {
    return (
        <div className={cn('flex flex-wrap items-center gap-x-3 gap-y-1', className)}>
            {items.map((item) => (
                <span key={item.label} className="flex items-center gap-1.5 text-[11px] text-muted-foreground">
                    <span className="size-2 rounded-full" style={{ background: item.color }} />
                    {item.label}
                    {item.value && <span className="font-medium tnum text-foreground">{item.value}</span>}
                </span>
            ))}
        </div>
    );
}
