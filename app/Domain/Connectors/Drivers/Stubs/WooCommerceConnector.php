<?php

declare(strict_types=1);

namespace App\Domain\Connectors\Drivers\Stubs;

use App\Domain\Connectors\Support\StubConnector;

class WooCommerceConnector extends StubConnector
{
    public function id(): string
    {
        return 'woocommerce';
    }

    public function label(): string
    {
        return 'WooCommerce';
    }

    public function summary(): string
    {
        return 'Orders, products, customers and refunds from a WooCommerce store.';
    }

    /** @return list<string> */
    public function syncableEntities(): array
    {
        return ['orders', 'products', 'customers', 'refunds'];
    }
}
