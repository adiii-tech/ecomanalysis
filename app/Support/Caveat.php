<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Data-honesty disclosure rendered under a widget. If a number cannot be
 * computed truthfully we say so here instead of fabricating it.
 */
final class Caveat
{
    public function __construct(
        public readonly string $message,
        public readonly string $level = 'info',
        public readonly ?string $connector = null,
    ) {}

    public static function missingConnector(string $connector, string $what): self
    {
        return new self(
            sprintf('%s is not connected, so %s is not included in these numbers.', str($connector)->headline()->toString(), $what),
            'warning',
            $connector,
        );
    }

    public static function partial(string $message): self
    {
        return new self($message, 'warning');
    }

    public static function note(string $message): self
    {
        return new self($message, 'info');
    }

    /** @return array{message: string, level: string, connector: string|null} */
    public function toArray(): array
    {
        return [
            'message' => $this->message,
            'level' => $this->level,
            'connector' => $this->connector,
        ];
    }
}
