<?php

declare(strict_types=1);

namespace App\Domain\Access;

use App\Models\Tenant;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Roles live per tenant (spatie teams mode), so every tenant needs its own copy
 * of the built-in set before anyone can be invited or reassigned. Provisioning
 * is idempotent and safe to call on any path that touches a role name.
 */
class RoleProvisioner
{
    public function ensureFor(Tenant|int $tenant): void
    {
        $tenantId = $tenant instanceof Tenant ? $tenant->id : $tenant;

        setPermissionsTeamId($tenantId);

        $existing = Role::query()->where($this->teamKey(), $tenantId)->pluck('name')->all();
        $missing = array_diff(RoleRegistry::names(), $existing);

        if ($missing === []) {
            return;
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach ($missing as $name) {
            Role::findOrCreate($name, 'web')->syncPermissions(RoleRegistry::permissionsFor($name));
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Grants permissions that have appeared in the registry since a tenant's
     * roles were created.
     *
     * Additive only: a permission is granted to a built-in role when its
     * definition calls for it and the role does not have it yet. Nothing is
     * ever removed, because an admin may have deliberately tightened a role and
     * a deploy should not quietly undo that.
     *
     * @return array{permissions_created: int, grants_added: int}
     */
    public function syncNewPermissions(Tenant|int $tenant): array
    {
        $tenantId = $tenant instanceof Tenant ? $tenant->id : $tenant;

        setPermissionsTeamId($tenantId);
        $this->ensureFor($tenantId);

        $created = $this->createMissingPermissions();
        $granted = 0;

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (RoleRegistry::names() as $name) {
            $role = Role::query()->where($this->teamKey(), $tenantId)->where('name', $name)->first();

            if ($role === null) {
                continue;
            }

            $missing = array_diff(RoleRegistry::permissionsFor($name), $role->permissions->pluck('name')->all());

            foreach ($missing as $permission) {
                $role->givePermissionTo($permission);
                $granted++;
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return ['permissions_created' => $created, 'grants_added' => $granted];
    }

    /** Permission rows are global; roles that point at them are per tenant. */
    private function createMissingPermissions(): int
    {
        $existing = Permission::query()->pluck('name')->all();
        $missing = array_values(array_diff(PermissionRegistry::all(), $existing));

        foreach (array_chunk($missing, 200) as $chunk) {
            DB::table('permissions')->insert(array_map(static fn (string $name): array => [
                'name' => $name, 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now(),
            ], $chunk));
        }

        return count($missing);
    }

    /**
     * Every role a tenant may assign: the built-in set plus any custom roles an
     * admin has added.
     *
     * @return list<string>
     */
    public function assignableFor(Tenant|int $tenant): array
    {
        $tenantId = $tenant instanceof Tenant ? $tenant->id : $tenant;

        $custom = Role::query()->where($this->teamKey(), $tenantId)->pluck('name')->all();

        return array_values(array_unique([...RoleRegistry::names(), ...$custom]));
    }

    private function teamKey(): string
    {
        return Config::string('permission.column_names.team_foreign_key', 'team_id');
    }
}
