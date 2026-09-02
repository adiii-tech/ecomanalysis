<?php

declare(strict_types=1);

use App\Support\Metric;

it('colours by goodness rather than direction', function (): void {
    $rtoFell = new Metric('rto', 'RTO %', value: 8.0, previousValue: 12.0, higherIsBetter: false);
    $salesFell = new Metric('sales', 'Net Sales', value: 8.0, previousValue: 12.0, higherIsBetter: true);

    expect($rtoFell->direction())->toBe('down')
        ->and($rtoFell->isGood())->toBeTrue()
        ->and($salesFell->direction())->toBe('down')
        ->and($salesFell->isGood())->toBeFalse();
});

it('reports no delta rather than a fake one when there is no prior data', function (): void {
    $metric = new Metric('sales', 'Net Sales', value: 500.0, previousValue: null);

    expect($metric->deltaPct())->toBeNull()
        ->and($metric->direction())->toBe('flat')
        ->and($metric->isGood())->toBeNull();
});

it('does not divide by zero when the previous period was empty', function (): void {
    $grew = new Metric('sales', 'Net Sales', value: 500.0, previousValue: 0.0);
    $flat = new Metric('sales', 'Net Sales', value: 0.0, previousValue: 0.0);

    expect($grew->deltaPct())->toBeNull()
        ->and($flat->deltaPct())->toBe(0.0);
});

it('computes percentage change', function (): void {
    expect((new Metric('x', 'X', 150.0, 100.0))->deltaPct())->toBe(50.0)
        ->and((new Metric('x', 'X', 75.0, 100.0))->deltaPct())->toBe(-25.0);
});
