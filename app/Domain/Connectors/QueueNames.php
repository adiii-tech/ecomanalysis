<?php

declare(strict_types=1);

namespace App\Domain\Connectors;

/**
 * Each connector syncs on its own queue, so a slow Shopify backfill cannot
 * block a Meta refresh. That only works if the worker is told to listen on all
 * of them — this is the single place that list is derived, so adding a
 * connector never needs a worker config change.
 */
final class QueueNames
{
    /*
     * These are the queues `config/horizon.php` supervises. Horizon needs
     * QUEUE_CONNECTION=redis and a running Redis server; on the database queue
     * the same work runs through `php artisan queue:work --queue=` with
     * `QueueNames::asList()`.
     */

    /** @return list<string> */
    public static function all(): array
    {
        // Webhooks first: an order arriving live matters more than a backfill.
        return ['webhooks', ...self::sync(), 'rollups', 'default'];
    }

    /** @return list<string> */
    public static function sync(): array
    {
        return array_map(
            static fn (string $id): string => 'sync-'.$id,
            app(ConnectorRegistry::class)->live()->map(fn ($driver): string => $driver->id())->all(),
        );
    }

    public static function asList(): string
    {
        return implode(',', self::all());
    }
}
