import { useForm } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import { AuthLayout } from '@/layouts/auth-layout';
import { Button } from '@/components/ui/button';
import { Input, Label } from '@/components/ui/input';

export default function ResetPassword({ token, email }: { token: string; email: string }) {
    const { data, setData, post, processing, errors } = useForm({
        token,
        email,
        password: '',
        password_confirmation: '',
    });

    return (
        <AuthLayout title="Set a new password">
            <form
                className="space-y-4"
                onSubmit={(event) => {
                    event.preventDefault();
                    post('/reset-password');
                }}
            >
                <div className="space-y-1.5">
                    <Label htmlFor="email">Email</Label>
                    <Input id="email" type="email" value={data.email} onChange={(event) => setData('email', event.target.value)} readOnly />
                    {errors.email && <p className="text-xs text-bad">{errors.email}</p>}
                </div>

                <div className="space-y-1.5">
                    <Label htmlFor="password">New password</Label>
                    <Input id="password" type="password" autoFocus value={data.password} onChange={(event) => setData('password', event.target.value)} />
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
                    Update password
                </Button>
            </form>
        </AuthLayout>
    );
}
