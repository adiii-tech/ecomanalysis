<?php

declare(strict_types=1);

namespace App\Domain\Connectors\DTOs;

final class HealthResult
{
    /** @param array<string, mixed> $details */
    public function __construct(
        public readonly bool $healthy,
        public readonly string $message,
        public readonly array $details = [],
        public readonly int $latencyMs = 0,
    ) {}

    /** @param array<string, mixed> $details */
    public static function ok(string $message = 'Connection healthy.', array $details = [], int $latencyMs = 0): self
    {
        return new self(true, $message, $details, $latencyMs);
    }

    /** @param array<string, mixed> $details */
    public static function fail(string $message, array $details = []): self
    {
        return new self(false, $message, $details);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'healthy' => $this->healthy,
            'message' => $this->message,
            'details' => $this->details,
            'latency_ms' => $this->latencyMs,
        ];
    }
}
