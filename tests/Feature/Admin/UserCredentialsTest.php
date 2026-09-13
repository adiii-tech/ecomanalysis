<?php

declare(strict_types=1);

use App\Domain\Access\RoleRegistry;
use App\Models\ActivityLog as Activity;
use Illuminate\Support\Facades\Hash;

beforeEach(function (): void {
    $this->tenant = $this->tenant();
    $this->admin = $this->userFor($this->tenant);
    $this->member = $this->userFor($this->tenant, [], RoleRegistry::ANALYST);
});

it('changes another user email and password without asking for their password', function (): void {
    $this->actingAs($this->admin)
        ->putJson("/api/admin/users/{$this->member->id}", [
            'email' => 'renamed@example.test',
            'password' => 'brandnew12pass',
            'password_confirmation' => 'brandnew12pass',
        ])
        ->assertOk();

    $member = $this->member->fresh();

    expect($member->email)->toBe('renamed@example.test')
        ->and(Hash::check('brandnew12pass', $member->password))->toBeTrue()
        // Someone else's password is a handover, so they set their own at next sign-in.
        ->and($member->must_change_password)->toBeTrue();
});

it('refuses to change your own email or password without your current one', function (): void {
    $this->actingAs($this->admin)
        ->putJson("/api/admin/users/{$this->admin->id}", ['email' => 'newme@example.test'])
        ->assertStatus(422);

    $this->actingAs($this->admin)
        ->putJson("/api/admin/users/{$this->admin->id}", [
            'password' => 'brandnew12pass',
            'password_confirmation' => 'brandnew12pass',
            'current_password' => 'not-my-password',
        ])
        ->assertStatus(422);

    expect($this->admin->fresh()->email)->toBe($this->admin->email)
        ->and(Hash::check('password', $this->admin->fresh()->password))->toBeTrue();
});

it('changes your own email and password once the current one checks out', function (): void {
    $this->actingAs($this->admin)
        ->putJson("/api/admin/users/{$this->admin->id}", [
            'email' => 'newme@example.test',
            'password' => 'brandnew12pass',
            'password_confirmation' => 'brandnew12pass',
            'current_password' => 'password',
        ])
        ->assertOk();

    $admin = $this->admin->fresh();

    expect($admin->email)->toBe('newme@example.test')
        ->and(Hash::check('brandnew12pass', $admin->password))->toBeTrue()
        // You chose it yourself, so there is nothing to force at next sign-in.
        ->and($admin->must_change_password)->toBeFalse();
});

it('keeps other admin edits working without a current password', function (): void {
    $this->actingAs($this->admin)
        ->putJson("/api/admin/users/{$this->member->id}", ['name' => 'Renamed Analyst'])
        ->assertOk();

    expect($this->member->fresh()->name)->toBe('Renamed Analyst');
});

it('rejects a weak password or an email somebody else already has', function (): void {
    $this->actingAs($this->admin)
        ->putJson("/api/admin/users/{$this->member->id}", ['password' => 'short1', 'password_confirmation' => 'short1'])
        ->assertStatus(422);

    $this->actingAs($this->admin)
        ->putJson("/api/admin/users/{$this->member->id}", ['email' => $this->admin->email])
        ->assertStatus(422);
});

it('records the change without writing the credential into the audit trail', function (): void {
    $this->actingAs($this->admin)
        ->putJson("/api/admin/users/{$this->member->id}", [
            'password' => 'brandnew12pass',
            'password_confirmation' => 'brandnew12pass',
        ])
        ->assertOk();

    $entry = Activity::query()->where('description', 'user.updated')->latest('id')->firstOrFail();

    expect($entry->properties['fields'])->toContain('password')
        ->and(json_encode($entry->properties))->not->toContain('brandnew12pass')
        ->and(json_encode($entry->properties))->not->toContain('$2y$');
});
