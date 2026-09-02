import { Head } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { TrendingDown, TrendingUp } from 'lucide-react';

const CHAIN = [
    { label: 'Gross Sales', tone: 'muted' },
    { label: '− Discounts', tone: 'down' },
    { label: '− Cancelled', tone: 'down' },
    { label: '= Invoiced', tone: 'mid' },
    { label: '− Returns & RTO', tone: 'down' },
    { label: '= Net Sales', tone: 'mid' },
    { label: '− COGS, fees, logistics', tone: 'down' },
    { label: '= Contribution Margin', tone: 'up' },
];

export function AuthLayout({ title, description, children }: { title: string; description?: string; children: ReactNode }) {
    return (
        <div className="grid min-h-screen lg:grid-cols-2">
            <Head title={title} />

            <div className="flex items-center justify-center px-6 py-12">
                <div className="w-full max-w-sm space-y-6">
                    <div className="space-y-1.5">
                        <div className="mb-6 flex size-9 items-center justify-center rounded-xl bg-primary text-primary-foreground">
                            <TrendingUp className="size-5" />
                        </div>
                        <h1 className="text-xl font-semibold tracking-tight">{title}</h1>
                        {description && <p className="text-sm text-muted-foreground">{description}</p>}
                    </div>
                    {children}
                </div>
            </div>

            <div className="relative hidden items-center justify-center overflow-hidden bg-sidebar px-12 lg:flex">
                <div className="relative z-10 max-w-sm space-y-6">
                    <p className="text-2xl font-semibold leading-snug tracking-tight">
                        Am I actually making money?
                    </p>
                    <p className="text-sm leading-relaxed text-muted-foreground">
                        Every number here resolves down the chain — no gross revenue on its own, no vanity metric
                        without the cost sitting next to it.
                    </p>

                    <div className="space-y-1.5">
                        {CHAIN.map((step) => (
                            <div
                                key={step.label}
                                className="flex items-center gap-2 rounded-lg border border-sidebar-border bg-card/60 px-3 py-2 text-xs"
                            >
                                {step.tone === 'down' && <TrendingDown className="size-3.5 text-bad" />}
                                {step.tone === 'up' && <TrendingUp className="size-3.5 text-good" />}
                                <span
                                    className={
                                        step.tone === 'up'
                                            ? 'font-semibold text-good'
                                            : step.tone === 'mid'
                                              ? 'font-medium'
                                              : 'text-muted-foreground'
                                    }
                                >
                                    {step.label}
                                </span>
                            </div>
                        ))}
                    </div>
                </div>

                <div className="pointer-events-none absolute -right-24 top-1/4 size-96 rounded-full bg-primary/10 blur-3xl" />
                <div className="pointer-events-none absolute -bottom-24 -left-16 size-80 rounded-full bg-chart-3/10 blur-3xl" />
            </div>
        </div>
    );
}
