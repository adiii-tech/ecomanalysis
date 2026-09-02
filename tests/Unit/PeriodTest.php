<?php

declare(strict_types=1);

use App\Support\Period;
use Carbon\CarbonImmutable;

beforeEach(function (): void {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-15 14:00:00', 'Asia/Kolkata'));
});

afterEach(function (): void {
    CarbonImmutable::setTestNow();
});

it('derives a previous window of identical length immediately before', function (): void {
    $period = Period::make('2026-09-01', '2026-09-30');
    $previous = $period->previous();

    expect($period->days())->toBe(30)
        ->and($previous->days())->toBe(30)
        ->and($previous->toDate())->toBe('2026-08-31')
        ->and($previous->fromDate())->toBe('2026-08-02');
});

it('resolves presets against the tenant timezone', function (): void {
    expect(Period::fromPreset('today')->fromDate())->toBe('2026-09-15')
        ->and(Period::fromPreset('last_7_days')->fromDate())->toBe('2026-09-09')
        ->and(Period::fromPreset('mtd')->fromDate())->toBe('2026-09-01')
        ->and(Period::fromPreset('last_month')->fromDate())->toBe('2026-08-01')
        ->and(Period::fromPreset('last_month')->toDate())->toBe('2026-08-31');
});

it('uses the Indian financial year for YTD', function (): void {
    expect(Period::fromPreset('ytd', 'Asia/Kolkata', 4)->fromDate())->toBe('2026-04-01');
});

it('swaps a reversed custom range instead of returning nothing', function (): void {
    $period = Period::make('2026-09-30', '2026-09-01');

    expect($period->fromDate())->toBe('2026-09-01')->and($period->toDate())->toBe('2026-09-30');
});
