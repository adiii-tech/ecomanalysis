<?php

declare(strict_types=1);

namespace App\Enums;

enum RfmSegment: string
{
    case Champions = 'champions';
    case Loyal = 'loyal';
    case Potential = 'potential';
    case New = 'new';
    case AtRisk = 'at_risk';
    case Hibernating = 'hibernating';
    case Lost = 'lost';

    public function label(): string
    {
        return match ($this) {
            self::Champions => 'Champions',
            self::Loyal => 'Loyal',
            self::Potential => 'Potential Loyalist',
            self::New => 'New Customers',
            self::AtRisk => 'At Risk',
            self::Hibernating => 'Hibernating',
            self::Lost => 'Lost',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Champions => '#16a34a',
            self::Loyal => '#0ea5e9',
            self::Potential => '#8b5cf6',
            self::New => '#f59e0b',
            self::AtRisk => '#f97316',
            self::Hibernating => '#94a3b8',
            self::Lost => '#ef4444',
        };
    }

    public function playbook(): string
    {
        return match ($this) {
            self::Champions => 'Reward them. Early access, referral asks, VIP tier.',
            self::Loyal => 'Upsell higher-margin SKUs and bundles.',
            self::Potential => 'Nudge the second order — time-boxed offer within 30 days.',
            self::New => 'Onboarding flow; aim for repeat inside 90 days.',
            self::AtRisk => 'Win-back campaign before they lapse fully.',
            self::Hibernating => 'Low-cost reactivation only; do not spend paid budget.',
            self::Lost => 'Suppress from paid audiences to stop wasting spend.',
        };
    }
}
