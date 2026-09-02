import { Head } from '@inertiajs/react';
import { Sparkles } from 'lucide-react';
import { Card } from '@/components/ui/card';
import { formatLongDate } from '@/lib/format';
import { cn } from '@/lib/utils';

/**
 * A publicly shared answer. Read-only, and deliberately carries no navigation
 * into the app — the link grants access to this conversation and nothing else.
 */
export default function SharedAnswer({
    title,
    brand,
    sharedAt,
    messages,
}: {
    title: string;
    brand: string | null;
    sharedAt: string | null;
    messages: { role: string; content: string; created_at: string | null }[];
}) {
    return (
        <div className="min-h-screen bg-background px-4 py-10">
            <Head title={title} />

            <div className="mx-auto max-w-2xl space-y-4">
                <div className="space-y-1">
                    <p className="flex items-center gap-1.5 text-xs text-muted-foreground">
                        <Sparkles className="size-3.5 text-primary" />
                        Shared analysis{brand ? ` from ${brand}` : ''}
                    </p>
                    <h1 className="text-xl font-semibold tracking-tight">{title}</h1>
                    {sharedAt && <p className="text-xs text-muted-foreground">Shared {formatLongDate(sharedAt)}</p>}
                </div>

                <div className="space-y-3">
                    {messages.map((message, index) => (
                        <Card
                            key={index}
                            className={cn('p-4', message.role === 'user' && 'border-primary/25 bg-primary/5')}
                        >
                            <p className="mb-1.5 text-[11px] font-semibold uppercase tracking-wide text-muted-foreground">
                                {message.role === 'user' ? 'Question' : 'Answer'}
                            </p>
                            <p className="whitespace-pre-wrap text-sm leading-relaxed">{message.content}</p>
                        </Card>
                    ))}
                </div>

                <p className="pt-2 text-center text-[11px] text-muted-foreground">
                    This is a read-only snapshot. The numbers were correct when it was shared.
                </p>
            </div>
        </div>
    );
}
