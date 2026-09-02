import { formatMetric, type MetricFormat } from '@/lib/format';
import { cn } from '@/lib/utils';

export interface BarListRow {
    label: string;
    value: number;
    share?: number;
    color?: string | null;
    secondary?: string;
    tone?: 'good' | 'bad' | 'neutral';
}

/**
 * A ranked horizontal bar list — the densest honest way to show "who is
 * biggest" without spending a whole chart on it.
 */
export function BarList({
    rows,
    format = 'currency',
    emptyLabel = 'No data in this period.',
}: {
    rows: BarListRow[];
    format?: MetricFormat;
    emptyLabel?: string;
}) {
    if (rows.length === 0) {
        return <p className="py-6 text-center text-xs text-muted-foreground">{emptyLabel}</p>;
    }

    const max = Math.max(...rows.map((row) => Math.abs(row.value)), 1);

    return (
        <div className="space-y-2.5">
            {rows.map((row) => {
                const width = row.share !== undefined ? row.share : (Math.abs(row.value) / max) * 100;

                return (
                    <div key={row.label} className="space-y-1">
                        <div className="flex items-baseline justify-between gap-2 text-xs">
                            <span className="flex min-w-0 items-center gap-1.5 font-medium">
                                {row.color && <span className="size-2 shrink-0 rounded-full" style={{ background: row.color }} />}
                                <span className="truncate">{row.label}</span>
                            </span>
                            <span className="shrink-0 tnum text-muted-foreground">
                                {formatMetric(row.value, format, true)}
                                {row.secondary && <span className={cn('ml-1.5', row.tone === 'bad' && 'text-bad', row.tone === 'good' && 'text-good')}>{row.secondary}</span>}
                            </span>
                        </div>
                        <div className="h-1.5 overflow-hidden rounded-full bg-muted">
                            <div
                                className="h-full rounded-full transition-all"
                                style={{ width: `${Math.max(Math.min(width, 100), 1)}%`, background: row.color ?? 'var(--chart-1)' }}
                            />
                        </div>
                    </div>
                );
            })}
        </div>
    );
}
