import { Bar, BarChart, CartesianGrid, Cell, ResponsiveContainer, XAxis, YAxis } from 'recharts';
import { AXIS_PROPS, GRID_PROPS, axisCurrency } from '@/components/charts/chart-primitives';
import { formatCurrency } from '@/lib/format';

export interface WaterfallStep {
    key: string;
    label: string;
    delta: number;
    start: number;
    end: number;
    is_total?: boolean;
}

/**
 * The gross → contribution-margin chain. Floating bars show each deduction as
 * the distance it takes off, so the leak is visible rather than inferred.
 */
export function WaterfallChart({ steps, height = 300 }: { steps: WaterfallStep[]; height?: number }) {
    const data = steps.map((step) => ({
        ...step,
        range: step.is_total ? [0, step.end] : [Math.min(step.start, step.end), Math.max(step.start, step.end)],
    }));

    return (
        <ResponsiveContainer width="100%" height={height}>
            <BarChart data={data} margin={{ top: 8, right: 4, bottom: 4, left: 4 }}>
                <CartesianGrid {...GRID_PROPS} />
                <XAxis dataKey="label" {...AXIS_PROPS} interval={0} angle={-28} textAnchor="end" height={62} />
                <YAxis {...AXIS_PROPS} tickFormatter={axisCurrency} width={58} />
                <Bar dataKey="range" radius={3} isAnimationActive={false}>
                    {data.map((step) => (
                        <Cell
                            key={step.key}
                            fill={step.is_total ? 'var(--chart-1)' : step.delta >= 0 ? 'var(--good)' : 'var(--bad)'}
                            fillOpacity={step.is_total ? 1 : 0.85}
                        />
                    ))}
                </Bar>
            </BarChart>
        </ResponsiveContainer>
    );
}

export function WaterfallLegend({ steps }: { steps: WaterfallStep[] }) {
    const total = steps.find((step) => step.is_total);

    return (
        <div className="flex flex-wrap items-center justify-between gap-2 text-xs">
            <div className="flex items-center gap-3">
                <span className="flex items-center gap-1.5 text-muted-foreground">
                    <span className="size-2 rounded-full bg-good" /> adds
                </span>
                <span className="flex items-center gap-1.5 text-muted-foreground">
                    <span className="size-2 rounded-full bg-bad" /> takes away
                </span>
            </div>
            {total && (
                <span className="font-medium tnum">
                    Contribution margin <span className={total.end >= 0 ? 'text-good' : 'text-bad'}>{formatCurrency(total.end)}</span>
                </span>
            )}
        </div>
    );
}
