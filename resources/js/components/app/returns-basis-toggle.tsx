import { Info } from 'lucide-react';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { useFilters, type ReturnsBasis } from '@/hooks/use-filters';

/**
 * Order-date basis attributes a return to the order's cohort; return-date basis
 * attributes it to the day it came back. It changes every returns number in the
 * app, not just the headline.
 */
export function ReturnsBasisToggle() {
    const { filters, setFilters } = useFilters();

    return (
        <div className="flex items-center gap-1.5">
            <Tabs value={filters.returns_basis} onValueChange={(value) => setFilters({ returns_basis: value as ReturnsBasis })}>
                <TabsList>
                    <TabsTrigger value="order_date">Order date</TabsTrigger>
                    <TabsTrigger value="return_date">Return date</TabsTrigger>
                </TabsList>
            </Tabs>
            <Tooltip>
                <TooltipTrigger asChild>
                    <button type="button" className="text-muted-foreground/60 transition hover:text-muted-foreground" aria-label="About the returns basis">
                        <Info className="size-3.5" />
                    </button>
                </TooltipTrigger>
                <TooltipContent>
                    <p className="font-medium">Returns basis</p>
                    <p className="mt-1 opacity-90">
                        <b>Order date</b> counts a return against the day the order was placed — cohort accounting, so a
                        month&rsquo;s true return rate settles over time.
                    </p>
                    <p className="mt-1 opacity-90">
                        <b>Return date</b> counts it on the day it came back — what your warehouse actually handled this week.
                    </p>
                </TooltipContent>
            </Tooltip>
        </div>
    );
}
