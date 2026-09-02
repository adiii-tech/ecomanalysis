<?php

declare(strict_types=1);

namespace Tests;

use App\Domain\Access\PermissionRegistry;
use App\Domain\Access\RoleRegistry;
use App\Enums\Plan;
use App\Models\Tenant;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

abstract class TestCase extends BaseTestCase
{
    protected function tenant(array $attributes = []): Tenant
    {
        $tenant = Tenant::query()->create([
            'name' => 'Test Brand',
            'slug' => 'test-brand-'.uniqid(),
            'currency' => 'INR',
            'timezone' => 'Asia/Kolkata',
            'plan' => Plan::Growth,
            'is_demo' => false,
            ...$attributes,
        ]);

        app(TenantContext::class)->set($tenant);
        setPermissionsTeamId($tenant->id);

        return $tenant;
    }

    /**
     * Seeds only the permissions a test actually needs, keeping the suite fast.
     *
     * @param  list<string>  $permissions
     */
    protected function seedPermissions(array $permissions = []): void
    {
        $names = $permissions === [] ? PermissionRegistry::all() : $permissions;
        $existing = Permission::query()->pluck('name')->all();

        $rows = array_map(static fn (string $name): array => [
            'name' => $name, 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now(),
        ], array_values(array_diff($names, $existing)));

        foreach (array_chunk($rows, 200) as $chunk) {
            DB::table('permissions')->insert($chunk);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * @param  list<string>  $permissions
     */
    protected function userFor(Tenant $tenant, array $permissions = [], string $role = RoleRegistry::OWNER): User
    {
        setPermissionsTeamId($tenant->id);
        $this->seedPermissions($permissions);

        $user = User::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Test User',
            'email' => 'user'.uniqid().'@example.test',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);

        $roleModel = Role::findOrCreate($role, 'web');
        $roleModel->syncPermissions($permissions === [] ? RoleRegistry::permissionsFor($role) : $permissions);
        $user->syncRoles([$role]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $user->fresh();
    }
}
