<?php

declare(strict_types=1);

namespace App\Support;

use App\Console\Commands\ClearTenantData;
use App\Models\Tenant;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Generates a restorable SQL dump of everything a single tenant owns, for
 * pulling a tenant's data down into another database to reproduce a bug.
 * Shares its table list with ClearTenantData so "what counts as this
 * tenant's data" is never defined in two places that can drift apart.
 *
 * Every encrypted-at-rest column is dropped rather than exported: a
 * different APP_KEY on the receiving end cannot decrypt it anyway — the row
 * would just throw on read — and a matching key would mean live API
 * credentials sitting in a downloadable file, able to authenticate as the
 * live store from wherever the file ends up.
 */
class TenantBackupExporter
{
    /** @var array<string, list<string>> */
    private const REDACT_COLUMNS = [
        'connectors' => ['credentials'],
        'customers' => ['email_encrypted', 'phone_encrypted'],
        'users' => ['mfa_secret'],
        'notification_settings' => ['whatsapp_token'],
        'ai_settings' => ['api_key'],
    ];

    private const ROWS_PER_INSERT = 200;

    /**
     * Streams the dump straight to the given handle rather than building the
     * whole file in memory — a tenant with a few thousand orders spread
     * across forty tables can add up.
     *
     * @param  resource  $handle
     */
    public function export(Tenant $tenant, $handle): void
    {
        fwrite($handle, "-- ecomanalysis backup -- tenant '{$tenant->name}' (#{$tenant->id})\n");
        fwrite($handle, '-- generated '.now()->toIso8601String()."\n");
        fwrite($handle, "-- Secrets (connector credentials, WhatsApp/AI tokens, MFA) are redacted — reconnect them from Admin after importing.\n\n");
        fwrite($handle, "SET FOREIGN_KEY_CHECKS=0;\n");
        fwrite($handle, "START TRANSACTION;\n\n");

        $this->dumpScoped($handle, 'tenants', 'id', $tenant->id);

        foreach ([...ClearTenantData::DATA_TABLES, ...ClearTenantData::KEPT_TABLES] as $table) {
            $this->dumpScoped($handle, $table, 'tenant_id', $tenant->id);
        }

        // Role-permission assignments are keyed by role_id, not tenant_id —
        // the one tenant-owned pivot that doesn't carry the column itself.
        $roleIds = DB::table('roles')->where('tenant_id', $tenant->id)->pluck('id');
        fwrite($handle, 'DELETE FROM `role_has_permissions` WHERE `role_id` IN ('.($roleIds->implode(',') ?: '0').");\n");
        $this->writeRows($handle, 'role_has_permissions', DB::table('role_has_permissions')->whereIn('role_id', $roleIds)->get());

        fwrite($handle, "\nCOMMIT;\n");
        fwrite($handle, "SET FOREIGN_KEY_CHECKS=1;\n");
    }

    /** @param resource $handle */
    private function dumpScoped($handle, string $table, string $column, int $tenantId): void
    {
        fwrite($handle, "DELETE FROM `{$table}` WHERE `{$column}` = {$tenantId};\n");
        $this->writeRows($handle, $table, DB::table($table)->where($column, $tenantId)->get());
    }

    /**
     * @param  resource  $handle
     * @param  Collection<int, object>  $rows
     */
    private function writeRows($handle, string $table, Collection $rows): void
    {
        if ($rows->isEmpty()) {
            return;
        }

        $redacted = self::REDACT_COLUMNS[$table] ?? [];
        $columns = array_keys((array) $rows->first());
        $columnList = implode(', ', array_map(static fn (string $c): string => "`{$c}`", $columns));

        fwrite($handle, "-- {$table} ({$rows->count()} row".($rows->count() === 1 ? '' : 's').")\n");

        foreach ($rows->chunk(self::ROWS_PER_INSERT) as $chunk) {
            $tuples = $chunk->map(function (object $row) use ($columns, $redacted): string {
                $values = array_map(
                    fn (string $column): string => in_array($column, $redacted, true) ? 'NULL' : $this->quote($row->{$column}),
                    $columns,
                );

                return '('.implode(', ', $values).')';
            });

            fwrite($handle, "INSERT INTO `{$table}` ({$columnList}) VALUES\n".$tuples->implode(",\n").";\n");
        }

        fwrite($handle, "\n");
    }

    private function quote(mixed $value): string
    {
        if ($value === null) {
            return 'NULL';
        }

        return DB::connection()->getPdo()->quote((string) $value);
    }
}
