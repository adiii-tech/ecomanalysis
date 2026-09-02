<?php

declare(strict_types=1);

namespace App\Domain\Connectors\Drivers\Stubs;

use App\Domain\Connectors\Support\StubConnector;

class WhatsAppCloudConnector extends StubConnector
{
    public function id(): string
    {
        return 'whatsapp_cloud_api';
    }

    public function label(): string
    {
        return 'WhatsApp Cloud API';
    }

    public function summary(): string
    {
        return 'Send alerts, digests and win-back campaigns over WhatsApp (Interakt / WATI compatible).';
    }

    /** @return list<string> */
    public function syncableEntities(): array
    {
        return ['templates', 'messages'];
    }
}
