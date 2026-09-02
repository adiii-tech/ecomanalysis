import { Link } from '@inertiajs/react';
import { AlertTriangle, ChevronRight, ShieldCheck } from 'lucide-react';
import { Card } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import { formatCompactCurrency } from '@/lib/format';
import { cn } from '@/lib/utils';

export interface HealthFlag {
    key: string;
    severity: 'critical' | 'warning' | 'info';
    title: string;
    body: string;
    link: string;
    impact_amount: number | null;
}

export function HealthFlags({ flags, loading }: { flags: HealthFlag[] | null; loading: boolean }) {
    if (loading) {
        return (
            <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                {Array.from({ length: 3 }).map((_, index) => (
                    <Card key={index} className="p-4">
                        <Skeleton className="h-4 w-40" />
                        <Skeleton className="mt-2 h-3 w-full" />
                    </Card>
                ))}
            </div>
        );
    }

    if (!flags || flags.length === 0) {
        return (
            <Card className="flex items-center gap-3 border-good/25 bg-good-soft/50 p-4">
                <ShieldCheck className="size-5 shrink-0 text-good" />
                <div>
                    <p className="text-sm font-medium text-good">Nothing needs your attention right now.</p>
                    <p className="text-xs text-good/80">No margin drops, RTO spikes, stockouts or SLA breaches in this window.</p>
                </div>
            </Card>
        );
    }

    return (
        <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
            {flags.map((flag) => (
                <Link key={flag.key} href={flag.link}>
                    <Card
                        className={cn(
                            'group h-full p-4 transition-shadow hover:shadow-md',
                            flag.severity === 'critical' ? 'border-bad/30 bg-bad-soft/40' : 'border-warn/30 bg-warn-soft/40',
                        )}
                    >
                        <div className="flex items-start gap-2.5">
                            <AlertTriangle className={cn('mt-0.5 size-4 shrink-0', flag.severity === 'critical' ? 'text-bad' : 'text-warn')} />
                            <div className="min-w-0 flex-1">
                                <p className="text-sm font-semibold leading-snug">{flag.title}</p>
                                <p className="mt-0.5 text-xs leading-snug text-muted-foreground">{flag.body}</p>
                                {flag.impact_amount ? (
                                    <p className={cn('mt-1.5 text-xs font-semibold tnum', flag.severity === 'critical' ? 'text-bad' : 'text-warn')}>
                                        {formatCompactCurrency(flag.impact_amount)} at stake
                                    </p>
                                ) : null}
                            </div>
                            <ChevronRight className="size-4 shrink-0 text-muted-foreground opacity-0 transition group-hover:opacity-100" />
                        </div>
                    </Card>
                </Link>
            ))}
        </div>
    );
}
