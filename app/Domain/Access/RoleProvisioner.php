<?php

declare(strict_types=1);

namespace App\Domain\Access;

use App\Models\Tenant;
use Illuminate\Support\Facades\Config;
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
