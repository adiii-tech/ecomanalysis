import { useForm } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import { AuthLayout } from '@/layouts/auth-layout';
import { Button } from '@/components/ui/button';
import { Input, Label } from '@/components/ui/input';

export default function ChangePassword() {
    const { data, setData, post, processing, errors } = useForm({
        current_password: '',
        password: '',
        password_confirmation: '',
    });

    return (
        <AuthLayout title="Choose a new password" description="An admin has asked you to reset it before continuing.">
            <form
                className="space-y-4"
                onSubmit={(event) => {
                    event.preventDefault();
                    post('/password/change');
                }}
            >
                <div className="space-y-1.5">
                    <Label htmlFor="current_password">Current password</Label>
                    <Input
                        id="current_password"
                        type="password"
                        autoFocus
                        value={data.current_password}
                        onChange={(event) => setData('current_password', event.target.value)}
                    />
                    {errors.current_password && <p className="text-xs text-bad">{errors.current_password}</p>}
                </div>

                <div className="space-y-1.5">
                    <Label htmlFor="password">New password</Label>
                    <Input id="password" type="password" value={data.password} onChange={(event) => setData('password', event.target.value)} />
                    {errors.password && <p className="text-xs text-bad">{errors.password}</p>}
                </div>

                <div className="space-y-1.5">
                    <Label htmlFor="password_confirmation">Confirm new password</Label>
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
