<?php

declare(strict_types=1);

namespace App\Domain\Connectors\Drivers\Stubs;

use App\Domain\Connectors\Support\StubConnector;

class TikTokAdsConnector extends StubConnector
{
    public function id(): string
    {
        return 'tiktok_ads';
    }

    public function label(): string
    {
        return 'TikTok Ads';
    }

    public function summary(): string
    {
        return 'Campaign spend, impressions, clicks and conversions.';
    }

    /** @return list<string> */
    public function syncableEntities(): array
    {
        return ['campaigns', 'insights'];
    }
}
