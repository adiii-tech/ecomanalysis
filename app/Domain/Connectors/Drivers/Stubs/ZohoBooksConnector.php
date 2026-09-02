<?php

declare(strict_types=1);

namespace App\Domain\Connectors\Drivers\Stubs;

use App\Domain\Connectors\Support\StubConnector;

class ZohoBooksConnector extends StubConnector
{
    public function id(): string
    {
        return 'zoho_books';
    }

    public function label(): string
    {
        return 'Zoho Books';
    }

    public function summary(): string
    {
        return 'Invoices, credit notes, expenses and chart of accounts.';
    }

    /** @return list<string> */
    public function syncableEntities(): array
    {
        return ['invoices', 'credit_notes', 'expenses'];
    }
}
