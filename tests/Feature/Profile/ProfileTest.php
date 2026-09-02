<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

beforeEach(function (): void {
    $this->tenant = $this->tenant();
    $this->user = $this->userFor($this->tenant);
});

it('returns the signed-in user, their sessions and their tokens', function (): void {
    $this->user->createToken('Warehouse script');

    $response = $this->actingAs($this->user)->getJson('/api/profile');

    $response->assertOk()
        ->assertJsonPath('data.user.email', $this->user->email)
        ->assertJsonPath('data.tokens.0.name', 'Warehouse script')
        ->assertJsonStructure(['data' => ['user', 'ai', 'sessions', 'tokens']]);

    // The token value itself only ever exists at creation time.
    expect(json_encode($response->json()))->not->toContain('plain_text_token');
});

it('updates name, email and appearance', function (): void {
    $this->actingAs($this->user)->putJson('/api/profile', [
        'name' => 'Asha Rao',
        'email' => 'asha@brand.test',
        'theme' => 'dark',
        'accent_color' => 'emerald',
    ])->assertOk();

    $fresh = $this->user->fresh();

    expect($fresh->name)->toBe('Asha Rao')
        ->and($fresh->email)->toBe('asha@brand.test')
        ->and($fresh->theme)->toBe('dark')
        ->and($fresh->accent_color)->toBe('emerald');
});

it('refuses an email that another user already has', function (): void {
    $other = $this->userFor($this->tenant, [], 'ANALYST');

    $this->actingAs($this->user)->putJson('/api/profile', ['email' => $other->email])->assertStatus(422);
});

it('rejects an unknown accent or theme', function (): void {
    $this->actingAs($this->user)->putJson('/api/profile', ['accent_color' => 'neon'])->assertStatus(422);
    $this->actingAs($this->user)->putJson('/api/profile', ['theme' => 'sepia'])->assertStatus(422);
});

it('changes the password only with the correct current one', function (): void {
    $this->actingAs($this->user)->putJson('/api/profile/password', [
        'current_password' => 'wrong-password',
        'password' => 'a-new-password-99',
        'password_confirmation' => 'a-new-password-99',
    ])->assertStatus(422);

    $this->actingAs($this->user)->putJson('/api/profile/password', [
        'current_password' => 'password',
        'password' => 'a-new-password-99',
        'password_confirmation' => 'a-new-password-99',
    ])->assertOk();

    expect(Hash::check('a-new-password-99', $this->user->fresh()->password))->toBeTrue()
        ->and($this->user->fresh()->must_change_password)->toBeFalse();
});

it('enforces a password long enough to be worth having', function (): void {
    $this->actingAs($this->user)->putJson('/api/profile/password', [
        'current_password' => 'password',
        'password' => 'short1',
        'password_confirmation' => 'short1',
    ])->assertStatus(422);
});

it('signs other devices out when the password changes', function (): void {
    // The suite runs on the array session driver; this behaviour only exists
    // for the database driver the app actually ships with.
    config(['session.driver' => 'database']);

    DB::table('sessions')->insert([
        ['id' => 'other-device', 'user_id' => $this->user->id, 'ip_address' => '1.2.3.4', 'user_agent' => 'Mozilla/5.0 (Macintosh) Chrome/1', 'payload' => '', 'last_activity' => time()],
    ]);

    $this->actingAs($this->user)->putJson('/api/profile/password', [
        'current_password' => 'password',
        'password' => 'a-new-password-99',
        'password_confirmation' => 'a-new-password-99',
    ])->assertOk();

    expect(DB::table('sessions')->where('id', 'other-device')->exists())->toBeFalse();
});

it('creates a token once and revokes it', function (): void {
    $response = $this->actingAs($this->user)->postJson('/api/profile/tokens', ['name' => 'Warehouse script']);

    $response->assertOk();
    expect($response->json('data.plain_text_token'))->toBeString();

    $id = $response->json('data.id');

    $this->actingAs($this->user)->deleteJson("/api/profile/tokens/{$id}")->assertOk();

    expect($this->user->fresh()->tokens()->count())->toBe(0);
});

it('will not let a user revoke somebody else token', function (): void {
    $other = $this->userFor($this->tenant, [], 'ANALYST');
    $token = $other->createToken('Theirs');

    $this->actingAs($this->user)
        ->deleteJson('/api/profile/tokens/'.$token->accessToken->getKey())
        ->assertNotFound();

    expect($other->fresh()->tokens()->count())->toBe(1);
});

it('names the devices in the session list', function (): void {
    config(['session.driver' => 'database']);

    DB::table('sessions')->insert([
        ['id' => 'phone', 'user_id' => $this->user->id, 'ip_address' => '1.2.3.4', 'payload' => '', 'last_activity' => time(),
            'user_agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0) AppleWebKit/605 Safari/604.1'],
    ]);

    $sessions = $this->actingAs($this->user)->getJson('/api/profile')->json('data.sessions');

    expect(collect($sessions)->pluck('device'))->toContain('iPhone · Safari');
});
