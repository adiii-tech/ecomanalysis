import { PlugZap, SearchX, TriangleAlert, PartyPopper } from 'lucide-react';
import type { ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

type Kind = 'empty' | 'connector' | 'error' | 'celebrate';

const ICONS = { empty: SearchX, connector: PlugZap, error: TriangleAlert, celebrate: PartyPopper };

export function EmptyState({
    kind = 'empty',
    title,
    description,
    action,
    className,
    compact = false,
}: {
    kind?: Kind;
    title: string;
    description?: string;
    action?: ReactNode;
    className?: string;
    compact?: boolean;
}) {
    const Icon = ICONS[kind];

    return (
        <div className={cn('flex flex-col items-center justify-center gap-2 text-center', compact ? 'py-6' : 'py-12', className)}>
            <span
                className={cn(
                    'flex size-9 items-center justify-center rounded-full',
                    kind === 'error' ? 'bg-bad-soft text-bad' : kind === 'celebrate' ? 'bg-good-soft text-good' : 'bg-muted text-muted-foreground',
                )}
            >
                <Icon className="size-4" />
            </span>
            <p className="text-sm font-medium">{title}</p>
            {description && <p className="max-w-sm text-xs text-muted-foreground">{description}</p>}
            {action}
        </div>
    );
}

export function ConnectorEmptyState({ connector, what }: { connector: string; what: string }) {
    return (
        <EmptyState
            kind="connector"
            title={`Connect ${connector} to see this`}
            description={what}
            action={
                <Button size="sm" variant="outline" asChild className="mt-1">
                    <a href="/connectors">Go to connectors</a>
                </Button>
            }
        />
    );
}

export function WidgetError({ message, onRetry }: { message: string; onRetry?: () => void }) {
    return (
        <EmptyState
            kind="error"
            compact
            title="Could not load this widget"
            description={message}
            action={
                onRetry ? (
                    <Button size="sm" variant="outline" onClick={onRetry} className="mt-1">
                        Try again
                    </Button>
                ) : undefined
            }
        />
    );
}
