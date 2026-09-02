import { Head, Link } from '@inertiajs/react';
import { Clock, FileBarChart, Search, Star } from 'lucide-react';
import { useCallback, useMemo, useState } from 'react';
import { AppLayout } from '@/layouts/app-layout';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { SkeletonChart } from '@/components/ui/skeleton';
import { WidgetError } from '@/components/app/empty-state';
import type { ReportMeta } from '@/components/reports/types';
import { useWidget } from '@/hooks/use-widget';
import { apiSend } from '@/lib/api';
import { formatDateTime } from '@/lib/format';
import { cn } from '@/lib/utils';

type Sort = 'a-z' | 'recent' | 'favourites';

export default function Reports() {
    const { data, loading, error, reload } = useWidget<{ reports: ReportMeta[]; categories: string[] }>('/reports');
    const [query, setQuery] = useState('');
    const [category, setCategory] = useState<string | null>(null);
    const [sort, setSort] = useState<Sort>('a-z');
    const [favourites, setFavourites] = useState<Record<string, boolean>>({});

    const reports = useMemo(
        () =>
            (data?.reports ?? []).map((report) => ({
                ...report,
                is_favourite: favourites[report.key] ?? report.is_favourite ?? false,
            })),
        [data, favourites],
    );

    const toggleFavourite = useCallback(async (report: ReportMeta, next: boolean) => {
        setFavourites((current) => ({ ...current, [report.key]: next }));

        try {
            await apiSend('POST', `/reports/${report.slug}/favourite`);
        } catch {
            setFavourites((current) => ({ ...current, [report.key]: !next }));
        }
    }, []);

    const filtered = useMemo(() => {
        const needle = query.trim().toLowerCase();

        const matches = reports.filter((report) => {
            const matchesCategory = category === null || report.category === category;
            const matchesQuery =
                needle === '' ||
                report.label.toLowerCase().includes(needle) ||
                report.description.toLowerCase().includes(needle) ||
                report.category.toLowerCase().includes(needle);

            return matchesCategory && matchesQuery;
        });

        if (sort === 'favourites') {
            return matches.filter((report) => report.is_favourite);
        }

        if (sort === 'recent') {
            return [...matches].sort((left, right) => (right.last_used_at ?? '').localeCompare(left.last_used_at ?? ''));
        }

        return [...matches].sort((left, right) => left.label.localeCompare(right.label));
    }, [reports, query, category, sort]);

    const counts = useMemo(() => {
        const map: Record<string, number> = {};
        reports.forEach((report) => {
            map[report.category] = (map[report.category] ?? 0) + 1;
        });
        return map;
    }, [reports]);

    return (
        <AppLayout title="Report library" description={`${reports.length} reports available to you`} showFilters={false}>
            <Head title="Reports" />

            {error && <WidgetError message={error} onRetry={reload} />}

            {loading && !data && (
                <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                    {[0, 1, 2, 3, 4, 5].map((index) => (
                        <SkeletonChart key={index} className="h-24" />
                    ))}
                </div>
            )}

            {data && (
                <>
                    <div className="grid grid-cols-2 gap-3 lg:grid-cols-5">
                        {(data.categories ?? []).filter((name) => counts[name]).map((name) => (
                            <button
                                key={name}
                                type="button"
                                onClick={() => setCategory((current) => (current === name ? null : name))}
                                className={cn(
                                    'rounded-(--radius-card) border p-4 text-left transition-colors',
                                    category === name ? 'border-primary bg-primary/5' : 'border-border bg-card hover:bg-accent/50',
                                )}
                            >
                                <p className="text-[11px] font-medium uppercase tracking-wide text-muted-foreground">{name}</p>
                                <p className="mt-2 text-xl font-semibold tnum">{counts[name]}</p>
                            </button>
                        ))}
                    </div>

                    <div className="flex flex-wrap items-center gap-2">
                        <div className="relative min-w-64 flex-1 sm:max-w-md">
                            <Search className="pointer-events-none absolute left-2.5 top-1/2 size-3.5 -translate-y-1/2 text-muted-foreground" />
                            <Input
                                value={query}
                                onChange={(event) => setQuery(event.target.value)}
                                placeholder="Search reports…"
                                className="pl-8"
                            />
                        </div>
                        <div className="flex items-center gap-1">
                            {(['a-z', 'recent', 'favourites'] as Sort[]).map((option) => (
                                <Button
                                    key={option}
                                    variant={sort === option ? 'secondary' : 'ghost'}
                                    size="sm"
                                    onClick={() => setSort(option)}
                                >
                                    {option === 'a-z' ? 'A–Z' : option === 'recent' ? 'Recently used' : 'Favourites'}
                                </Button>
                            ))}
                        </div>
                    </div>

                    <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                        {filtered.map((report) => (
                            <Card key={report.key} className="group relative h-full p-4 transition-shadow hover:shadow-md">
                                <button
                                    type="button"
                                    onClick={() => toggleFavourite(report, !report.is_favourite)}
                                    className="absolute right-3 top-3 text-muted-foreground transition-colors hover:text-warn"
                                    aria-label={report.is_favourite ? 'Remove from favourites' : 'Add to favourites'}
                                >
                                    <Star className={cn('size-4', report.is_favourite && 'fill-warn text-warn')} />
                                </button>

                                <Link href={`/reports/${report.slug}`} className="flex items-start gap-2.5">
                                    <span className="flex size-8 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                                        <FileBarChart className="size-4" />
                                    </span>
                                    <div className="min-w-0 pr-6">
                                        <p className="truncate text-sm font-semibold">{report.label}</p>
                                        <Badge variant="muted" className="mt-1">{report.category}</Badge>
                                        <p className="mt-1.5 text-xs leading-snug text-muted-foreground">{report.description}</p>
                                        {report.last_used_at && (
                                            <p className="mt-1.5 flex items-center gap-1 text-[11px] text-muted-foreground">
                                                <Clock className="size-3" /> opened {formatDateTime(report.last_used_at)}
                                            </p>
                                        )}
                                    </div>
                                </Link>
                            </Card>
                        ))}
                    </div>

                    {filtered.length === 0 && (
                        <p className="py-12 text-center text-sm text-muted-foreground">
                            {sort === 'favourites' ? 'No favourites yet — star a report to pin it here.' : 'No report matches that search.'}
                        </p>
                    )}
                </>
            )}
        </AppLayout>
    );
}
