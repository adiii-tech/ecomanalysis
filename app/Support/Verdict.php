<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Every widget must return a judgement, not just a number. A Verdict is that
 * judgement plus the one-line reason behind it.
 */
final class Verdict
{
    public const SCALE = 'scale';

    public const HOLD = 'hold';

    public const CUT = 'cut';

    public const GOOD = 'good';

    public const WATCH = 'watch';

    public const BAD = 'bad';

    public const NEUTRAL = 'neutral';

    public function __construct(
        public readonly string $status,
        public readonly string $headline,
        public readonly ?string $detail = null,
        public readonly ?string $action = null,
        public readonly ?int $impactPaise = null,
    ) {}

    public static function good(string $headline, ?string $detail = null, ?string $action = null): self
    {
        return new self(self::GOOD, $headline, $detail, $action);
    }

    public static function watch(string $headline, ?string $detail = null, ?string $action = null, ?int $impactPaise = null): self
    {
        return new self(self::WATCH, $headline, $detail, $action, $impactPaise);
    }

    public static function bad(string $headline, ?string $detail = null, ?string $action = null, ?int $impactPaise = null): self
    {
        return new self(self::BAD, $headline, $detail, $action, $impactPaise);
    }

    public static function neutral(string $headline, ?string $detail = null, ?string $action = null): self
    {
        return new self(self::NEUTRAL, $headline, $detail, $action);
    }

    /**
     * Campaign-style verdict: Scale / Hold / Cut against a target and the account average.
     */
    public static function forRoas(float $roas, float $target, float $accountAverage, int $spendPaise): self
    {
        if ($spendPaise <= 0) {
            return new self(self::NEUTRAL, 'No spend', 'Nothing spent in this window.');
        }

        if ($roas >= max($target, $accountAverage * 1.15)) {
            return new self(self::SCALE, 'Scale',
                sprintf('ROAS %.2f beats both target %.2f and account average %.2f.', $roas, $target, $accountAverage),
                'Increase budget 20-30% and re-check in 3 days.');
        }

        if ($roas < min($target * 0.6, max($accountAverage * 0.5, 0.01))) {
            return new self(self::CUT, 'Cut',
                sprintf('ROAS %.2f is far below target %.2f — every rupee here loses money.', $roas, $target),
                'Pause and reallocate budget to the top performer.',
                (int) round($spendPaise * (1 - ($roas / max($target, 0.01)))));
        }

        return new self(self::HOLD, 'Hold',
            sprintf('ROAS %.2f is near target %.2f — not proven either way yet.', $roas, $target),
            'Keep budget flat; test creative before changing spend.');
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'status' => $this->status,
            'headline' => $this->headline,
            'detail' => $this->detail,
            'action' => $this->action,
            'impact_paise' => $this->impactPaise,
        ];
    }
}
