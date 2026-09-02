import { Bookmark, Check, Loader2, Share2, Trash2 } from 'lucide-react';
import { useCallback, useEffect, useState } from 'react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import { Input, Label } from '@/components/ui/input';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { Badge } from '@/components/ui/badge';
import { useFilters, type FilterState } from '@/hooks/use-filters';
import { apiGet, apiSend } from '@/lib/api';
import { cn } from '@/lib/utils';

interface SavedView {
    id: number;
    surface: string;
    name: string;
    state: Partial<FilterState>;
    is_shared: boolean;
    is_mine: boolean;
    owner: string | null;
}

/**
 * Named filter sets for the current screen. "Last quarter, marketplace only,
 * return-date basis" is a question people ask weekly — this stops them
 * rebuilding it every time.
 */
export function SavedViews({ surface }: { surface: string }) {
    const { filters, setFilters } = useFilters();
    const [views, setViews] = useState<SavedView[] | null>(null);
    const [open, setOpen] = useState(false);
    const [name, setName] = useState('');
    const [shared, setShared] = useState(false);
    const [saving, setSaving] = useState(false);

    const load = useCallback(() => {
        apiGet<{ rows: SavedView[] }>('/saved-views', { surface })
            .then((response) => setViews(response.data.rows))
            .catch(() => setViews([]));
    }, [surface]);

    useEffect(() => {
        if (open && views === null) {
            load();
        }
    }, [open, views, load]);

    const save = async () => {
        if (name.trim() === '') {
            toast.error('Give the view a name.');
            return;
        }

        setSaving(true);
        try {
            await apiSend('POST', '/saved-views', { surface, name: name.trim(), state: filters, is_shared: shared });
            toast.success('View saved.');
            setName('');
            load();
        } catch (error) {
            toast.error(error instanceof Error ? error.message : 'Could not save the view.');
        } finally {
            setSaving(false);
        }
    };

    const apply = (view: SavedView) => {
        setFilters(view.state);
        setOpen(false);
        toast.success(`Applied "${view.name}".`);
    };

    const remove = async (view: SavedView) => {
        try {
            await apiSend('DELETE', `/saved-views/${view.id}`);
            toast.success('View deleted.');
            load();
        } catch (error) {
            toast.error(error instanceof Error ? error.message : 'Could not delete that view.');
        }
    };

    return (
        <Popover open={open} onOpenChange={setOpen}>
            <PopoverTrigger asChild>
                <Button variant="outline" size="sm" className="gap-1.5 text-xs">
                    <Bookmark className="size-3.5" />
                    Views
                    {views && views.length > 0 && <Badge variant="muted">{views.length}</Badge>}
                </Button>
            </PopoverTrigger>
            <PopoverContent align="start" className="w-80 p-3">
                <div className="space-y-3">
                    <div className="space-y-1.5">
                        {views === null && <p className="text-xs text-muted-foreground">Loading…</p>}
                        {views?.length === 0 && (
                            <p className="text-xs text-muted-foreground">
                                No saved views yet. Set the filters you want, then name them below.
                            </p>
                        )}
                        {views?.map((view) => (
                            <div key={view.id} className="flex items-center gap-1">
                                <button
                                    type="button"
                                    onClick={() => apply(view)}
                                    className="flex min-w-0 flex-1 items-center gap-2 rounded-lg px-2 py-1.5 text-left text-xs transition-colors hover:bg-accent/60"
                                >
                                    <Check className="size-3 shrink-0 text-muted-foreground" />
                                    <span className="truncate font-medium">{view.name}</span>
                                    {view.is_shared && <Share2 className="size-3 shrink-0 text-muted-foreground" />}
                                    {!view.is_mine && (
                                        <span className="shrink-0 text-[10px] text-muted-foreground">by {view.owner}</span>
                                    )}
                                </button>
                                {view.is_mine && (
                                    <Button variant="ghost" size="icon" onClick={() => remove(view)} aria-label={`Delete ${view.name}`}>
                                        <Trash2 className="size-3.5" />
                                    </Button>
                                )}
                            </div>
                        ))}
                    </div>

                    <div className="space-y-1.5 border-t border-border pt-3">
                        <Label htmlFor="view-name">Save the current filters</Label>
                        <Input
                            id="view-name"
                            placeholder="Last quarter, marketplace only"
                            value={name}
                            onChange={(event) => setName(event.target.value)}
                            onKeyDown={(event) => event.key === 'Enter' && save()}
                        />
                        <label className="flex cursor-pointer items-center gap-2 text-[11px] text-muted-foreground">
                            <input
                                type="checkbox"
                                checked={shared}
                                onChange={(event) => setShared(event.target.checked)}
                                className="size-3.5 rounded border-border"
                            />
                            Share with everyone on this account
                        </label>
                        <Button size="sm" className={cn('w-full')} onClick={save} disabled={saving}>
                            {saving && <Loader2 className="size-3.5 animate-spin" />} Save view
                        </Button>
                    </div>
                </div>
            </PopoverContent>
        </Popover>
    );
}
