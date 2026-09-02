<?php

declare(strict_types=1);

namespace App\Support;

use Closure;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Facades\Cache;
use JsonSerializable;
use stdClass;

/**
 * Dashboard read-through cache keyed by
 * `tenant:module:widget:from:to:channel:basis`.
 *
 * Invalidation uses a per-tenant version stamp folded into every key rather
 * than cache tags, so it works on the database and file stores as well as
 * Redis. Busting is then a single increment instead of a tag sweep.
 */
final class MetricCache
{
    private static ?string $buildId = null;

    public const TTL_SECONDS = 300;

    public function __construct(private readonly TenantContext $context) {}

    /**
     * @template TValue
     *
     * @param  array<string, mixed>  $filters
     * @param  Closure(): TValue  $callback
     * @return TValue
     */
    public function remember(string $module, string $widget, array $filters, Closure $callback, ?int $ttl = null): mixed
    {
        $tenantId = $this->context->id();

        if ($tenantId === null) {
            return $callback();
        }

        return Cache::remember(
            $this->key($tenantId, $module, $widget, $filters),
            $ttl ?? self::TTL_SECONDS,
            fn (): mixed => self::normalise($callback()),
        );
    }

    /**
     * Widget payloads are cached as plain arrays and scalars.
     *
     * The cache store unserialises with classes disallowed, so *no* object
     * survives a round trip — a Collection or even a plain stdClass comes back
     * as __PHP_Incomplete_Class and reaches the frontend as the wrong shape.
     * Flattening everything to arrays on the way in makes that impossible, and
     * an associative array encodes to the same JSON object anyway.
     */
    private static function normalise(mixed $value): mixed
    {
        if ($value instanceof Arrayable) {
            $value = $value->toArray();
        } elseif ($value instanceof JsonSerializable) {
            $value = $value->jsonSerialize();
        } elseif ($value instanceof stdClass) {
            $value = (array) $value;
        }

        if (is_array($value)) {
            return array_map(static fn (mixed $item): mixed => self::normalise($item), $value);
        }

        return $value;
    }

    /** @param array<string, mixed> $filters */
    public function key(int $tenantId, string $module, string $widget, array $filters): string
    {
        ksort($filters);

        $suffix = collect($filters)
            ->map(static fn (mixed $value, string $key): string => $key.'='.(is_array($value) ? implode('|', $value) : (string) ($value ?? '')))
            ->implode(':');

        return sprintf('t%d:v%d:b%s:%s:%s:%s', $tenantId, $this->version($tenantId), self::buildId(), $module, $widget, $suffix);
    }

    /**
     * A deploy changes what a widget returns even though no tenant data moved,
     * so the build is part of the key. Without this, a shipped fix stays
     * invisible behind yesterday's cached payload until the TTL runs out.
     */
    public static function buildId(): string
    {
        return self::$buildId ??= (function (): string {
            $manifest = public_path('build/manifest.json');

            return substr(md5((string) (is_file($manifest) ? filemtime($manifest) : config('app.version', 'dev'))), 0, 8);
        })();
    }

    public function version(int $tenantId): int
    {
        return (int) Cache::get($this->versionKey($tenantId), 1);
    }

    /**
     * Bust every cached widget for a tenant. Called when a sync completes and
     * whenever cost settings change, since those move every margin number.
     */
    public function bust(?int $tenantId = null): void
    {
        $tenantId ??= $this->context->id();

        if ($tenantId === null) {
            return;
        }

        Cache::forever($this->versionKey($tenantId), $this->version($tenantId) + 1);
    }

    public function cachedAt(): string
    {
        return now()->toIso8601String();
    }

    private function versionKey(int $tenantId): string
    {
        return "metric-cache-version:{$tenantId}";
    }
}
