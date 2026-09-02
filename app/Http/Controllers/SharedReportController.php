<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Reports\Reports\ReportRegistry;
use App\Models\ReportShare;
use App\Support\Period;
use App\Support\TenantContext;
use App\Support\WidgetFilters;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * A read-only snapshot of one report, for someone who has the link and no
 * account. The link carries the exact filters the sender was looking at, so
 * the recipient cannot accidentally see a different window — or anything else
 * in the tenant.
 */
class SharedReportController extends Controller
{
    public function __construct(
        private readonly ReportRegistry $registry,
        private readonly TenantContext $context,
    ) {}

    public function show(string $token): Response|RedirectResponse
    {
        $share = $this->context->withoutScope(fn (): ?ReportShare => ReportShare::query()
            ->with('tenant')
            ->where('token', $token)
            ->whereNull('revoked_at')
            ->first());

        if ($share === null || ($share->expires_at !== null && $share->expires_at->isPast())) {
            return Inertia::render('reports/shared', [
                'expired' => true,
                'report' => null,
                'payload' => null,
                'tenant' => null,
                'filters' => null,
            ]);
        }

        $report = $this->registry->find($share->report_key);

        if ($report === null) {
            return redirect('/');
        }

        $this->context->set($share->tenant);

        $filters = $this->filtersFor($share);
        $payload = $report->build($filters)->toArray();

        $share->increment('view_count');

        return Inertia::render('reports/shared', [
            'expired' => false,
            'report' => $report->meta(),
            'payload' => $payload,
            'tenant' => ['name' => $share->tenant->name, 'logo_url' => $share->tenant->logo_url],
            'filters' => [
                'from' => $filters->period->fromDate(),
                'to' => $filters->period->toDate(),
                'channel' => $filters->channelScope,
                'returns_basis' => $filters->returnsBasis,
            ],
            'shared_at' => $share->created_at?->toIso8601String(),
            'expires_at' => $share->expires_at?->toIso8601String(),
        ]);
    }

    private function filtersFor(ReportShare $share): WidgetFilters
    {
        $frozen = $share->filters ?? [];
        $timezone = $share->tenant->timezone;

        return WidgetFilters::forSnapshot(
            Period::make($frozen['from'] ?? null, $frozen['to'] ?? null, $timezone),
            $frozen['channel'] ?? 'all',
            $frozen['payment_mode'] ?? null,
            $frozen['returns_basis'] ?? WidgetFilters::BASIS_ORDER_DATE,
        );
    }
}
