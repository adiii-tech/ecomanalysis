<?php

declare(strict_types=1);

namespace App\Domain\Connectors\DTOs;

final class ConnectionResult
{
    /**
     * @param  array<string, mixed>  $credentials  normalised credentials to persist (encrypted)
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        public readonly bool $ok,
        public readonly ?string $accountLabel = null,
        public readonly array $credentials = [],
        public readonly ?string $error = null,
        public readonly array $meta = [],
        public readonly ?string $redirectUrl = null,
    ) {}

    /** @param array<string, mixed> $credentials */
    public static function success(string $accountLabel, array $credentials = [], array $meta = []): self
    {
        return new self(true, $accountLabel, $credentials, null, $meta);
    }

    public static function failure(string $error): self
    {
        return new self(false, null, [], $error);
    }

    public static function redirect(string $url): self
    {
        return new self(false, null, [], null, [], $url);
    }
}
