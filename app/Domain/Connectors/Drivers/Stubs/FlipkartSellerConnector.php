<?php

declare(strict_types=1);

namespace App\Domain\Connectors\Drivers\Stubs;

use App\Domain\Connectors\Support\StubConnector;

class FlipkartSellerConnector extends StubConnector
{
    public function id(): string
    {
        return 'flipkart_seller';
    }

    public function label(): string
    {
        return 'Flipkart Seller';
    }

    public function summary(): string
    {
        return 'Direct Flipkart orders, returns, settlements and listing health.';
    }

    /** @return list<string> */
    public function syncableEntities(): array
    {
        return ['orders', 'returns', 'settlements', 'listings'];
    }
}
