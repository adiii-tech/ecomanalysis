import { Download, Info, Maximize2, Sparkles } from 'lucide-react';
import { useState, type ReactNode } from 'react';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { CaveatNote } from '@/components/app/caveat-note';
import { ChartInsight } from '@/components/app/chart-insight';
import { VerdictNote } from '@/components/app/verdict-note';
import { SkeletonChart } from '@/components/ui/skeleton';
import { WidgetError } from '@/components/app/empty-state';
import { usePermissions } from '@/hooks/use-permissions';
import { useExport, type ExportFormat } from '@/hooks/use-export';
import { cn } from '@/lib/utils';
import type { Caveat, Verdict } from '@/types';

export interface ChartCardProps {
    title: string;
    subtitle?: string;
    tooltip?: string;
    /** module.widget prefix, e.g. "dashboard.channel_mix" — gates export and AI insight. */
    widgetKey?: string;
    tabs?: ReactNode;
    actions?: ReactNode;
    onDetail?: () => void;
    /** Dataset key to export. The card builds the download itself. */
    exportDataset?: string;
    onExport?: (format: ExportFormat) => void;
    /** Data the AI should explain. Passing it enables the ✨ button. */
    insightPayload?: unknown;
    onAiInsight?: () => void;
    verdict?: Verdict | null;
    caveat?: Caveat | string | null;
    loading?: boolean;
    error?: string | null;
    onRetry?: () => void;
    empty?: boolean;
    emptyState?: ReactNode;
    className?: string;
    bodyClassName?: string;
    children: ReactNode;
}

export function ChartCard({
    title,
    subtitle,
    tooltip,
    widgetKey,
    tabs,
    actions,
    onDetail,
    exportDataset,
    onExport,
    insightPayload,
    onAiInsight,
    verdict,
    caveat,
    loading = false,
    error = null,
    onRetry,
    empty = false,
    emptyState,
    className,
    bodyClassName,
    children,
}: ChartCardProps) {
    const { can } = usePermissions();
    const download = useExport();
    const [showInsight, setShowInsight] = useState(false);
    const canExport = widgetKey ? can(`${widgetKey}.export`) : true;
    const exportHandler = onExport ?? (exportDataset ? (format: ExportFormat) => download(exportDataset, format) : undefined);
    const canSeeInsight = can('ai.chart_insight.view');

    return (
        <Card className={cn('flex flex-col', className)}>
            <CardHeader className="gap-2 pb-3">
                <div className="flex flex-wrap items-start justify-between gap-2">
                    <div className="min-w-0 space-y-0.5">
                        <div className="flex items-center gap-1.5">
                            <h3 className="truncate text-sm font-semibold tracking-tight">{title}</h3>
                            {tooltip && (
                                <Tooltip>
                                    <TooltipTrigger asChild>
                                        <button type="button" className="text-muted-foreground/60 transition hover:text-muted-foreground" aria-label={`About ${title}`}>
                                            <Info className="size-3.5" />
                                        </button>
                                    </TooltipTrigger>
                                    <TooltipContent>{tooltip}</TooltipContent>
                                </Tooltip>
                            )}
                        </div>
                        {subtitle && <p className="text-xs text-muted-foreground">{subtitle}</p>}
                    </div>

                    <div className="flex shrink-0 items-center gap-1">
                        {tabs}
                        {actions}
                        {(onAiInsight || (insightPayload !== undefined && widgetKey)) && canSeeInsight && (
                            <Tooltip>
                                <TooltipTrigger asChild>
                                    <Button
                                        variant="ghost"
                                        size="icon-sm"
                                        onClick={onAiInsight ?? (() => setShowInsight(true))}
                                        aria-label="Explain this chart"
                                    >
                                        <Sparkles className="size-3.5 text-primary" />
                                    </Button>
                                </TooltipTrigger>
                                <TooltipContent>The story behind this chart</TooltipContent>
                            </Tooltip>
                        )}
                        {exportHandler && canExport && (
                            <DropdownMenu>
                                <DropdownMenuTrigger asChild>
                                    <Button variant="ghost" size="icon-sm" aria-label="Export">
                                        <Download className="size-3.5" />
                                    </Button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent align="end">
                                    <DropdownMenuItem onSelect={() => exportHandler('csv')}>Export CSV</DropdownMenuItem>
                                    <DropdownMenuItem onSelect={() => exportHandler('xlsx')}>Export Excel</DropdownMenuItem>
                                    <DropdownMenuItem onSelect={() => exportHandler('pdf')}>Export PDF</DropdownMenuItem>
                                </DropdownMenuContent>
                            </DropdownMenu>
                        )}
                        {onDetail && (
                            <Button variant="ghost" size="sm" onClick={onDetail} className="gap-1 text-xs text-muted-foreground">
                                <Maximize2 className="size-3" />
                                View detail
                            </Button>
                        )}
                    </div>
                </div>
            </CardHeader>

            <CardContent className={cn('flex flex-1 flex-col gap-3', bodyClassName)}>
                {loading ? (
                    <SkeletonChart />
                ) : error ? (
                    <WidgetError message={error} onRetry={onRetry} />
                ) : empty ? (
                    (emptyState ?? <div className="py-8 text-center text-xs text-muted-foreground">No data in this period.</div>)
                ) : (
                    children
                )}

                {showInsight && widgetKey && !loading && !error && (
                    <ChartInsight
                        widgetKey={widgetKey}
                        title={title}
                        payload={insightPayload}
                        onClose={() => setShowInsight(false)}
                    />
                )}

                {!loading && !error && verdict && <VerdictNote verdict={verdict} />}
                {!loading && caveat && <CaveatNote caveat={caveat} />}
            </CardContent>
        </Card>
    );
}
