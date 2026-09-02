import { Link } from '@inertiajs/react';
import { Hammer } from 'lucide-react';
import { Card } from '@/components/ui/card';
import { Button } from '@/components/ui/button';

/**
 * An honest placeholder for a module whose backend has not been built yet.
 * Better than a page of empty widgets that implies data is merely missing.
 */
export function ModuleNotReady({
    title,
    summary,
    planned,
    dependsOn,
}: {
    title: string;
    summary: string;
    planned: string[];
    dependsOn?: string;
}) {
    return (
        <Card className="mx-auto max-w-2xl p-6">
            <div className="flex items-start gap-3">
                <span className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-warn-soft text-warn">
                    <Hammer className="size-4" />
                </span>
                <div className="space-y-1">
                    <h2 className="text-sm font-semibold">{title} is not built yet</h2>
                    <p className="text-xs leading-relaxed text-muted-foreground">{summary}</p>
                </div>
            </div>

            <div className="mt-5 space-y-2">
                <p className="text-[11px] font-semibold uppercase tracking-wide text-muted-foreground">What this page will contain</p>
                <ul className="space-y-1.5">
                    {planned.map((item) => (
                        <li key={item} className="flex items-start gap-2 text-xs text-foreground/85">
                            <span className="mt-1.5 size-1 shrink-0 rounded-full bg-muted-foreground/50" />
                            {item}
                        </li>
                    ))}
                </ul>
            </div>

            {dependsOn && (
                <p className="mt-4 rounded-lg bg-muted px-3 py-2 text-[11px] text-muted-foreground">
                    Needs {dependsOn} before it can show anything real.
                </p>
            )}

            <div className="mt-5 flex gap-2">
                <Button size="sm" asChild>
                    <Link href="/dashboard">Back to Command Centre</Link>
                </Button>
                <Button size="sm" variant="outline" asChild>
                    <Link href="/connectors">Connectors</Link>
                </Button>
            </div>
        </Card>
    );
}
