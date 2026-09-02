<?php

declare(strict_types=1);

namespace App\Domain\Connectors\DTOs;

final class SyncReport
{
    /** @param list<string> $warnings */
    public function __construct(
        public readonly string $entity,
        public readonly int $fetched = 0,
        public readonly int $upserted = 0,
        public readonly mixed $cursorAfter = null,
        public readonly ?string $error = null,
        public readonly array $warnings = [],
        public readonly bool $hasMore = false,
    ) {}

    public static function empty(string $entity, mixed $cursor = null): self
    {
        return new self($entity, 0, 0, $cursor);
    }

    public static function of(string $entity, int $fetched, int $upserted, mixed $cursorAfter = null, bool $hasMore = false): self
    {
        return new self($entity, $fetched, $upserted, $cursorAfter, null, [], $hasMore);
    }

    public static function failed(string $entity, string $error): self
    {
        return new self($entity, 0, 0, null, $error);
    }

    public function ok(): bool
    {
        return $this->error === null;
    }

    public function merge(self $other): self
    {
        return new self(
            $this->entity,
            $this->fetched + $other->fetched,
            $this->upserted + $other->upserted,
            $other->cursorAfter ?? $this->cursorAfter,
            $other->error ?? $this->error,
            [...$this->warnings, ...$other->warnings],
            $other->hasMore,
        );
    }
}
