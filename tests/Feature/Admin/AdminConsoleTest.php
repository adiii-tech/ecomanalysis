<?php

declare(strict_types=1);

use App\Domain\Access\RoleRegistry;
use App\Models\ActivityLog as Activity;
use App\Models\CostSetting;
use App\Support\MetricCache;
use App\Support\Money;
use App\Support\TenantContext;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    $this->tenant = $this->tenant();
    $this->admin = $this->userFor($this->tenant);
});

it('lists users, seats and pending invitations', function (): void {
    $this->userFor($this->tenant, [], RoleRegistry::ANALYST);

    $response = $this->actingAs($this->admin)->getJson('/api/admin/users');

    $response->assertOk()
        ->assertJsonStructure(['data' => ['users', 'invitations', 'roles', 'seat_limit', 'seats_used']]);

    expect($response->json('data.seats_used'))->toBe(2)
        ->and($response->json('data.roles'))->toContain('OWNER', 'ANALYST');
});

it('creates an invite and returns a usable accept link', function (): void {
    $response = $this->actingAs($this->admin)->postJson('/api/admin/users/invite', [
        'email' => 'newhire@example.test',
        'role' => 'FINANCE',
    ]);

    $response->assertOk();
    $url = $response->json('data.accept_url');

    expect($url)->toContain('/accept-invite/');

    // The link the admin was handed must actually work for the invitee, who
    // arrives signed out.
    $this->app['auth']->forgetGuards();
    $this->flushSession();
    $this->get($url)->assertOk();

    expect(Activity::query()->where('description', 'user.invited')->exists())->toBeTrue();
});

it('refuses to invite past the plan seat limit', function (): void {
    $this->tenant->forceFill(['plan' => 'starter'])->save();

    // Starter allows 3 seats; fill them.
    $this->userFor($this->tenant, [], RoleRegistry::ANALYST);
    $this->userFor($this->tenant, [], RoleRegistry::ANALYST);

    $this->actingAs($this->admin)->postJson('/api/admin/users/invite', [
        'email' => 'fourth@example.test', 'role' => 'ANALYST',
    ])->assertStatus(422);
});

it('will not let an admin disable their own account', function (): void {
    $this->actingAs($this->admin)
        ->putJson("/api/admin/users/{$this->admin->id}", ['is_active' => false])
        ->assertStatus(422);

    expect($this->admin->fresh()->is_active)->toBeTrue();
});

it('resets a password and forces a change at next sign-in', function (): void {
    $target = $this->userFor($this->tenant, [], RoleRegistry::ANALYST);

    $response = $this->actingAs($this->admin)->postJson("/api/admin/users/{$target->id}/password");

    $response->assertOk();
    expect($response->json('data.temporary_password'))->toBeString()
        ->and($target->fresh()->must_change_password)->toBeTrue();
});

it('shows role-granted permissions separately from overrides', function (): void {
    $analyst = $this->userFor($this->tenant, [], RoleRegistry::ANALYST);

    $response = $this->actingAs($this->admin)->getJson("/api/admin/users/{$analyst->id}/permissions");
    $response->assertOk();

    $dashboard = collect($response->json('data.modules'))->firstWhere('module', 'dashboard');
    $view = collect($dashboard['permissions'])->firstWhere('permission', 'dashboard.kpi_strip.view');
    $manage = collect($response->json('data.modules'))
        ->firstWhere('module', 'admin')['permissions'];

    expect($view['from_role'])->toBeTrue()
        ->and($view['granted_directly'])->toBeFalse()
        // An analyst must not have admin manage rights from their role.
        ->and(collect($manage)->firstWhere('permission', 'admin.users.manage')['effective'])->toBeFalse();
});

it('grants an override and bumps the permissions version so the SPA re-reads', function (): void {
    $analyst = $this->userFor($this->tenant, [], RoleRegistry::ANALYST);
    $before = $analyst->permissions_version;

    expect($analyst->can('finance.pnl.export'))->toBeFalse();

    $this->actingAs($this->admin)
        ->putJson("/api/admin/users/{$analyst->id}/permissions", ['grant' => ['finance.pnl.export']])
        ->assertOk();

    $fresh = $analyst->fresh();
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    expect($fresh->can('finance.pnl.export'))->toBeTrue()
        ->and($fresh->permissions_version)->toBeGreaterThan($before);
});

it('resets overrides back to the role', function (): void {
    $analyst = $this->userFor($this->tenant, [], RoleRegistry::ANALYST);

    $this->actingAs($this->admin)->putJson("/api/admin/users/{$analyst->id}/permissions", ['grant' => ['finance.pnl.export']]);
    $this->actingAs($this->admin)->postJson("/api/admin/users/{$analyst->id}/permissions/reset")->assertOk();

    app(PermissionRegistrar::class)->forgetCachedPermissions();

    expect($analyst->fresh()->can('finance.pnl.export'))->toBeFalse();
});

