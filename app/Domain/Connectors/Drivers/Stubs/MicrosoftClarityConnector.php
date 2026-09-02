<?php

declare(strict_types=1);

namespace App\Domain\Connectors\Drivers\Stubs;

use App\Domain\Connectors\Support\StubConnector;

class MicrosoftClarityConnector extends StubConnector
{
    public function id(): string
    {
        return 'microsoft_clarity';
    }

    public function label(): string
    {
        return 'Microsoft Clarity';
    }

    public function summary(): string
    {
        return 'Session recordings, rage clicks and dead clicks for checkout friction analysis.';
    }

    /** @return list<string> */
    public function syncableEntities(): array
    {
        return ['metrics'];
    }
}
