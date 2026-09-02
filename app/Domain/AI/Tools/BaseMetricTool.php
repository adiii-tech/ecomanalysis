<?php

declare(strict_types=1);

namespace App\Domain\AI\Tools;

use App\Support\Money;
use App\Support\Period;
use App\Support\WidgetFilters;
use Illuminate\Http\Request;

abstract class BaseMetricTool implements MetricTool
{
    /** @return array<string, mixed> */
    public function schema(): array
    {
        return [
            'type' => 'object',
            'properties' => $this->properties(),
            'required' => $this->required(),
            'additionalProperties' => false,
        ];
    }

    /** @return array<string, mixed> */
    protected function properties(): array
    {
        return $this->periodProperties();
    }

    /** @return list<string> */
    protected function required(): array
    {
        return [];
    }

    /**
     * Every tool accepts the same period and channel controls, so the model can
     * answer "last month" or "on Amazon" without a bespoke parameter per tool.
     *
     * @return array<string, mixed>
     */
    protected function periodProperties(): array
    {
        return [
            'period' => [
                'type' => 'string',
                'enum' => ['today', 'yesterday', 'last_7_days', 'last_30_days', 'last_90_days', 'mtd', 'last_month', 'qtd', 'ytd'],
                'description' => 'Named period. Omit to use the window the user currently has selected on screen.',
            ],
            'channel' => [
                'type' => 'string',
                'description' => "Channel code such as 'shopify' or 'amazon', or 'd2c' / 'marketplace' / 'all'. Omit for the current selection.",
            ],
        ];
    }

    /**
     * Applies the model's period/channel overrides on top of what the user has
     * selected, so an unspecified parameter means "what they are looking at".
     *
     * @param  array<string, mixed>  $input
     */
    protected function resolve(array $input, WidgetFilters $filters): WidgetFilters
    {
        $resolved = $filters;

        if (! empty($input['period'])) {
            $resolved = $resolved->withPeriod(
                Period::fromPreset((string) $input['period'], $filters->period->timezone),
            );
        }

        if (! empty($input['channel'])) {
            $request = new Request([
                'from' => $resolved->period->fromDate(),
                'to' => $resolved->period->toDate(),
                'channel' => (string) $input['channel'],
                'returns_basis' => $resolved->returnsBasis,
            ]);

            $resolved = WidgetFilters::fromRequest($request, $resolved->period->timezone);
        }

        return $resolved;
    }

    /**
     * Money crosses into the model as formatted rupees rather than raw paise —
     * a model shown `1412000` will happily call it "1.4 million rupees".
     */
    protected function money(int $paise): string
    {
        return Money::format($paise);
    }

    protected function describePeriod(WidgetFilters $filters): string
    {
        return sprintf('%s to %s', $filters->period->fromDate(), $filters->period->toDate());
    }
}
