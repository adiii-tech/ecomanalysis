import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { formatCurrency, formatNumber } from '@/lib/format';
import { cn } from '@/lib/utils';

export interface SummaryRow {
    key: string;
    label: string;
    orders: number;
    amount: number;
    kind: 'positive' | 'negative' | 'subtotal' | 'total' | 'neutral';
    tooltip: string;
}

/**
 * The gross → net chain as a table. Subtotals and the final total are visually
 * separated so the eye lands on the two numbers that matter.
 */
export function SalesSummaryTable({ rows }: { rows: SummaryRow[] }) {
    return (
        <div className="overflow-x-auto scrollbar-thin">
            <table className="w-full text-sm">
                <thead>
                    <tr className="border-b border-border">
                        <th className="py-2 text-left text-[11px] font-semibold uppercase tracking-wide text-muted-foreground">Line</th>
                        <th className="py-2 text-right text-[11px] font-semibold uppercase tracking-wide text-muted-foreground">Orders</th>
                        <th className="py-2 text-right text-[11px] font-semibold uppercase tracking-wide text-muted-foreground">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    {rows.map((row) => (
                        <tr
                            key={row.key}
                            className={cn(
                                'border-b border-border/50 last:border-0',
                                row.kind === 'subtotal' && 'bg-muted/40 font-medium',
                                row.kind === 'total' && 'border-t-2 border-border bg-accent/40 font-semibold',
                            )}
                        >
                            <td className="py-2 pl-1">
                                <Tooltip>
                                    <TooltipTrigger asChild>
                                        <span className="cursor-help border-b border-dotted border-muted-foreground/40">{row.label}</span>
                                    </TooltipTrigger>
                                    <TooltipContent>{row.tooltip}</TooltipContent>
                                </Tooltip>
                            </td>
                            <td className="py-2 text-right tnum text-muted-foreground">
                                {row.orders > 0 ? formatNumber(row.orders) : '—'}
                            </td>
                            <td
                                className={cn(
                                    'py-2 pr-1 text-right tnum',
                                    row.kind === 'negative' && row.amount !== 0 && 'text-bad',
                                    row.kind === 'total' && (row.amount >= 0 ? 'text-good' : 'text-bad'),
                                )}
                            >
                                {formatCurrency(row.amount)}
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}
