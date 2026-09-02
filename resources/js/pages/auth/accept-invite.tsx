import { useForm } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import { AuthLayout } from '@/layouts/auth-layout';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Input, Label } from '@/components/ui/input';

export default function AcceptInvite({
    token,
    email,
    name,
    role,
    tenant,
}: {
    token: string;
    email: string;
    name: string | null;
    role: string;
    tenant: string;
}) {
    const { data, setData, post, processing, errors } = useForm({
        name: name ?? '',
        password: '',
        password_confirmation: '',
    });

    return (
        <AuthLayout title={`Join ${tenant}`} description="Set a password to activate your account.">
            <div className="flex items-center gap-2 rounded-lg border border-border bg-accent/40 px-3 py-2 text-xs">
                <span className="text-muted-foreground">{email}</span>
                <Badge variant="secondary">{role}</Badge>
            </div>

            <form
                className="space-y-4"
                onSubmit={(event) => {
                    event.preventDefault();
                    post(`/accept-invite/${token}`);
                }}
            >
                <div className="space-y-1.5">
                    <Label htmlFor="name">Your name</Label>
                    <Input id="name" autoFocus value={data.name} onChange={(event) => setData('name', event.target.value)} />
                    {errors.name && <p className="text-xs text-bad">{errors.name}</p>}
                </div>

                <div className="space-y-1.5">
                    <Label htmlFor="password">Password</Label>
                    <Input id="password" type="password" value={data.password} onChange={(event) => setData('password', event.target.value)} />
                    {errors.password && <p className="text-xs text-bad">{errors.password}</p>}
                </div>

                <div className="space-y-1.5">
                    <Label htmlFor="password_confirmation">Confirm password</Label>
                    <Input
                        id="password_confirmation"
                        type="password"
                        value={data.password_confirmation}
                        onChange={(event) => setData('password_confirmation', event.target.value)}
                    />
                </div>

                <Button type="submit" className="w-full" disabled={processing}>
                    {processing && <LoaderCircle className="animate-spin" />}
                    Create account
                </Button>
            </form>
        </AuthLayout>
    );
}
