<?php

declare(strict_types=1);

namespace App\Domain\Connectors\Support;

use App\Domain\Connectors\DTOs\HealthResult;
use App\Domain\Connectors\DTOs\SyncContext;
use App\Domain\Connectors\DTOs\SyncReport;
use App\Enums\AuthType;

/**
 * Phase-2 connectors: the interface and credential form exist so a tenant can
 * register intent, but no live driver is wired yet. We say so plainly rather
 * than pretending a sync happened.
 */
abstract class StubConnector extends AbstractConnector
{
    public function authType(): AuthType
    {
        return AuthType::Token;
    }

    public function isStub(): bool
    {
        return true;
    }

    /** @return array<string, array{label: string, type: string, required: bool, help?: string}> */
    public function credentialFields(): array
    {
        return [
            'api_key' => ['label' => 'API key', 'type' => 'password', 'required' => true],
            'account_label' => ['label' => 'Account label', 'type' => 'text', 'required' => false],
        ];
    }

    public function testConnection(): HealthResult
    {
        return HealthResult::fail(
            sprintf('%s is registered but its driver ships in phase 2 — no data is being pulled yet.', $this->label()),
        );
    }

    public function sync(SyncContext $ctx): SyncReport
    {
        return SyncReport::failed(
            $ctx->entity,
            sprintf('%s driver not implemented yet (phase 2). No data was fabricated.', $this->label()),
        );
    }
}
