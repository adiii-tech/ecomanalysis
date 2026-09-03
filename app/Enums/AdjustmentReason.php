<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Why someone changed stock by hand. Forcing a reason is what makes shrinkage
 * measurable later — "adjusted by -40" tells you nothing; "damaged: -40" does.
 */
enum AdjustmentReason: string
{
    case Damaged = 'damaged';
    case Lost = 'lost';
    case Stolen = 'stolen';
    case Expired = 'expired';
    case FoundExtra = 'found_extra';
    case CountCorrection = 'count_correction';
    case SampleOrGift = 'sample_or_gift';
    case SupplierShortfall = 'supplier_shortfall';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Damaged => 'Damaged in warehouse',
            self::Lost => 'Lost',
            self::Stolen => 'Stolen',
            self::Expired => 'Expired',
            self::FoundExtra => 'Found extra stock',
            self::CountCorrection => 'Count correction',
            self::SampleOrGift => 'Sample or gift',
            self::SupplierShortfall => 'Supplier sent less',
            self::Other => 'Other',
        };
    }

    /** @return list<array{value: string, label: string}> */
    public static function catalogue(): array
    {
        return array_map(
            static fn (self $case): array => ['value' => $case->value, 'label' => $case->label()],
            self::cases(),
        );
    }
}
