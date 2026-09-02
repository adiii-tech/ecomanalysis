import { Head, router } from '@inertiajs/react';
import { Copy, Key, Loader2, LogOut, Monitor, ShieldCheck, Trash2 } from 'lucide-react';
import { useCallback, useEffect, useState } from 'react';
import { toast } from 'sonner';
import { AppLayout } from '@/layouts/app-layout';
import { ChartCard } from '@/components/app/chart-card';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input, Label } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { SkeletonChart } from '@/components/ui/skeleton';
import { WidgetError } from '@/components/app/empty-state';
import { apiGet, apiSend } from '@/lib/api';
import { ACCENTS, useAppearance, type Accent, type Theme } from '@/hooks/use-appearance';
import { formatDateTime } from '@/lib/format';
import { cn } from '@/lib/utils';

interface ProfilePayload {
    user: {
        id: number;
        name: string;
        email: string;
        role: string | null;
        theme: Theme;
        accent_color: Accent;
        mfa_enabled: boolean;
        must_change_password: boolean;
        created_at: string | null;
        last_login_at: string | null;
    };
    ai: { credits_used: number; credit_limit: number; credits_remaining: number };
    sessions: { id: string; ip_address: string; device: string; is_current: boolean; last_active: string }[];
    tokens: { id: number; name: string; last_used_at: string | null; created_at: string | null }[];
}

