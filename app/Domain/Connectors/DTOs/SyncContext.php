<?php

declare(strict_types=1);

namespace App\Domain\Connectors\DTOs;

use App\Models\Connector;
use App\Models\Tenant;
use Carbon\CarbonImmutable;

final class SyncContext
{
    public function __construct(
        public readonly Tenant $tenant,
        public readonly Connector $connector,
        public readonly string $entity,
        public readonly mixed $cursor = null,
        public readonly ?CarbonImmutable $since = null,
        public readonly ?CarbonImmutable $until = null,
        public readonly bool $backfill = false,
        public readonly int $pageLimit = 250,
        public readonly string $trigger = 'scheduled',
    ) {}

    /** @return array<string, mixed> */
    public function credentials(): array
    {
        return $this->connector->credentials ?? [];
    }

    public function credential(string $key, mixed $default = null): mixed
    {
        return data_get($this->connector->credentials, $key, $default);
    }

    public function withCursor(mixed $cursor): self
    {
        return new self(
            $this->tenant, $this->connector, $this->entity, $cursor,
            $this->since, $this->until, $this->backfill, $this->pageLimit, $this->trigger,
        );
    }

    public function sinceOrDefault(int $defaultDays = 30): CarbonImmutable
    {
        return $this->since ?? CarbonImmutable::now($this->tenant->timezone)->subDays($defaultDays)->startOfDay();
    }

    public function untilOrNow(): CarbonImmutable
    {
        return $this->until ?? CarbonImmutable::now($this->tenant->timezone);
    }
}
