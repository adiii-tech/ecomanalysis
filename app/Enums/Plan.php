<?php

declare(strict_types=1);

namespace App\Enums;

enum Plan: string
{
    case Demo = 'demo';
    case Starter = 'starter';
    case Growth = 'growth';
    case Scale = 'scale';
    case Custom = 'custom';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    public function connectorLimit(): int
    {
        return match ($this) {
            self::Demo => 0,
            self::Starter => 3,
            self::Growth => 8,
            self::Scale, self::Custom => 100,
        };
    }

    public function seatLimit(): int
    {
        return match ($this) {
            self::Demo => 1,
            self::Starter => 3,
            self::Growth => 10,
            self::Scale, self::Custom => 100,
        };
    }

    public function aiCredits(): int
    {
        return match ($this) {
            self::Demo => 2,
            self::Starter => 100,
            self::Growth => 500,
            self::Scale, self::Custom => 5000,
        };
    }

    public function historyWindowDays(): int
    {
        return match ($this) {
            self::Demo => 30,
            self::Starter => 180,
            self::Growth => 730,
            self::Scale, self::Custom => 3650,
        };
    }

    public function alertRuleLimit(): int
    {
        return match ($this) {
            self::Demo => 0,
            self::Starter => 5,
            self::Growth => 25,
            self::Scale, self::Custom => 200,
        };
    }

    public function hasChartInsights(): bool
    {
        return $this !== self::Demo;
    }

    /**
     * The finance-grade reports — P&L, GST, forecasting, cohort contribution —
     * are the reason a brand moves off the entry plan.
     */
    public function hasAdvancedReports(): bool
    {
        return in_array($this, [self::Growth, self::Scale, self::Custom], true);
    }

    public function hasScheduledDelivery(): bool
    {
        return $this !== self::Demo;
    }

    /** @return list<string> report keys gated behind an upgrade on this plan */
    public function restrictedReports(): array
    {
        return $this->hasAdvancedReports()
            ? []
            : ['pnl_statement', 'gst_summary', 'forecast', 'contribution_by_cohort', 'net_realisation'];
    }
}
