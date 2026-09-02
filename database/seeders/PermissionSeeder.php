<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Access\PermissionRegistry;
use App\Domain\Access\RoleProvisioner;
use App\Models\Tenant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Seeds the full permission catalogue globally, then the built-in roles for a
 * tenant. Permissions are tenant-agnostic; roles are per-tenant so a tenant can
 * customise them without affecting anyone else.
 */
class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedPermissions();

        Tenant::query()->each(fn (Tenant $tenant) => $this->seedRolesFor($tenant));
    }

    public function seedPermissions(): void
    {
        $existing = Permission::query()->pluck('name')->all();
        $missing = array_diff(PermissionRegistry::all(), $existing);

        $rows = array_map(static fn (string $name): array => [
            'name' => $name,
            'guard_name' => 'web',
            'created_at' => now(),
            'updated_at' => now(),
        ], array_values($missing));

        foreach (array_chunk($rows, 200) as $chunk) {
            DB::table('permissions')->insert($chunk);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function seedRolesFor(Tenant $tenant): void
    {
        app(RoleProvisioner::class)->ensureFor($tenant);
    }
}