it('rejects a permission that is not in the registry', function (): void {
    $analyst = $this->userFor($this->tenant, [], RoleRegistry::ANALYST);

    $this->actingAs($this->admin)
        ->putJson("/api/admin/users/{$analyst->id}/permissions", ['grant' => ['orders.delete.everything']])
        ->assertStatus(422);
});

it('saves cost settings in paise and busts the cached widgets', function (): void {
    CostSetting::query()->create(['tenant_id' => $this->tenant->id]);

    $cache = app(MetricCache::class);
    $versionBefore = $cache->version($this->tenant->id);

    $this->actingAs($this->admin)->putJson('/api/admin/settings', [
        'cost_settings' => ['packaging_cost' => 25.5, 'gateway_fee_pct' => 2.4],
        'benchmarks' => ['target_roas' => 4.0],
    ])->assertOk();

    $costs = CostSetting::query()->where('tenant_id', $this->tenant->id)->first();

    expect($costs->packaging_cost)->toBe(Money::fromRupees(25.5))
        ->and((float) $costs->gateway_fee_pct)->toBe(2.4)
        // Changing cost settings moves every margin number, so the cache must go.
        ->and($cache->version($this->tenant->id))->toBeGreaterThan($versionBefore);
});

it('returns settings in rupees, not paise', function (): void {
    CostSetting::query()->create(['tenant_id' => $this->tenant->id, 'packaging_cost' => 2550]);

    $this->actingAs($this->admin)->getJson('/api/admin/settings')
        ->assertOk()
        ->assertJsonPath('data.cost_settings.packaging_cost', 25.5);
});

it('keeps the admin console away from non-admin roles', function (): void {
    $analyst = $this->userFor($this->tenant, [], RoleRegistry::ANALYST);

    $this->actingAs($analyst)->getJson('/api/admin/users')->assertForbidden();
    $this->actingAs($analyst)->getJson('/api/admin/audit')->assertForbidden();
    $this->actingAs($analyst)->putJson('/api/admin/settings', [])->assertForbidden();
});

it('records admin actions in the audit log', function (): void {
    $target = $this->userFor($this->tenant, [], RoleRegistry::ANALYST);

    $this->actingAs($this->admin)->putJson("/api/admin/users/{$target->id}", ['name' => 'Renamed']);

    $response = $this->actingAs($this->admin)->getJson('/api/admin/audit');
    $response->assertOk();

    expect(collect($response->json('data.rows'))->pluck('description'))->toContain('user.updated');
});

it('lists roles with real user and permission counts', function (): void {
    $this->userFor($this->tenant, [], RoleRegistry::ANALYST);
    $this->userFor($this->tenant, [], RoleRegistry::ANALYST);

    $response = $this->actingAs($this->admin)->getJson('/api/admin/roles');
    $response->assertOk();

    $rows = collect($response->json('data.rows'));
    $analyst = $rows->firstWhere('name', 'ANALYST');

    expect($rows)->toHaveCount(count(RoleRegistry::names()))
        ->and($analyst['users_count'])->toBe(2)
        ->and($analyst['permission_count'])->toBeGreaterThan(0)
        ->and($analyst['is_builtin'])->toBeTrue()
        // Every built-in role is offered even before anyone is in it.
        ->and($rows->firstWhere('name', 'MARKETING')['users_count'])->toBe(0);
});

it('never shows another tenant roles, users or audit trail', function (): void {
    $other = $this->tenant(['name' => 'Rival Brand']);
    $rival = $this->userFor($other);
    $this->actingAs($rival)->putJson("/api/admin/users/{$rival->id}", ['name' => 'Rival Owner']);

    app(TenantContext::class)->set($this->tenant);
    setPermissionsTeamId($this->tenant->id);

    $users = $this->actingAs($this->admin)->getJson('/api/admin/users');
    $audit = $this->actingAs($this->admin)->getJson('/api/admin/audit');

    expect(collect($users->json('data.users'))->pluck('email'))->not->toContain($rival->email)
        ->and(collect($audit->json('data.rows'))->pluck('causer'))->not->toContain('Rival Owner');
});

it('saves a custom role and refuses to rename a built-in one', function (): void {
    $this->actingAs($this->admin)->postJson('/api/admin/roles', [
        'name' => 'WAREHOUSE',
        'permissions' => ['operations.rto_by_state.view'],
    ])->assertOk();

    $roles = $this->actingAs($this->admin)->getJson('/api/admin/roles')->json('data.rows');
    $warehouse = collect($roles)->firstWhere('name', 'WAREHOUSE');

    expect($warehouse['is_builtin'])->toBeFalse()
        ->and($warehouse['permission_count'])->toBe(1);

    $owner = collect($roles)->firstWhere('name', 'OWNER');

    $this->actingAs($this->admin)
        ->putJson("/api/admin/roles/{$owner['id']}", ['name' => 'BOSS', 'permissions' => []])
        ->assertStatus(422);
});
