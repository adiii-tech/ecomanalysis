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
 *
 * Tables are read with chunkById rather than get(), and flushed after every
 * chunk: a tenant can have tens of thousands of customers or SKUs, and
 * loading a table like that into one Collection — or letting PHP buffer the
 * whole response until the script ends — is exactly what times a request
 * out or exhausts its memory limit on a tenant big enough to matter.
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

    private const CHUNK_SIZE = 500;

    /**
     * Spatie's pivot tables have no single id column, so chunkById (which
     * paginates by id) cannot page them — but both are bounded by user and
     * role counts, never large enough to need chunking anyway.
     *
     * @var list<string>
     */
    private const NO_ID_TABLES = ['model_has_roles', 'model_has_permissions'];

    /**
     * @param  resource  $handle
     */
    public function export(Tenant $tenant, $handle): void
    {
        fwrite($handle, "-- ecomanalysis backup -- tenant '{$tenant->name}' (#{$tenant->id})\n");
        fwrite($handle, '-- generated '.now()->toIso8601String()."\n");
        fwrite($handle, "-- Secrets (connector credentials, WhatsApp/AI tokens, MFA) are redacted — reconnect them from Admin after importing.\n\n");
        fwrite($handle, "SET FOREIGN_KEY_CHECKS=0;\n");
        fwrite($handle, "START TRANSACTION;\n\n");
        $this->flush($handle);

        $this->dumpSmall($handle, 'tenants', DB::table('tenants')->where('id', $tenant->id)->get());

        foreach ([...ClearTenantData::DATA_TABLES, ...ClearTenantData::KEPT_TABLES] as $table) {
            if (in_array($table, self::NO_ID_TABLES, true)) {
                fwrite($handle, "DELETE FROM `{$table}` WHERE `tenant_id` = {$tenant->id};\n");
                $this->dumpSmall($handle, $table, DB::table($table)->where('tenant_id', $tenant->id)->get());

                continue;
            }

            $this->dumpChunked($handle, $table, 'tenant_id', $tenant->id);
        }

        // Role-permission assignments are keyed by role_id, not tenant_id —
        // the one tenant-owned pivot that doesn't carry the column itself.
        // Always small (roles x permissions), so no chunking needed.
        $roleIds = DB::table('roles')->where('tenant_id', $tenant->id)->pluck('id');
        fwrite($handle, 'DELETE FROM `role_has_permissions` WHERE `role_id` IN ('.($roleIds->implode(',') ?: '0').");\n");
        $this->dumpSmall($handle, 'role_has_permissions', DB::table('role_has_permissions')->whereIn('role_id', $roleIds)->get());

        fwrite($handle, "\nCOMMIT;\n");
        fwrite($handle, "SET FOREIGN_KEY_CHECKS=1;\n");
        $this->flush($handle);
    }

    /**
     * For tables that can genuinely grow large (orders, customers, skus,
     * rollups, …). chunkById keeps memory bounded to one chunk regardless of
     * table size, and a flush after every chunk keeps the connection fed so
     * a reverse proxy watching for idle bytes never has a reason to cut it.
     *
     * @param  resource  $handle
     */
    private function dumpChunked($handle, string $table, string $column, int $tenantId): void
    {
        fwrite($handle, "DELETE FROM `{$table}` WHERE `{$column}` = {$tenantId};\n");

        $redacted = self::REDACT_COLUMNS[$table] ?? [];
        $wroteHeader = false;

        DB::table($table)->where($column, $tenantId)
            ->chunkById(self::CHUNK_SIZE, function (Collection $rows) use ($handle, $table, $redacted, &$wroteHeader): void {
                if (! $wroteHeader) {
                    fwrite($handle, "-- {$table}\n");
                    $wroteHeader = true;
                }

                $this->writeInsert($handle, $table, $rows, $redacted);
                $this->flush($handle);
            });

        if ($wroteHeader) {
            fwrite($handle, "\n");
        }
    }

    /**
     * For the handful of tables guaranteed to stay small regardless of
     * tenant size (a single tenant row, role/permission pivots) — no point
     * chunking what is at most a few hundred rows.
     *
     * @param  resource  $handle
     * @param  Collection<int, object>  $rows
     */
    private function dumpSmall($handle, string $table, Collection $rows): void
    {
        if ($rows->isEmpty()) {
            return;
        }

        fwrite($handle, "-- {$table}\n");
        $this->writeInsert($handle, $table, $rows, self::REDACT_COLUMNS[$table] ?? []);
        fwrite($handle, "\n");
    }

    /**
     * @param  resource  $handle
     * @param  Collection<int, object>  $rows
     * @param  list<string>  $redacted
     */
    private function writeInsert($handle, string $table, Collection $rows, array $redacted): void
    {
        $columns = array_keys((array) $rows->first());
        $columnList = implode(', ', array_map(static fn (string $c): string => "`{$c}`", $columns));

        $tuples = $rows->map(function (object $row) use ($columns, $redacted): string {
            $values = array_map(
                fn (string $column): string => in_array($column, $redacted, true) ? 'NULL' : $this->quote($row->{$column}),
                $columns,
            );

            return '('.implode(', ', $values).')';
        });

        fwrite($handle, "INSERT INTO `{$table}` ({$columnList}) VALUES\n".$tuples->implode(",\n").";\n");
    }

    private function quote(mixed $value): string
    {
        if ($value === null) {
            return 'NULL';
        }

        return DB::connection()->getPdo()->quote((string) $value);
    }

    /** @param resource $handle */
    private function flush($handle): void
    {
        fflush($handle);

        if (ob_get_level() > 0) {
            ob_flush();
        }

        flush();
    }
}
