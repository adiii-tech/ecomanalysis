<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Tenant;
use RuntimeException;

/**
 * Holds the tenant for the current request / job. Registered as a singleton so
 * the BelongsToTenant global scope and every query can reach it without
 * threading a tenant id through every call.
 */
final class TenantContext
{
    private ?Tenant $tenant = null;

    private bool $suppressed = false;

    public function set(?Tenant $tenant): void
    {
        $this->tenant = $tenant;
    }

    public function get(): ?Tenant
    {
        return $this->tenant;
    }

    public function id(): ?int
    {
        return $this->tenant?->id;
    }

    public function requireId(): int
    {
        return $this->id() ?? throw new RuntimeException('No tenant resolved for the current context.');
    }

    public function require(): Tenant
    {
        return $this->tenant ?? throw new RuntimeException('No tenant resolved for the current context.');
    }

    public function has(): bool
    {
        return $this->tenant !== null;
    }

    public function isSuppressed(): bool
    {
        return $this->suppressed;
    }

    public function timezone(): string
    {
        return $this->tenant?->timezone ?? config('app.timezone', 'UTC');
    }

    /**
     * Run a callback with the tenant scope disabled (admin/console work only).
     *
     * @template TReturn
     *
     * @param  callable(): TReturn  $callback
     * @return TReturn
     */
    public function withoutScope(callable $callback): mixed
    {
        $previous = $this->suppressed;
        $this->suppressed = true;

        try {
            return $callback();
        } finally {
            $this->suppressed = $previous;
        }
    }

    /**
     * Run a callback as a specific tenant, restoring the previous one afterwards.
     *
     * @template TReturn
     *
     * @param  callable(): TReturn  $callback
     * @return TReturn
     */
    public function runAs(Tenant $tenant, callable $callback): mixed
    {
        $previous = $this->tenant;
        $this->tenant = $tenant;
        setPermissionsTeamId($tenant->id);

        try {
            return $callback();
        } finally {
            $this->tenant = $previous;
            setPermissionsTeamId($previous?->id);
        }
    }
}
