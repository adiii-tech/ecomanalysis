<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Concerns;

use App\Models\Tenant as TenantModel;
use App\Support\Facades\Tenant;
use App\Support\MetricCache;
use App\Support\Period;
use App\Support\ResponseMeta;
use App\Support\WidgetFilters;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Http\Request;
use Laravel\Pennant\Feature;

trait ResolvesFilters
{
    /** @var array<string, mixed>|null */
    protected ?array $historyClamped = null;

    protected function filters(Request $request): WidgetFilters
    {
        $tenant = Tenant::current();

        return $this->clampToPlan(
            WidgetFilters::fromRequest($request, $tenant->timezone, $tenant->fiscal_year_start),
            $tenant,
        );
    }

    /**
     * A plan buys a history window. Asking for more does not fail — the window
     * is clamped and the response says so, because silently returning a
     * different period than the one requested is the dishonest option.
     */
    protected function clampToPlan(WidgetFilters $filters, TenantModel $tenant): WidgetFilters
    {
        $days = (int) Feature::value('history-days');
        $earliest = now($tenant->timezone)->subDays($days)->startOfDay();

        if ($filters->period->from->greaterThanOrEqualTo($earliest)) {
            $this->historyClamped = null;
            app(ResponseMeta::class)->forget('history_clamped');

            return $filters;
        }

        $this->historyClamped = [
            'requested_from' => $filters->period->fromDate(),
            'allowed_from' => $earliest->toDateString(),
            'history_days' => $days,
            'plan' => $tenant->plan->value,
            'message' => sprintf(
                'Your %s plan covers %d days of history, so this starts on %s rather than %s.',
                $tenant->plan->label(),
                $days,
                $earliest->format('d M Y'),
                $filters->period->from->format('d M Y'),
            ),
        ];

        app(ResponseMeta::class)->merge(['history_clamped' => $this->historyClamped]);

        return $filters->withPeriod(new Period(
            CarbonImmutable::parse($earliest, $tenant->timezone),
            $filters->period->to,
            $tenant->timezone,
            $filters->period->preset,
        ));
    }

    /**
     * Extra response metadata describing a clamp, if one happened.
     *
     * @return array<string, mixed>
     */
    protected function filterMeta(): array
    {
        return $this->historyClamped === null ? [] : ['history_clamped' => $this->historyClamped];
    }

    /**
     * Read-through cache for a widget payload, keyed by
     * tenant:module:widget:from:to:channel:basis.
     *
     * @template TValue
     *
     * @param  Closure(): TValue  $callback
     * @return TValue
     */
    protected function cached(string $module, string $widget, WidgetFilters $filters, Closure $callback, ?int $ttl = null): mixed
    {
        return app(MetricCache::class)->remember($module, $widget, $filters->cacheKey(), $callback, $ttl);
    }
}
