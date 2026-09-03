<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Access\RoleProvisioner;
use App\Models\Tenant;
use App\Support\TenantContext;
use Illuminate\Console\Command;

/**
 * Brings every tenant's roles up to date with the permission registry.
 *
 * Shipping a feature adds permissions to the registry, but existing tenants
 * already have their roles — without this, the new screens are invisible to
 * everyone including the owner. Run it on deploy; it is additive and safe to
 * run twice.
 */
class SyncPermissions extends Command
{
    protected $signature = 'permissions:sync {--tenant= : Limit to one tenant}';

    protected $description = 'Create newly registered permissions and grant them to the built-in roles that should have them.';

    public function handle(TenantContext $context, RoleProvisioner $provisioner): int
    {
        $created = 0;
        $granted = 0;

        $context->withoutScope(function () use ($provisioner, &$created, &$granted): void {
            Tenant::query()
                ->when($this->option('tenant'), fn ($q) => $q->whereKey($this->option('tenant')))
                ->each(function (Tenant $tenant) use ($provisioner, &$created, &$granted): void {
                    $result = $provisioner->syncNewPermissions($tenant);
                    $created = max($created, $result['permissions_created']);
                    $granted += $result['grants_added'];

                    $this->line(sprintf('  %s: %d grants added', $tenant->name, $result['grants_added']));
                });
        });

        $this->info(sprintf('%d new permissions registered, %d role grants added.', $created, $granted));

        return self::SUCCESS;
    }
}
