<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Domain\Access\RoleProvisioner;
use App\Http\Controllers\Controller;
use App\Models\Invitation;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class InvitationController extends Controller
{
    public function __construct(private readonly TenantContext $context) {}

    public function show(string $token): Response|RedirectResponse
    {
        $invitation = $this->resolve($token);

        if ($invitation === null) {
            return redirect()->route('login')->with('error', 'That invite link is no longer valid.');
        }

        return Inertia::render('auth/accept-invite', [
            'token' => $token,
            'email' => $invitation->email,
            'name' => $invitation->name,
            'role' => $invitation->role,
            'tenant' => $invitation->tenant->name,
        ]);
    }

    public function accept(Request $request, string $token): RedirectResponse
    {
        $invitation = $this->resolve($token);

        if ($invitation === null) {
            throw ValidationException::withMessages(['token' => 'That invite link is no longer valid.']);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $user = $this->context->runAs($invitation->tenant, function () use ($invitation, $data): User {
            $user = User::query()->create([
                'tenant_id' => $invitation->tenant_id,
                'name' => $data['name'],
                'email' => $invitation->email,
                'password' => Hash::make($data['password']),
                'is_active' => true,
            ]);

            $user->forceFill(['email_verified_at' => now()])->save();
            // The tenant may never have used this role before.
            app(RoleProvisioner::class)->ensureFor($invitation->tenant_id);
            $user->syncRoles([$invitation->role]);

            return $user;
        });

        $invitation->forceFill(['accepted_at' => now()])->save();

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('dashboard')->with('success', 'Welcome aboard.');
    }

    private function resolve(string $token): ?Invitation
    {
        return $this->context->withoutScope(fn (): ?Invitation => Invitation::query()
            ->with('tenant')
            ->where('token', $token)
            ->whereNull('accepted_at')
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now())
            ->first());
    }
}
