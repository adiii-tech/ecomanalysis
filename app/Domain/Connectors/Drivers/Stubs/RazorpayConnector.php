<?php

declare(strict_types=1);

namespace App\Domain\Connectors\Drivers\Stubs;

use App\Domain\Connectors\Support\StubConnector;

class RazorpayConnector extends StubConnector
{
    public function id(): string
    {
        return 'razorpay';
    }

    public function label(): string
    {
        return 'Razorpay';
    }

    public function summary(): string
    {
        return 'Payments, refunds, settlements and gateway fees.';
    }

    /** @return list<string> */
    public function syncableEntities(): array
    {
        return ['payments', 'refunds', 'settlements'];
    }
}
