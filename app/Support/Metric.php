<?php

declare(strict_types=1);

namespace App\Support;

/**
 * A single KPI value with its previous-period comparison.
 *
 * `is_good` encodes *goodness*, not direction — a fall in RTO is good, so the UI
 * colours by `is_good` and never by the sign of `delta_pct`.
 */
final class Metric
{
    /**
     * @param  'currency'|'number'|'percent'|'ratio'|'days'|'seconds'  $format
     */
    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly float $value,
        public readonly ?float $previousValue = null,
        public readonly string $format = 'number',
        public readonly bool $higherIsBetter = true,
        public readonly ?string $tooltip = null,
        /** @var list<array{date: string, value: float}> */
        public readonly array $sparkline = [],
        public readonly ?string $caveat = null,
        public readonly ?string $drilldown = null,
        public readonly ?string $badge = null,
    ) {}

    public function deltaPct(): ?float
    {
        if ($this->previousValue === null) {
            return null;
        }

        if (abs($this->previousValue) < 0.0000001) {
            return abs($this->value) < 0.0000001 ? 0.0 : null;
        }

        return round((($this->value - $this->previousValue) / abs($this->previousValue)) * 100, 2);
    }

    public function direction(): string
    {
        $delta = $this->deltaPct();

        return match (true) {
            $delta === null => 'flat',
            $delta > 0.05 => 'up',
            $delta < -0.05 => 'down',
            default => 'flat',
        };
    }

    public function isGood(): ?bool
    {
        $direction = $this->direction();

        if ($direction === 'flat') {
            return null;
        }

        return $this->higherIsBetter ? $direction === 'up' : $direction === 'down';
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'label' => $this->label,
            'value' => $this->value,
            'prev_value' => $this->previousValue,
            'delta_pct' => $this->deltaPct(),
            'direction' => $this->direction(),
            'is_good' => $this->isGood(),
            'format' => $this->format,
            'higher_is_better' => $this->higherIsBetter,
            'tooltip' => $this->tooltip,
            'sparkline' => $this->sparkline,
            'caveat' => $this->caveat,
            'drilldown' => $this->drilldown,
            'badge' => $this->badge,
        ];
    }
}
