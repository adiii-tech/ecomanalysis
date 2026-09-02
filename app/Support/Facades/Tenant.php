<?php

declare(strict_types=1);

namespace App\Support\Facades;

use App\Models\Tenant as TenantModel;
use App\Support\TenantContext;

/**
 * Thin static accessor for the current tenant, for the places where injecting
 * TenantContext would only add noise.
 */
final class Tenant
{
    public static function id(): int
    {
        return app(TenantContext::class)->requireId();
    }

    public static function current(): TenantModel
    {
        return app(TenantContext::class)->require();
    }

    public static function timezone(): string
    {
        return app(TenantContext::class)->timezone();
    }
}
