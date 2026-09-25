<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * What a prepaid shopper actually paid with. COD is deliberately absent — it is
 * a payment mode, not an instrument, and lives on {@see PaymentMode}.
 *
 * The buckets are the ones an Indian D2C owner can act on: UPI costs almost
 * nothing to accept, cards cost around 2%, and pay-later costs more again.
 */
enum PaymentInstrument: string
{
    case Upi = 'upi';
    case Card = 'card';
    case NetBanking = 'netbanking';
    case Wallet = 'wallet';
    case PayLater = 'paylater';
    case GiftCard = 'gift_card';
    case Other = 'other';
    case Unattributed = 'unattributed';

    public function label(): string
    {
        return match ($this) {
            self::Upi => 'UPI',
            self::Card => 'Cards',
            self::NetBanking => 'Net banking',
            self::Wallet => 'Wallets',
            self::PayLater => 'Pay later / EMI',
            self::GiftCard => 'Gift card / store credit',
            self::Other => 'Other prepaid',
            self::Unattributed => 'Not attributed',
        };
    }

    /**
     * Whether the instrument is a real answer or a placeholder for one we could
     * not read. The two placeholders sort last and drive the coverage caveat.
     */
    public function isIdentified(): bool
    {
        return $this !== self::Other && $this !== self::Unattributed;
    }
}
