<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Why stock moved. Every movement carries one of these, so a balance can always
 * be explained rather than just observed.
 */
enum StockMovementType: string
{
    case Opening = 'opening';
    case PurchaseReceipt = 'purchase_receipt';
    case Sale = 'sale';
    case ReturnIn = 'return_in';
    case RtoIn = 'rto_in';
    case Adjustment = 'adjustment';
    case CountCorrection = 'count_correction';
    case TransferOut = 'transfer_out';
    case TransferIn = 'transfer_in';
    case Damage = 'damage';
    case WriteOff = 'write_off';
    case SyncCorrection = 'sync_correction';

    public function label(): string
    {
        return match ($this) {
            self::Opening => 'Opening stock',
            self::PurchaseReceipt => 'Purchase received',
            self::Sale => 'Sold',
            self::ReturnIn => 'Customer return',
            self::RtoIn => 'RTO returned',
            self::Adjustment => 'Manual adjustment',
            self::CountCorrection => 'Stock count correction',
            self::TransferOut => 'Transferred out',
            self::TransferIn => 'Transferred in',
            self::Damage => 'Damaged',
            self::WriteOff => 'Written off',
            self::SyncCorrection => 'Corrected from sales channel',
        };
    }

    /** Movements a human raises directly, as opposed to ones the system records. */
    public function isManual(): bool
    {
        return in_array($this, [
            self::Opening, self::Adjustment, self::Damage,
            self::WriteOff, self::TransferOut, self::TransferIn,
        ], true);
    }

    public function increasesStock(): bool
    {
        return in_array($this, [
            self::Opening, self::PurchaseReceipt, self::ReturnIn,
            self::RtoIn, self::TransferIn,
        ], true);
    }

    /** @return list<array{value: string, label: string, direction: string}> */
    public static function catalogue(): array
    {
        return array_map(static fn (self $case): array => [
            'value' => $case->value,
            'label' => $case->label(),
            'direction' => $case->increasesStock() ? 'in' : 'out',
        ], self::cases());
    }
}
