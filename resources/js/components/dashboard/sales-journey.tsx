import { ArrowRight, Play, RotateCcw } from 'lucide-react';
import { useEffect, useState } from 'react';
import { Button } from '@/components/ui/button';
import { formatCurrency } from '@/lib/format';
import { cn } from '@/lib/utils';

export interface JourneyStep {
    step: number;
    key: string;
    title: string;
    value: number;
    narrative: string;
    is_milestone?: boolean;
}

/**
 * A guided walkthrough of the gross → net chain. It advances one step at a time
 * so an owner who has never read a P&L can follow where the money went.
 */
export function SalesJourney({ steps }: { steps: JourneyStep[] }) {
    const [current, setCurrent] = useState(0);
    const [playing, setPlaying] = useState(false);

    useEffect(() => {
        if (!playing) return;

        const timer = setTimeout(() => {
            setCurrent((value) => {
                if (value >= steps.length - 1) {
                    setPlaying(false);
                    return value;
                }
                return value + 1;
            });
        }, 2200);

        return () => clearTimeout(timer);
    }, [playing, current, steps.length]);

    if (steps.length === 0) return null;

    const step = steps[current];

    return (
        <div className="space-y-4">
            <div className="flex items-center gap-1">
                {steps.map((item, index) => (
                    <button
                        key={item.key}
                        type="button"
                        onClick={() => {
                            setPlaying(false);
                            setCurrent(index);
                        }}
                        aria-label={`Step ${item.step}: ${item.title}`}
                        className={cn(
                            'h-1 flex-1 rounded-full transition-colors',
                            index < current ? 'bg-primary/40' : index === current ? 'bg-primary' : 'bg-muted',
                        )}
                    />
                ))}
            </div>

            <div className="min-h-[132px] rounded-xl border border-border bg-accent/30 p-4">
                <p className="text-[11px] font-semibold uppercase tracking-wide text-muted-foreground">
                    Step {step.step} of {steps.length}
                </p>
                <p className="mt-1 text-sm font-semibold">{step.title}</p>
                <p
                    className={cn(
                        'mt-1.5 text-2xl font-semibold tracking-tight tnum',
                        step.is_milestone ? 'text-primary' : step.value < 0 ? 'text-bad' : 'text-foreground',
                    )}
                >
                    {formatCurrency(step.value)}
                </p>
                <p className="mt-1.5 text-xs leading-relaxed text-muted-foreground">{step.narrative}</p>
            </div>

            <div className="flex items-center justify-between gap-2">
                <Button
                    variant="ghost"
                    size="sm"
                    onClick={() => {
                        setCurrent(0);
                        setPlaying(false);
                    }}
                    className="gap-1.5 text-xs"
                    disabled={current === 0 && !playing}
                >
                    <RotateCcw className="size-3" />
                    Restart
                </Button>

                <div className="flex items-center gap-1.5">
                    <Button variant="outline" size="sm" onClick={() => setPlaying((value) => !value)} className="gap-1.5 text-xs">
                        <Play className="size-3" />
                        {playing ? 'Pause' : 'Play'}
                    </Button>
                    <Button
                        size="sm"
                        onClick={() => setCurrent((value) => Math.min(value + 1, steps.length - 1))}
                        disabled={current === steps.length - 1}
                        className="gap-1.5 text-xs"
                    >
                        Next
                        <ArrowRight className="size-3" />
                    </Button>
                </div>
            </div>
        </div>
    );
}
