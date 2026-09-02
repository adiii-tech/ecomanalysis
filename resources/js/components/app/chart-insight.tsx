import { RefreshCw, Sparkles, X } from 'lucide-react';
import { useCallback, useEffect, useState } from 'react';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { apiSend } from '@/lib/api';
import { useFilters } from '@/hooks/use-filters';
import { cn } from '@/lib/utils';

interface InsightResponse {
    content: string;
    cached: boolean;
    credits: { remaining: number };
}

/**
 * The ✨ strip under a chart: two or three sentences on what the data says.
 *
 * Generated on demand rather than on page load — nobody wants to pay for an
 * insight on 19 widgets they scrolled past.
 */
export function ChartInsight({
    widgetKey,
    title,
    payload,
    onClose,
}: {
    widgetKey: string;
    title: string;
    payload: unknown;
    onClose: () => void;
}) {
    const { queryParams } = useFilters();
    const [content, setContent] = useState<string | null>(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);
    const [cached, setCached] = useState(false);

    const generate = useCallback(async (refresh = false) => {
        setLoading(true);
        setError(null);

        try {
            const response = await apiSend<InsightResponse>(
                'POST',
                `ai/chart-insight?${new URLSearchParams(queryParams)}`,
                { widget_key: widgetKey, title, payload: payload ?? {}, refresh },
            );
            setContent(response.data.content);
            setCached(response.data.cached);
        } catch (err) {
            setError(err instanceof Error ? err.message : 'Could not generate an insight.');
        } finally {
            setLoading(false);
        }
    }, [widgetKey, title, payload, queryParams]);

    // The parent only mounts this once the ✨ button is pressed, so generating
    // on mount is the intended behaviour.
    useEffect(() => {
        void generate();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    return (
        <div className={cn('rounded-lg border border-primary/20 bg-primary/5 px-3 py-2.5')}>
            <div className="flex items-start gap-2">
                <Sparkles className="mt-0.5 size-3.5 shrink-0 text-primary" />

                <div className="min-w-0 flex-1">
                    {loading && <Skeleton className="h-8 w-full" />}
                    {error && <p className="text-xs text-bad">{error}</p>}
                    {content && <p className="text-xs leading-relaxed text-foreground/90">{content}</p>}
                    {cached && !loading && (
                        <p className="mt-1 text-[10px] text-muted-foreground">Generated earlier today from the same numbers.</p>
                    )}
                </div>

                <div className="flex shrink-0 items-center gap-0.5">
                    <Button variant="ghost" size="icon-sm" onClick={() => generate(true)} disabled={loading} aria-label="Regenerate">
                        <RefreshCw className={cn('size-3', loading && 'animate-spin')} />
                    </Button>
                    <Button variant="ghost" size="icon-sm" onClick={onClose} aria-label="Dismiss">
                        <X className="size-3" />
                    </Button>
                </div>
            </div>
        </div>
    );
}
