import { usePage } from '@inertiajs/react';
import { Store } from 'lucide-react';
import { Select, SelectContent, SelectGroup, SelectItem, SelectLabel, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useFilters } from '@/hooks/use-filters';
import type { SharedProps } from '@/types';

export function ChannelFilter() {
    const { channels } = usePage<SharedProps>().props;
    const { filters, setFilters } = useFilters();

    const marketplaces = channels.filter((channel) => channel.type === 'marketplace');
    const d2c = channels.filter((channel) => channel.type === 'd2c');

    return (
        <Select value={filters.channel} onValueChange={(value) => setFilters({ channel: value })}>
            <SelectTrigger className="h-8 w-[170px] text-xs">
                <span className="flex items-center gap-1.5 truncate">
                    <Store className="size-3.5 shrink-0 text-muted-foreground" />
                    <SelectValue />
                </span>
            </SelectTrigger>
            <SelectContent>
                <SelectItem value="all">All channels</SelectItem>
                <SelectItem value="d2c">D2C only</SelectItem>
                <SelectItem value="marketplace">Marketplaces only</SelectItem>

                {d2c.length > 0 && (
                    <SelectGroup>
                        <SelectLabel>D2C</SelectLabel>
                        {d2c.map((channel) => (
                            <SelectItem key={channel.code} value={channel.code}>
                                {channel.name}
                            </SelectItem>
                        ))}
                    </SelectGroup>
                )}

                {marketplaces.length > 0 && (
                    <SelectGroup>
                        <SelectLabel>Marketplaces</SelectLabel>
                        {marketplaces.map((channel) => (
                            <SelectItem key={channel.code} value={channel.code}>
                                {channel.name}
                            </SelectItem>
                        ))}
                    </SelectGroup>
                )}
            </SelectContent>
        </Select>
    );
}
