import { Link, useForm } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import { AuthLayout } from '@/layouts/auth-layout';
import { Button } from '@/components/ui/button';
import { Input, Label } from '@/components/ui/input';

export default function Login({
    canResetPassword,
    demoCredentials,
    status,
}: {
    canResetPassword: boolean;
    demoCredentials: { email: string; password: string } | null;
    status?: string;
}) {
    const { data, setData, post, processing, errors, reset } = useForm({
        email: demoCredentials?.email ?? '',
        password: demoCredentials?.password ?? '',
        remember: false,
    });

    return (
        <AuthLayout title="Sign in" description="Your profitability command centre.">
            {status && <div className="rounded-lg bg-good-soft px-3 py-2 text-xs text-good">{status}</div>}

            <form
                className="space-y-4"
                onSubmit={(event) => {
                    event.preventDefault();
                    post('/login', { onFinish: () => reset('password') });
                }}
            >
                <div className="space-y-1.5">
                    <Label htmlFor="email">Email</Label>
                    <Input
                        id="email"
                        type="email"
                        autoComplete="email"
                        autoFocus
                        value={data.email}
                        onChange={(event) => setData('email', event.target.value)}
                    />
                    {errors.email && <p className="text-xs text-bad">{errors.email}</p>}
                </div>

                <div className="space-y-1.5">
                    <div className="flex items-center justify-between">
                        <Label htmlFor="password">Password</Label>
                        {canResetPassword && (
                            <Link href="/forgot-password" className="text-xs text-muted-foreground hover:text-foreground">
                                Forgot?
                            </Link>
                        )}
                    </div>
                    <Input
                        id="password"
                        type="password"
                        autoComplete="current-password"
                        value={data.password}
                        onChange={(event) => setData('password', event.target.value)}
                    />
                    {errors.password && <p className="text-xs text-bad">{errors.password}</p>}
                </div>

                <label className="flex items-center gap-2 text-xs text-muted-foreground">
                    <input
                        type="checkbox"
                        checked={data.remember}
                        onChange={(event) => setData('remember', event.target.checked)}
                        className="size-3.5 rounded border-input"
                    />
                    Keep me signed in
                </label>

                <Button type="submit" className="w-full" disabled={processing}>
                    {processing && <LoaderCircle className="animate-spin" />}
                    Sign in
                </Button>
            </form>

            {demoCredentials && (
                <p className="rounded-lg border border-dashed border-border px-3 py-2 text-[11px] text-muted-foreground">
                    Local demo tenant is pre-filled. Other seeded roles use the same password:{' '}
                    <span className="font-mono">finance@</span>, <span className="font-mono">marketing@</span>,{' '}
                    <span className="font-mono">ops@</span>, <span className="font-mono">analyst@</span>,{' '}
                    <span className="font-mono">demo@kairaliving.test</span>.
                </p>
            )}
        </AuthLayout>
    );
}
