<?php

declare(strict_types=1);

namespace App\Domain\Connectors\Drivers\Stubs;

use App\Domain\Connectors\Support\StubConnector;

class AmazonSpApiConnector extends StubConnector
{
    public function id(): string
    {
        return 'amazon_sp_api';
    }

    public function label(): string
    {
        return 'Amazon Seller (SP-API)';
    }

    public function summary(): string
    {
        return 'Direct Amazon orders, settlements, fees, FBA inventory and Buy Box status.';
    }

    /** @return list<string> */
    public function syncableEntities(): array
    {
        return ['orders', 'settlements', 'fees', 'inventory', 'buybox'];
    }
}
