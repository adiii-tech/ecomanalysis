import { Head } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import { Loader2, MessageSquarePlus, Send, Share2, Sparkles, Trash2, Wrench } from 'lucide-react';
import { toast } from 'sonner';
import { AppLayout } from '@/layouts/app-layout';
import { Card } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import { Skeleton } from '@/components/ui/skeleton';
import { EmptyState } from '@/components/app/empty-state';
import { CaveatNote } from '@/components/app/caveat-note';
import { useFilters } from '@/hooks/use-filters';
import { apiGet, apiSend } from '@/lib/api';
import { formatDateTime } from '@/lib/format';
import { cn } from '@/lib/utils';

interface ToolCall {
    tool: string;
    input: Record<string, unknown>;
    ok: boolean;
}

interface ChatMessage {
    id: number | string;
    role: string;
    content: string;
    tool_calls?: ToolCall[] | null;
    created_at?: string;
    pending?: boolean;
}

interface SessionRow {
    id: number;
    title: string;
    last_message_at: string | null;
    shared: boolean;
}

interface Status {
    configured: boolean;
    model: string;
    credits: { remaining: number; user_limit: number; limited_by: string };
    tools: { name: string; description: string }[];
    suggested_prompts: string[];
    caveat: string | null;
}

export default function AskAi() {
    const { queryParams } = useFilters();
    const [status, setStatus] = useState<Status | null>(null);
    const [sessions, setSessions] = useState<SessionRow[]>([]);
    const [sessionId, setSessionId] = useState<number | null>(null);
    const [messages, setMessages] = useState<ChatMessage[]>([]);
    const [input, setInput] = useState('');
    const [sending, setSending] = useState(false);
    const bottomRef = useRef<HTMLDivElement>(null);

    useEffect(() => {
        apiGet<Status>('ai/status').then((r) => setStatus(r.data)).catch(() => setStatus(null));
        refreshSessions();
    }, []);

    useEffect(() => {
        bottomRef.current?.scrollIntoView({ behavior: 'smooth' });
    }, [messages]);

    function refreshSessions() {
        apiGet<{ rows: SessionRow[] }>('ai/ask/history').then((r) => setSessions(r.data.rows)).catch(() => undefined);
    }

    async function openSession(id: number) {
        setSessionId(id);
        const response = await apiGet<{ messages: ChatMessage[] }>(`ai/ask/session/${id}`);
        setMessages(response.data.messages);
    }

    function newChat() {
        setSessionId(null);
        setMessages([]);
        setInput('');
    }

    async function send(question: string) {
        if (!question.trim() || sending) return;

        setInput('');
        setSending(true);
        setMessages((current) => [
            ...current,
            { id: `local-${Date.now()}`, role: 'user', content: question },
            { id: 'pending', role: 'assistant', content: '', pending: true },
        ]);

        try {
            const response = await apiSend<{
                session_id: number;
                message: ChatMessage;
                credits: Status['credits'];
            }>('POST', `ai/ask/chat?${new URLSearchParams(queryParams)}`, { message: question, session_id: sessionId });

            setSessionId(response.data.session_id);
            setMessages((current) => [...current.filter((m) => m.id !== 'pending'), response.data.message]);
            setStatus((current) => (current ? { ...current, credits: response.data.credits } : current));
            refreshSessions();
        } catch (error) {
            setMessages((current) => current.filter((m) => m.id !== 'pending'));
            toast.error(error instanceof Error ? error.message : 'Something went wrong.');
        } finally {
            setSending(false);
        }
    }

    async function share() {
        if (!sessionId) return;
        try {
            const response = await apiSend<{ url: string }>('POST', `ai/ask/session/${sessionId}/share`);
            await navigator.clipboard.writeText(response.data.url).catch(() => undefined);
            toast.success('Share link copied. It is read-only and you can revoke it any time.');
            refreshSessions();
        } catch (error) {
            toast.error(error instanceof Error ? error.message : 'Could not share.');
        }
    }

    async function remove(id: number) {
        await apiSend('DELETE', `ai/ask/session/${id}`).catch(() => undefined);
        if (id === sessionId) newChat();
        refreshSessions();
    }

    return (
        <AppLayout title="Ask AI" description="Ask about your own numbers">
            <Head title="Ask AI" />

            {status && !status.configured && <CaveatNote caveat={{ message: status.caveat ?? '', level: 'warning', connector: null }} />}

            <div className="grid gap-4 lg:grid-cols-[240px_1fr]">
                <div className="space-y-2">
                    <Button size="sm" className="w-full gap-1.5" onClick={newChat}>
                        <MessageSquarePlus className="size-3.5" />
                        New chat
                    </Button>

                    <div className="space-y-1">
                        {sessions.map((session) => (
                            <div
                                key={session.id}
                                className={cn(
                                    'group flex items-center gap-1 rounded-lg px-2 py-1.5 text-xs transition-colors',
                                    session.id === sessionId ? 'bg-accent text-accent-foreground' : 'hover:bg-accent/60',
                                )}
                            >
                                <button type="button" onClick={() => openSession(session.id)} className="min-w-0 flex-1 truncate text-left">
                                    {session.title}
                                </button>
                                {session.shared && <Share2 className="size-3 shrink-0 text-muted-foreground" />}
                                <button
                                    type="button"
                                    onClick={() => remove(session.id)}
                                    className="shrink-0 opacity-0 transition group-hover:opacity-100"
                                    aria-label="Delete chat"
                                >
                                    <Trash2 className="size-3 text-muted-foreground hover:text-bad" />
                                </button>
                            </div>
                        ))}
                        {sessions.length === 0 && <p className="px-2 py-3 text-[11px] text-muted-foreground">No chats yet.</p>}
                    </div>
                </div>

                <Card className="flex h-[calc(100vh-15rem)] flex-col">
                    <div className="flex items-center justify-between border-b border-border px-4 py-2.5">
                        <p className="flex items-center gap-1.5 text-xs text-muted-foreground">
                            <Sparkles className="size-3.5 text-primary" />
                            {status?.model ?? 'AI'} · reads only what your role can see
                        </p>
                        <div className="flex items-center gap-2">
                            {status && (
                                <Badge variant={status.credits.remaining > 0 ? 'muted' : 'bad'}>
                                    {status.credits.remaining} credits
                                </Badge>
                            )}
                            {sessionId && (
                                <Button size="xs" variant="ghost" onClick={share} className="gap-1">
                                    <Share2 className="size-3" />
                                    Share
                                </Button>
                            )}
                        </div>
                    </div>

                    <div className="flex-1 space-y-4 overflow-auto p-4 scrollbar-thin">
                        {messages.length === 0 && (
                            <div className="flex h-full flex-col items-center justify-center gap-4">
                                <EmptyState
                                    compact
                                    title="Ask about your numbers"
                                    description="Answers come from your live data through a fixed set of read-only metrics — never invented."
                                />
                                <div className="grid w-full max-w-lg gap-1.5 sm:grid-cols-2">
                                    {(status?.suggested_prompts ?? []).map((prompt) => (
                                        <button
                                            key={prompt}
                                            type="button"
                                            disabled={!status?.configured}
                                            onClick={() => send(prompt)}
                                            className="rounded-lg border border-border px-3 py-2 text-left text-xs transition-colors hover:bg-accent disabled:opacity-50"
                                        >
                                            {prompt}
                                        </button>
                                    ))}
                                </div>
                            </div>
                        )}

                        {messages.map((message) => (
                            <div key={message.id} className={cn('flex', message.role === 'user' ? 'justify-end' : 'justify-start')}>
                                <div
                                    className={cn(
                                        'max-w-[85%] rounded-xl px-3.5 py-2.5 text-sm leading-relaxed',
                                        message.role === 'user' ? 'bg-primary text-primary-foreground' : 'bg-muted',
                                    )}
                                >
                                    {message.pending ? (
                                        <span className="flex items-center gap-2 text-xs text-muted-foreground">
                                            <Loader2 className="size-3.5 animate-spin" />
                                            Reading your data…
                                        </span>
                                    ) : (
                                        <>
                                            <p className="whitespace-pre-wrap">{message.content}</p>
                                            {(message.tool_calls?.length ?? 0) > 0 && (
                                                <p className="mt-2 flex flex-wrap items-center gap-1 border-t border-border/40 pt-1.5 text-[10px] text-muted-foreground">
                                                    <Wrench className="size-2.5" />
                                                    {message.tool_calls!.map((call, index) => (
                                                        <span key={index} className={cn('rounded bg-background/60 px-1', !call.ok && 'text-bad')}>
                                                            {call.tool}
                                                        </span>
                                                    ))}
                                                </p>
                                            )}
                                        </>
                                    )}
                                </div>
                            </div>
                        ))}
                        <div ref={bottomRef} />
                    </div>

                    <form
                        className="flex items-center gap-2 border-t border-border p-3"
                        onSubmit={(event) => {
                            event.preventDefault();
                            send(input);
                        }}
                    >
                        <Input
                            value={input}
                            onChange={(event) => setInput(event.target.value)}
                            placeholder={status?.configured ? 'Ask about sales, margin, returns, campaigns…' : 'AI is not configured on this server'}
                            disabled={!status?.configured || sending}
                        />
                        <Button type="submit" size="icon" disabled={!status?.configured || sending || !input.trim()}>
                            {sending ? <Loader2 className="animate-spin" /> : <Send />}
                        </Button>
                    </form>
                </Card>
            </div>
        </AppLayout>
    );
}
