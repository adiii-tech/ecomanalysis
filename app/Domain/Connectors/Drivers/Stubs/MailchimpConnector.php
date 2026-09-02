<?php

declare(strict_types=1);

namespace App\Domain\Connectors\Drivers\Stubs;

use App\Domain\Connectors\Support\StubConnector;

class MailchimpConnector extends StubConnector
{
    public function id(): string
    {
        return 'mailchimp';
    }

    public function label(): string
    {
        return 'Mailchimp';
    }

    public function summary(): string
    {
        return 'Email campaign performance and audience sync.';
    }

    /** @return list<string> */
    public function syncableEntities(): array
    {
        return ['campaigns', 'lists'];
    }
}
