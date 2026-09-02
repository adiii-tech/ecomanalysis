import { usePage } from '@inertiajs/react';
import { RefreshCw } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { cn } from '@/lib/utils';
import type { SharedProps } from '@/types';

const DOT_CLASS: Record<string, string> = {
    green: 'bg-good',
    amber: 'bg-warn',
    red: 'bg-bad',
    grey: 'bg-muted-foreground',
    blue: 'bg-primary animate-pulse',
};

export function SyncIndicator() {
    const { syncHealth } = usePage<SharedProps>().props;

    if (!syncHealth) return null;

    return (
        <Popover>
            <PopoverTrigger asChild>
                <Button variant="ghost" size="sm" className="gap-1.5 px-2 text-xs font-normal text-muted-foreground">
                    <span className={cn('size-2 rounded-full', DOT_CLASS[syncHealth.status])} />
                    <span className="hidden sm:inline">{syncHealth.label}</span>
                </Button>
            </PopoverTrigger>
            <PopoverContent align="end" className="w-80 p-0">
                <div className="border-b border-border px-3 py-2.5">
                    <p className="text-xs font-semibold">Connector health</p>
                    <p className="text-[11px] text-muted-foreground">{syncHealth.label}</p>
                </div>

                <div className="max-h-80 overflow-auto scrollbar-thin">
                    {syncHealth.connectors.length === 0 ? (
                        <div className="px-3 py-6 text-center">
                            <p className="text-xs text-muted-foreground">No connectors yet.</p>
                            <Button size="sm" variant="outline" asChild className="mt-2">
                                <a href="/connectors">Connect a source</a>
                            </Button>
                        </div>
                    ) : (
                        syncHealth.connectors.map((connector) => (
                            <div key={connector.id} className="flex items-start gap-2.5 border-b border-border/60 px-3 py-2.5 last:border-0">
                                <span className={cn('mt-1.5 size-2 shrink-0 rounded-full', DOT_CLASS[connector.dot])} />
                                <div className="min-w-0 flex-1">
                                    <div className="flex items-baseline justify-between gap-2">
                                        <p className="truncate text-xs font-medium">{connector.label}</p>
                                        <span className="shrink-0 text-[10px] text-muted-foreground">
                                            {connector.last_synced_human ?? 'never synced'}
                                        </span>
                                    </div>
                                    {connector.account_label && (
                                        <p className="truncate text-[11px] text-muted-foreground">{connector.account_label}</p>
                                    )}
                                    {connector.last_error && (
                                        <p className="mt-0.5 line-clamp-2 text-[11px] text-bad">{connector.last_error}</p>
                                    )}
                                </div>
                            </div>
                        ))
                    )}
                </div>

                <div className="border-t border-border px-3 py-2">
                    <Button variant="ghost" size="sm" asChild className="h-7 w-full justify-start gap-1.5 text-xs">
                        <a href="/connectors">
                            <RefreshCw className="size-3" />
                            Manage connectors
                        </a>
                    </Button>
                </div>
            </PopoverContent>
        </Popover>
    );
}
