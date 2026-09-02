import { AlertTriangle, CheckCircle2, Info, Minus, TrendingDown, TrendingUp } from 'lucide-react';
import type { Verdict } from '@/types';
import { cn } from '@/lib/utils';
import { formatCompactCurrency } from '@/lib/format';

const STYLES: Record<Verdict['status'], { wrap: string; icon: typeof Info; label: string }> = {
    scale: { wrap: 'bg-good-soft text-good', icon: TrendingUp, label: 'Scale' },
    good: { wrap: 'bg-good-soft text-good', icon: CheckCircle2, label: 'Good' },
    hold: { wrap: 'bg-warn-soft text-warn', icon: Minus, label: 'Hold' },
    watch: { wrap: 'bg-warn-soft text-warn', icon: AlertTriangle, label: 'Watch' },
    cut: { wrap: 'bg-bad-soft text-bad', icon: TrendingDown, label: 'Cut' },
    bad: { wrap: 'bg-bad-soft text-bad', icon: AlertTriangle, label: 'Act now' },
    neutral: { wrap: 'bg-muted text-muted-foreground', icon: Info, label: 'Note' },
};

/**
 * Every widget ends in a judgement, not just a number.
 */
export function VerdictNote({ verdict, className }: { verdict: Verdict | null | undefined; className?: string }) {
    if (!verdict) return null;

    const style = STYLES[verdict.status] ?? STYLES.neutral;
    const Icon = style.icon;

    return (
        <div className={cn('flex items-start gap-2.5 rounded-lg px-3 py-2.5', style.wrap, className)}>
            <Icon className="mt-px size-4 shrink-0" />
            <div className="min-w-0 space-y-0.5">
                <p className="text-xs font-semibold leading-snug">{verdict.headline}</p>
                {verdict.detail && <p className="text-[11px] leading-snug opacity-90">{verdict.detail}</p>}
                {verdict.action && (
                    <p className="text-[11px] font-medium leading-snug opacity-95">→ {verdict.action}</p>
                )}
                {verdict.impact_paise ? (
                    <p className="text-[11px] font-semibold leading-snug tnum opacity-95">
                        Estimated impact: {formatCompactCurrency(verdict.impact_paise)}
                    </p>
                ) : null}
            </div>
        </div>
    );
}

export function VerdictBadge({ verdict }: { verdict: Verdict | null | undefined }) {
    if (!verdict) return null;
    const style = STYLES[verdict.status] ?? STYLES.neutral;

    return (
        <span className={cn('inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-semibold', style.wrap)}>
            {style.label}
        </span>
    );
}
