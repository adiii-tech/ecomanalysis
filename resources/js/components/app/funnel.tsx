import { formatNumber, formatPercent } from '@/lib/format';
import { cn } from '@/lib/utils';

export interface FunnelStep {
    key: string;
    label: string;
    value: number;
    step_rate?: number;
    pct?: number;
}

export function Funnel({ steps, overall }: { steps: FunnelStep[]; overall?: number }) {
    if (steps.length === 0) {
        return <p className="py-6 text-center text-xs text-muted-foreground">No funnel data in this period.</p>;
    }

    const top = Math.max(steps[0]?.value ?? 1, 1);

    return (
        <div className="space-y-2">
            {steps.map((step, index) => {
                const width = (step.value / top) * 100;
                const rate = step.step_rate ?? step.pct;
                const isWeak = index > 0 && rate !== undefined && rate < 40;

                return (
                    <div key={step.key} className="space-y-1">
                        <div className="flex items-baseline justify-between gap-2 text-xs">
                            <span className="font-medium">{step.label}</span>
                            <span className="tnum text-muted-foreground">
                                {formatNumber(step.value)}
                                {rate !== undefined && index > 0 && (
                                    <span className={cn('ml-1.5 font-medium', isWeak ? 'text-warn' : 'text-muted-foreground')}>
                                        {formatPercent(rate)}
                                    </span>
                                )}
                            </span>
                        </div>
                        <div className="h-6 overflow-hidden rounded-md bg-muted">
                            <div
                                className="h-full rounded-md transition-all"
                                style={{
                                    width: `${Math.max(width, 2)}%`,
                                    background: `color-mix(in oklch, var(--chart-1) ${100 - index * 14}%, var(--chart-2))`,
                                }}
                            />
                        </div>
                    </div>
                );
            })}

            {overall !== undefined && (
                <p className="pt-1 text-xs text-muted-foreground">
                    Overall conversion <span className="font-semibold tnum text-foreground">{formatPercent(overall, 2)}</span>
                </p>
            )}
        </div>
    );
}
