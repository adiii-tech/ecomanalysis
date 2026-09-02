<?php

declare(strict_types=1);

use App\Support\Verdict;

it('says scale when ROAS beats both the target and the account average', function (): void {
    $verdict = Verdict::forRoas(roas: 5.2, target: 3.0, accountAverage: 3.4, spendPaise: 100_000);

    expect($verdict->status)->toBe(Verdict::SCALE)
        ->and($verdict->action)->toContain('Increase budget');
});

it('says cut when ROAS is far below target, and quantifies the bleed', function (): void {
    $verdict = Verdict::forRoas(roas: 0.6, target: 3.0, accountAverage: 3.4, spendPaise: 100_000);

    expect($verdict->status)->toBe(Verdict::CUT)
        ->and($verdict->impactPaise)->toBeGreaterThan(0);
});

it('says hold when the result is not yet proven either way', function (): void {
    expect(Verdict::forRoas(roas: 2.9, target: 3.0, accountAverage: 3.0, spendPaise: 100_000)->status)
        ->toBe(Verdict::HOLD);
});

it('stays neutral rather than judging a campaign with no spend', function (): void {
    expect(Verdict::forRoas(roas: 0.0, target: 3.0, accountAverage: 3.0, spendPaise: 0)->status)
        ->toBe(Verdict::NEUTRAL);
});
