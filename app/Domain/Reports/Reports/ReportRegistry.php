<?php

declare(strict_types=1);

namespace App\Domain\Reports\Reports;

use App\Domain\Reports\Reports\Definitions\ChannelCacReport;
use App\Domain\Reports\Reports\Definitions\ChannelScorecardReport;
use App\Domain\Reports\Reports\Definitions\CodCashFlowReport;
use App\Domain\Reports\Reports\Definitions\CohortRetentionReport;
use App\Domain\Reports\Reports\Definitions\ContributionByCohortReport;
use App\Domain\Reports\Reports\Definitions\DiscountImpactReport;
use App\Domain\Reports\Reports\Definitions\FeeLeakageReport;
use App\Domain\Reports\Reports\Definitions\ForecastReport;
use App\Domain\Reports\Reports\Definitions\GeoCitiesReport;
use App\Domain\Reports\Reports\Definitions\GstSummaryReport;
use App\Domain\Reports\Reports\Definitions\InventoryHealthReport;
use App\Domain\Reports\Reports\Definitions\LogisticsPerformanceReport;
use App\Domain\Reports\Reports\Definitions\NetRealisationReport;
use App\Domain\Reports\Reports\Definitions\NewVsRepeatReport;
use App\Domain\Reports\Reports\Definitions\OrderAgingReport;
use App\Domain\Reports\Reports\Definitions\OrderProfitabilityReport;
use App\Domain\Reports\Reports\Definitions\OwnerBusinessReviewReport;
use App\Domain\Reports\Reports\Definitions\PnlStatementReport;
use App\Domain\Reports\Reports\Definitions\ReorderReplenishmentReport;
use App\Domain\Reports\Reports\Definitions\ReturnsRtoRegisterReport;
use App\Domain\Reports\Reports\Definitions\SkuMarginWaterfallReport;
use App\Domain\Reports\Reports\Definitions\StateRoiReport;
use App\Domain\Reports\Reports\Definitions\StockoutReport;
use App\Domain\Reports\Reports\Definitions\TopCustomersReport;
use App\Domain\Reports\Reports\Definitions\TransactionLedgerReport;
use App\Models\Tenant;
use Illuminate\Contracts\Auth\Access\Authorizable;

/**
 * The report library. Order here is the order the library lists them in.
 */
class ReportRegistry
{
    /** @var list<class-string<Report>> */
    private const REPORTS = [
        OwnerBusinessReviewReport::class,
        ForecastReport::class,
        ChannelScorecardReport::class,
        DiscountImpactReport::class,
        FeeLeakageReport::class,
        NetRealisationReport::class,
        OrderProfitabilityReport::class,
        SkuMarginWaterfallReport::class,
        CohortRetentionReport::class,
        ContributionByCohortReport::class,
        GeoCitiesReport::class,
        ChannelCacReport::class,
        NewVsRepeatReport::class,
        StateRoiReport::class,
        TopCustomersReport::class,
        InventoryHealthReport::class,
        LogisticsPerformanceReport::class,
        OrderAgingReport::class,
        ReorderReplenishmentReport::class,
        StockoutReport::class,
        CodCashFlowReport::class,
        ReturnsRtoRegisterReport::class,
        TransactionLedgerReport::class,
        PnlStatementReport::class,
        GstSummaryReport::class,
    ];

    /** @var array<string, Report>|null */
    private ?array $resolved = null;

    /** @return array<string, Report> keyed by slug */
    public function all(): array
    {
        return $this->resolved ??= collect(self::REPORTS)
            ->map(static fn (string $class): Report => app($class))
            ->keyBy(static fn (Report $report): string => $report->slug())
            ->all();
    }

    public function find(string $slug): ?Report
    {
        return $this->all()[$slug] ?? $this->byKey($slug);
    }

    private function byKey(string $key): ?Report
    {
        foreach ($this->all() as $report) {
            if ($report->key() === $key) {
                return $report;
            }
        }

        return null;
    }

    /**
     * The library a given user may see. A report the user cannot open is not
     * listed at all rather than listed and then refused.
     *
     * @return list<Report>
     */
    public function forUser(Authorizable $user): array
    {
        return array_values(array_filter(
            $this->all(),
            static fn (Report $report): bool => $user->can($report->permission()),
        ));
    }

    /**
     * Reports the tenant's plan does not include. They stay visible in the
     * library — you cannot want what you cannot see — but opening one asks for
     * an upgrade instead of rendering.
     *
     * @return list<string> report keys
     */
    public function restrictedFor(?Tenant $tenant): array
    {
        return $tenant?->plan->restrictedReports() ?? [];
    }

    /** @return list<string> */
    public function categories(): array
    {
        return array_values(array_unique(array_map(
            static fn (Report $report): string => $report->category(),
            array_values($this->all()),
        )));
    }
}