export default function Profile() {
    const [data, setData] = useState<ProfilePayload | null>(null);
    const [error, setError] = useState<string | null>(null);
    const [saving, setSaving] = useState(false);
    const [identity, setIdentity] = useState({ name: '', email: '' });
    const [passwords, setPasswords] = useState({ current_password: '', password: '', password_confirmation: '' });
    const [tokenName, setTokenName] = useState('');
    const [newToken, setNewToken] = useState<string | null>(null);
    const { theme, setTheme, accent, setAccent } = useAppearance();

    const load = useCallback(() => {
        apiGet<ProfilePayload>('/profile')
            .then((response) => {
                setData(response.data);
                setIdentity({ name: response.data.user.name, email: response.data.user.email });
                setError(null);
            })
            .catch((err: unknown) => setError(err instanceof Error ? err.message : 'Could not load your profile.'));
    }, []);

    useEffect(load, [load]);

    const saveIdentity = async () => {
        setSaving(true);
        try {
            const response = await apiSend<null>('PUT', '/profile', identity);
            toast.success(response.message);
            load();
            // The header shows the name, so refresh the shared props too.
            router.reload({ only: ['auth'] });
        } catch (err) {
            toast.error(err instanceof Error ? err.message : 'Could not save.');
        } finally {
            setSaving(false);
        }
    };

    /** Appearance is applied locally at once, then stored so other devices match. */
    const saveAppearance = async (next: { theme?: Theme; accent_color?: Accent }) => {
        if (next.theme) setTheme(next.theme);
        if (next.accent_color) setAccent(next.accent_color);

        try {
            await apiSend('PUT', '/profile', next);
        } catch {
            toast.error('Saved on this device, but could not sync to your account.');
        }
    };

    const changePassword = async () => {
        try {
            const response = await apiSend<null>('PUT', '/profile/password', passwords);
            toast.success(response.message);
            setPasswords({ current_password: '', password: '', password_confirmation: '' });
            load();
        } catch (err) {
            toast.error(err instanceof Error ? err.message : 'Could not change your password.');
        }
    };

    return (
        <AppLayout title="Profile & security" description="Your account, devices and API access" showFilters={false}>
            <Head title="Profile & security" />

            {error && <WidgetError message={error} onRetry={load} />}
            {!data && !error && <SkeletonChart className="h-64" />}

            {data && (
                <div className="grid gap-4 xl:grid-cols-2">
                    <ChartCard title="Your details" subtitle={`${data.user.role ?? 'No role'} · joined ${data.user.created_at ? formatDateTime(data.user.created_at) : '—'}`}>
                        <div className="space-y-3">
                            <div className="space-y-1">
                                <Label htmlFor="name">Name</Label>
                                <Input id="name" value={identity.name} onChange={(e) => setIdentity((c) => ({ ...c, name: e.target.value }))} />
                            </div>
                            <div className="space-y-1">
                                <Label htmlFor="email">Email</Label>
                                <Input id="email" type="email" value={identity.email} onChange={(e) => setIdentity((c) => ({ ...c, email: e.target.value }))} />
                            </div>
                            <Button size="sm" onClick={saveIdentity} disabled={saving}>
                                {saving && <Loader2 className="size-3.5 animate-spin" />} Save details
                            </Button>
                        </div>
                    </ChartCard>

                    <ChartCard title="Password" subtitle="Changing it signs out your other devices">
                        <div className="space-y-3">
                            <div className="space-y-1">
                                <Label htmlFor="current_password">Current password</Label>
                                <Input
                                    id="current_password"
                                    type="password"
                                    autoComplete="current-password"
                                    value={passwords.current_password}
                                    onChange={(e) => setPasswords((c) => ({ ...c, current_password: e.target.value }))}
                                />
                            </div>
                            <div className="space-y-1">
                                <Label htmlFor="password">New password</Label>
                                <Input
                                    id="password"
                                    type="password"
                                    autoComplete="new-password"
                                    value={passwords.password}
                                    onChange={(e) => setPasswords((c) => ({ ...c, password: e.target.value }))}
                                />
                                <p className="text-[11px] text-muted-foreground">At least 10 characters, with letters and numbers.</p>
                            </div>
                            <div className="space-y-1">
                                <Label htmlFor="password_confirmation">Confirm new password</Label>
                                <Input
                                    id="password_confirmation"
                                    type="password"
                                    autoComplete="new-password"
                                    value={passwords.password_confirmation}
                                    onChange={(e) => setPasswords((c) => ({ ...c, password_confirmation: e.target.value }))}
                                />
                            </div>
                            <Button size="sm" onClick={changePassword}>Change password</Button>
                        </div>
                    </ChartCard>

                    <ChartCard title="Appearance" subtitle="Stored on your account, so every device matches">
                        <div className="space-y-3">
                            <div className="space-y-1.5">
                                <Label>Theme</Label>
                                <Select value={theme} onValueChange={(value) => saveAppearance({ theme: value as Theme })}>
                                    <SelectTrigger><SelectValue /></SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="light">Light</SelectItem>
                                        <SelectItem value="dark">Dark</SelectItem>
                                        <SelectItem value="system">Match my system</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                            <div className="space-y-1.5">
                                <Label>Accent</Label>
                                <div className="flex flex-wrap gap-2">
                                    {ACCENTS.map((option) => (
                                        <button
                                            key={option}
                                            type="button"
                                            onClick={() => saveAppearance({ accent_color: option })}
                                            className={cn(
                                                'rounded-lg border px-3 py-1.5 text-xs capitalize transition-colors',
                                                accent === option ? 'border-primary bg-primary/5 font-medium' : 'border-border hover:bg-accent/50',
                                            )}
                                        >
                                            {option}
                                        </button>
                                    ))}
                                </div>
                            </div>
                        </div>
                    </ChartCard>

                    <ChartCard title="Two-factor authentication" subtitle="A second step when signing in">
                        <div className="flex items-center justify-between gap-3">
                            <div className="flex items-center gap-2">
                                <ShieldCheck className={cn('size-5', data.user.mfa_enabled ? 'text-good' : 'text-muted-foreground')} />
                                <div>
                                    <p className="text-sm font-medium">{data.user.mfa_enabled ? 'Enabled' : 'Not enabled'}</p>
                                    <p className="text-xs text-muted-foreground">
                                        {data.user.mfa_enabled
                                            ? 'You are asked for a code from your authenticator app at sign-in.'
                                            : 'Anyone with your password can sign in as you.'}
                                    </p>
                                </div>
                            </div>
                            <Button variant={data.user.mfa_enabled ? 'outline' : 'default'} size="sm" asChild>
                                <a href="/settings/mfa">{data.user.mfa_enabled ? 'Manage' : 'Set up'}</a>
                            </Button>
                        </div>
                    </ChartCard>

                    <ChartCard title="Signed-in devices" subtitle={`${data.sessions.length} active session${data.sessions.length === 1 ? '' : 's'}`}>
                        <div className="space-y-2">
                            {data.sessions.map((session) => (
                                <Card key={session.id} className="flex items-center justify-between gap-3 p-3">
                                    <div className="flex items-center gap-2.5">
                                        <Monitor className="size-4 text-muted-foreground" />
                                        <div>
                                            <p className="text-sm font-medium">
                                                {session.device}
                                                {session.is_current && <Badge variant="good" className="ml-2">This device</Badge>}
                                            </p>
                                            <p className="text-[11px] text-muted-foreground">
                                                {session.ip_address} · last active {formatDateTime(session.last_active)}
                                            </p>
                                        </div>
                                    </div>
                                </Card>
                            ))}

                            {data.sessions.length > 1 && (
                                <Button
                                    variant="outline"
                                    size="sm"
                                    className="w-full"
                                    onClick={async () => {
                                        try {
                                            const response = await apiSend<null>('DELETE', '/profile/sessions');
                                            toast.success(response.message);
                                            load();
                                        } catch {
                                            toast.error('Could not sign out the other devices.');
                                        }
                                    }}
                                >
                                    <LogOut className="size-3.5" /> Sign out everywhere else
                                </Button>
                            )}
                        </div>
                    </ChartCard>

                    <ChartCard title="API tokens" subtitle="For scripts and integrations that call this API directly">
                        <div className="space-y-3">
                            {newToken && (
                                <Card className="space-y-1.5 border-primary/40 bg-primary/5 p-3">
                                    <p className="text-xs font-medium">Copy this now — it is not shown again.</p>
                                    <div className="flex items-center gap-2">
                                        <code className="min-w-0 flex-1 truncate rounded bg-background px-2 py-1 text-[11px]">{newToken}</code>
                                        <Button
                                            size="icon"
                                            variant="ghost"
                                            onClick={() => {
                                                navigator.clipboard?.writeText(newToken).catch(() => undefined);
                                                toast.success('Token copied.');
                                            }}
                                            aria-label="Copy token"
                                        >
                                            <Copy className="size-4" />
                                        </Button>
                                    </div>
                                </Card>
                            )}

                            <div className="flex items-end gap-2">
                                <div className="flex-1 space-y-1">
                                    <Label htmlFor="token_name">Token name</Label>
                                    <Input id="token_name" placeholder="Warehouse script" value={tokenName} onChange={(e) => setTokenName(e.target.value)} />
                                </div>
                                <Button
                                    size="sm"
                                    disabled={tokenName.trim() === ''}
                                    onClick={async () => {
                                        try {
                                            const response = await apiSend<{ plain_text_token: string }>('POST', '/profile/tokens', { name: tokenName });
                                            setNewToken(response.data.plain_text_token);
                                            setTokenName('');
                                            load();
                                        } catch (err) {
                                            toast.error(err instanceof Error ? err.message : 'Could not create the token.');
                                        }
                                    }}
                                >
                                    <Key className="size-3.5" /> Create
                                </Button>
                            </div>

                            {data.tokens.map((token) => (
                                <Card key={token.id} className="flex items-center justify-between gap-3 p-3">
                                    <div>
                                        <p className="text-sm font-medium">{token.name}</p>
                                        <p className="text-[11px] text-muted-foreground">
                                            {token.last_used_at ? `last used ${formatDateTime(token.last_used_at)}` : 'never used'}
                                        </p>
                                    </div>
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        aria-label="Revoke token"
                                        onClick={async () => {
                                            try {
                                                await apiSend('DELETE', `/profile/tokens/${token.id}`);
                                                toast.success('Token revoked.');
                                                load();
                                            } catch {
                                                toast.error('Could not revoke that token.');
                                            }
                                        }}
                                    >
                                        <Trash2 className="size-4" />
                                    </Button>
                                </Card>
                            ))}
                        </div>
                    </ChartCard>
                </div>
            )}
        </AppLayout>
    );
}
