import { CalendarDays, ChevronDown } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input, Label } from '@/components/ui/input';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { Separator } from '@/components/ui/separator';
import { useFilters } from '@/hooks/use-filters';
import { formatLongDate } from '@/lib/format';
import { cn } from '@/lib/utils';

const PRESETS: { value: string; label: string }[] = [
    { value: 'today', label: 'Today' },
    { value: 'yesterday', label: 'Yesterday' },
    { value: 'last_7_days', label: 'Last 7 days' },
    { value: 'last_30_days', label: 'Last 30 days' },
    { value: 'last_90_days', label: 'Last 90 days' },
    { value: 'mtd', label: 'Month to date' },
    { value: 'last_month', label: 'Last month' },
    { value: 'qtd', label: 'Quarter to date' },
    { value: 'ytd', label: 'Financial YTD' },
];

/**
 * The global window. Persisted in the URL query and localStorage, and attached
 * to every analytics request.
 */
export function DateRangePicker({ period }: { period?: { from: string; to: string } | null }) {
    const { filters, setFilters } = useFilters();
    const [open, setOpen] = useState(false);
    const [customFrom, setCustomFrom] = useState(filters.from ?? '');
    const [customTo, setCustomTo] = useState(filters.to ?? '');

    const label = filters.preset
        ? (PRESETS.find((p) => p.value === filters.preset)?.label ?? 'Custom')
        : period
          ? `${formatLongDate(period.from)} — ${formatLongDate(period.to)}`
          : 'Custom range';

    return (
        <Popover open={open} onOpenChange={setOpen}>
            <PopoverTrigger asChild>
                <Button variant="outline" size="sm" className="gap-2 font-normal">
                    <CalendarDays className="size-3.5 text-muted-foreground" />
                    <span className="max-w-[190px] truncate">{label}</span>
                    <ChevronDown className="size-3.5 text-muted-foreground" />
                </Button>
            </PopoverTrigger>
            <PopoverContent align="end" className="w-72 p-2">
                <div className="grid grid-cols-2 gap-1">
                    {PRESETS.map((preset) => (
                        <button
                            key={preset.value}
                            type="button"
                            onClick={() => {
                                setFilters({ preset: preset.value });
                                setOpen(false);
                            }}
                            className={cn(
                                'rounded-lg px-2.5 py-1.5 text-left text-xs transition-colors hover:bg-accent',
                                filters.preset === preset.value && 'bg-accent font-medium text-accent-foreground',
                            )}
                        >
                            {preset.label}
                        </button>
                    ))}
                </div>

                <Separator className="my-2" />

                <div className="space-y-2 px-1 pb-1">
                    <p className="text-[11px] font-semibold uppercase tracking-wide text-muted-foreground">Custom range</p>
                    <div className="grid grid-cols-2 gap-2">
                        <div className="space-y-1">
                            <Label htmlFor="range-from">From</Label>
                            <Input id="range-from" type="date" value={customFrom} onChange={(e) => setCustomFrom(e.target.value)} className="h-8 text-xs" />
                        </div>
                        <div className="space-y-1">
                            <Label htmlFor="range-to">To</Label>
                            <Input id="range-to" type="date" value={customTo} onChange={(e) => setCustomTo(e.target.value)} className="h-8 text-xs" />
                        </div>
                    </div>
                    <Button
                        size="sm"
                        className="w-full"
                        disabled={!customFrom || !customTo}
                        onClick={() => {
                            setFilters({ from: customFrom, to: customTo, preset: null });
                            setOpen(false);
                        }}
                    >
                        Apply range
                    </Button>
                </div>
            </PopoverContent>
        </Popover>
    );
}
