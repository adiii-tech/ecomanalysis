import { router, useForm } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import { AuthLayout } from '@/layouts/auth-layout';
import { Button } from '@/components/ui/button';
import { Input, Label } from '@/components/ui/input';

export default function MfaChallenge() {
    const { data, setData, post, processing, errors } = useForm({ code: '' });

    return (
        <AuthLayout title="Two-factor code" description="Enter the 6-digit code from your authenticator app.">
            <form
                className="space-y-4"
                onSubmit={(event) => {
                    event.preventDefault();
                    post('/mfa/verify');
                }}
            >
                <div className="space-y-1.5">
                    <Label htmlFor="code">Authentication code</Label>
                    <Input
                        id="code"
                        autoFocus
                        inputMode="numeric"
                        autoComplete="one-time-code"
                        placeholder="123456"
                        className="text-center text-lg tracking-[0.4em] tnum"
                        value={data.code}
                        onChange={(event) => setData('code', event.target.value)}
                    />
                    {errors.code && <p className="text-xs text-bad">{errors.code}</p>}
                    <p className="text-[11px] text-muted-foreground">
                        Lost your device? Enter one of your recovery codes instead.
                    </p>
                </div>

                <Button type="submit" className="w-full" disabled={processing}>
                    {processing && <LoaderCircle className="animate-spin" />}
                    Verify
                </Button>

                <Button type="button" variant="ghost" className="w-full text-xs" onClick={() => router.post('/mfa/resend')}>
                    I don&rsquo;t have a code
                </Button>
            </form>
        </AuthLayout>
    );
}
