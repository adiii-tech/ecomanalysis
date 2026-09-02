<?php

declare(strict_types=1);

namespace App\Domain\Alerts\Services;

use App\Models\AlertEvent;
use App\Models\AlertRule;
use App\Support\Money;
use Illuminate\Support\Collection;

/**
 * Evaluates one alert rule and raises an event when it breaches.
 *
 * Two rules keep alerting useful rather than noisy: a rule that is still
 * breaching does not re-fire within its own window, and a rule scoped to a
 * dimension raises one event for the worst offender rather than twenty.
 */
class RuleEvaluator
{
    public function __construct(private readonly MetricResolver $metrics) {}

    /** @return Collection<int, AlertEvent> */
    public function evaluate(AlertRule $rule, bool $dryRun = false): Collection
    {
        $events = collect();

        if (! MetricResolver::supports($rule->metric)) {
            return $events;
        }

        $readings = collect($this->metrics->resolve($rule->metric, $rule->window_days))
            ->filter(fn (array $reading): bool => $this->matchesScope($rule, $reading['dimension']))
            ->filter(fn (array $reading): bool => $this->breaches($rule, $reading['value']))
            ->sortByDesc(fn (array $reading): float => $rule->operator === 'lt' ? -$reading['value'] : $reading['value'])
            ->values();

        if (! $dryRun) {
            $rule->forceFill(['last_evaluated_at' => now()])->save();
        }

        if ($readings->isEmpty()) {
            return $events;
        }

        // One event per rule firing, naming the worst offender.
        $worst = $readings->first();

        if (! $dryRun && $this->recentlyFired($rule, $worst['dimension'])) {
            return $events;
        }

        $event = new AlertEvent([
            'tenant_id' => $rule->tenant_id,
            'alert_rule_id' => $rule->id,
            'title' => $this->title($rule, $worst),
            'body' => $this->body($rule, $worst, $readings->count()),
            'severity' => $this->severity($rule, $worst['value']),
            'observed_value' => $worst['value'],
            'threshold' => (float) $rule->threshold,
            'dimension_value' => $worst['dimension'],
        ]);

        if (! $dryRun) {
            $event->save();
            $rule->forceFill(['last_triggered_at' => now()])->save();
        }

        return $events->push($event);
    }

    private function matchesScope(AlertRule $rule, ?string $dimension): bool
    {
        if ($rule->scope === 'global' || blank($rule->scope_values)) {
            return true;
        }

        return $dimension !== null && in_array($dimension, $rule->scope_values, true);
    }

    private function breaches(AlertRule $rule, float $value): bool
    {
        $threshold = (float) $rule->threshold;

        return match ($rule->operator) {
            'gt' => $value > $threshold,
            'gte' => $value >= $threshold,
            'lt' => $value < $threshold,
            'lte' => $value <= $threshold,
            'eq' => abs($value - $threshold) < 0.0001,
            default => false,
        };
    }

    /**
     * A rule that is still breaching should not shout every five minutes. It
     * stays quiet for the length of its own window before firing again.
     */
    private function recentlyFired(AlertRule $rule, ?string $dimension): bool
    {
        return AlertEvent::query()
            ->where('alert_rule_id', $rule->id)
            ->when($dimension !== null, fn ($q) => $q->where('dimension_value', $dimension))
            ->where('created_at', '>', now()->subDays(max($rule->window_days, 1)))
            ->exists();
    }

    /** @param array{dimension: string|null, value: float, context: array<string, mixed>} $reading */
    private function title(AlertRule $rule, array $reading): string
    {
        $meta = MetricResolver::catalogue()[$rule->metric];
        $where = $reading['dimension'] !== null ? " in {$reading['dimension']}" : '';

        return sprintf('%s%s is %s', $meta['label'], $where, $this->format($reading['value'], $meta['unit']));
    }

    /** @param array{dimension: string|null, value: float, context: array<string, mixed>} $reading */
    private function body(AlertRule $rule, array $reading, int $breachCount): string
    {
        $meta = MetricResolver::catalogue()[$rule->metric];

        $body = sprintf(
            'Your rule "%s" watches %s over %d day%s and fires when it is %s %s. It is currently %s.',
            $rule->name,
            strtolower($meta['label']),
            $rule->window_days,
            $rule->window_days === 1 ? '' : 's',
            $this->operatorWords($rule->operator),
            $this->format((float) $rule->threshold, $meta['unit']),
            $this->format($reading['value'], $meta['unit']),
        );

        if ($breachCount > 1) {
            $body .= sprintf(' %d others are also over the line.', $breachCount - 1);
        }

        foreach ($reading['context'] as $key => $value) {
            $body .= sprintf(' %s: %s.', str($key)->headline()->toString(), is_scalar($value) ? $value : json_encode($value));
        }

        return $body;
    }

    private function severity(AlertRule $rule, float $value): string
    {
        $threshold = max(abs((float) $rule->threshold), 0.0001);
        $overshoot = abs($value - (float) $rule->threshold) / $threshold;

        return $overshoot >= 0.5 ? 'critical' : 'warning';
    }

    private function operatorWords(string $operator): string
    {
        return match ($operator) {
            'gt' => 'above',
            'gte' => 'at or above',
            'lt' => 'below',
            'lte' => 'at or below',
            default => 'equal to',
        };
    }

    private function format(float $value, string $unit): string
    {
        return match ($unit) {
            'currency' => Money::format((int) round($value)),
            'percent' => number_format($value, 1).'%',
            'ratio' => number_format($value, 2).'×',
            'days' => number_format($value, 1).' days',
            default => number_format($value),
        };
    }
}
