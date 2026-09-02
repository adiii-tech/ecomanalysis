import { Link, useForm } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import { AuthLayout } from '@/layouts/auth-layout';
import { Button } from '@/components/ui/button';
import { Input, Label } from '@/components/ui/input';

export default function ForgotPassword({ status }: { status?: string }) {
    const { data, setData, post, processing, errors } = useForm({ email: '' });

    return (
        <AuthLayout title="Reset your password" description="We'll email you a link to set a new one.">
            {status && <div className="rounded-lg bg-good-soft px-3 py-2 text-xs text-good">{status}</div>}

            <form
                className="space-y-4"
                onSubmit={(event) => {
                    event.preventDefault();
                    post('/forgot-password');
                }}
            >
                <div className="space-y-1.5">
                    <Label htmlFor="email">Email</Label>
                    <Input id="email" type="email" autoFocus value={data.email} onChange={(event) => setData('email', event.target.value)} />
                    {errors.email && <p className="text-xs text-bad">{errors.email}</p>}
                </div>

                <Button type="submit" className="w-full" disabled={processing}>
                    {processing && <LoaderCircle className="animate-spin" />}
                    Email reset link
                </Button>

                <Link href="/login" className="block text-center text-xs text-muted-foreground hover:text-foreground">
                    Back to sign in
                </Link>
            </form>
        </AuthLayout>
    );
}
