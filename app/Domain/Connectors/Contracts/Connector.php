<?php

declare(strict_types=1);

namespace App\Domain\Connectors\Contracts;

use App\Domain\Connectors\DTOs\ConnectionResult;
use App\Domain\Connectors\DTOs\HealthResult;
use App\Domain\Connectors\DTOs\SyncContext;
use App\Domain\Connectors\DTOs\SyncReport;
use App\Enums\AuthType;

interface Connector
{
    /** Stable machine id, e.g. 'shopify'. */
    public function id(): string;

    public function label(): string;

    public function summary(): string;

    public function authType(): AuthType;

    /** @param array<string, mixed> $credentials */
    public function connect(array $credentials): ConnectionResult;

    public function testConnection(): HealthResult;

    /** @return list<string> */
    public function syncableEntities(): array;

    public function sync(SyncContext $ctx): SyncReport;

    /**
     * Fields the connect form should render, keyed by credential name.
     *
     * @return array<string, array{label: string, type: string, required: bool, help?: string}>
     */
    public function credentialFields(): array;

    /** Minutes between scheduled syncs, per entity. @return array<string, int> */
    public function syncCadence(): array;

    /** True when the driver is a Phase-2 stub with no live implementation yet. */
    public function isStub(): bool;

    /** @return list<string> */
    public function webhookTopics(): array;
}
