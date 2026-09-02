<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Request-scoped notes that every API response should carry, wherever they were
 * discovered. A plan clamp is found deep inside filter resolution but has to
 * surface in the envelope, and threading it through a hundred controller
 * signatures would be worse than this.
 */
class ResponseMeta
{
    /** @var array<string, mixed> */
    private array $meta = [];

    /** @param array<string, mixed> $meta */
    public function merge(array $meta): void
    {
        $this->meta = [...$this->meta, ...$meta];
    }

    /** @return array<string, mixed> */
    public function all(): array
    {
        return $this->meta;
    }

    public function forget(string $key): void
    {
        unset($this->meta[$key]);
    }
}
