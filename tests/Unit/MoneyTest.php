<?php

declare(strict_types=1);

use App\Support\Money;

it('groups rupees the Indian way', function (): void {
    expect(Money::format(95_68_32_00))->toBe('₹9,56,832')
        ->and(Money::format(100_00))->toBe('₹100')
        ->and(Money::format(12_34_567_89))->toBe('₹12,34,567')
        ->and(Money::format(-45_67_00))->toBe('-₹4,567');
});

it('renders compact lakh and crore notation', function (): void {
    // ₹9,56,83,200 → 9.57 crore; ₹9,56,832 → 9.6 lakh.
    expect(Money::compact(95_68_32_000_0))->toBe('₹9.6Cr')
        ->and(Money::compact(95_68_32_00))->toBe('₹9.6L')
        ->and(Money::compact(95_68_30_0))->toBe('₹95.7K')
        ->and(Money::compact(45_00))->toBe('₹45');
});

it('round-trips rupees to paise without float drift', function (): void {
    expect(Money::fromRupees(1299.99))->toBe(129999)
        ->and(Money::fromRupees('0.1'))->toBe(10)
        ->and(Money::toRupees(129999))->toBe(1299.99);
});
