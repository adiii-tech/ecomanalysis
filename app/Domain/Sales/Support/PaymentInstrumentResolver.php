<?php

declare(strict_types=1);

namespace App\Domain\Sales\Support;

use App\Enums\PaymentInstrument;

/**
 * Buckets a payment transaction into the instrument the shopper actually used.
 *
 * Every processor spells it differently — Razorpay reports `netbanking`,
 * Cashfree "Net Banking", and Shopify hands back the card network ("RuPay",
 * "Visa") instead of the word card at all — so separators are flattened before
 * matching, the same way {@see PaymentModeResolver} does.
 *
 * The transaction's method is read first and its gateway only as a fallback,
 * because a gateway name says who processed the payment, not how it was paid:
 * "Razorpay" alone cannot tell UPI from a credit card, and guessing would put
 * a 0%-MDR rupee in the 2% bucket. Anything unreadable stays `Other` rather
 * than being assigned to the most likely instrument.
 */
final class PaymentInstrumentResolver
{
    public static function resolve(?string $method, ?string $gateway = null): PaymentInstrument
    {
        return self::classify($method)
            ?? self::classify($gateway)
            ?? PaymentInstrument::Other;
    }

    private static function classify(?string $value): ?PaymentInstrument
    {
        $flat = trim(strtolower((string) preg_replace('/[^a-zA-Z0-9]+/', ' ', (string) $value)));

        if ($flat === '') {
            return null;
        }

        // Order matters. "Gift card" and "store credit" both contain a word the
        // card branch would otherwise claim, and a string that says "wallet"
        // outright beats a brand name that could be either.
        return match (true) {
            self::has($flat, ['gift card', 'giftcard', 'store credit', 'shop credit']) => PaymentInstrument::GiftCard,
            self::has($flat, ['wallet']) => PaymentInstrument::Wallet,
            self::has($flat, ['net banking', 'netbanking', 'internet banking']) => PaymentInstrument::NetBanking,
            self::has($flat, ['upi', 'gpay', 'google pay', 'phonepe', 'phone pe', 'bhim']) => PaymentInstrument::Upi,
            self::has($flat, ['pay later', 'paylater', 'emi', 'simpl', 'lazypay', 'zestmoney', 'snapmint', 'sezzle', 'klarna', 'afterpay', 'affirm', 'bnpl']) => PaymentInstrument::PayLater,
            self::has($flat, ['card', 'credit', 'debit', 'visa', 'mastercard', 'master card', 'rupay', 'amex', 'american express', 'maestro', 'diners', 'discover', 'jcb']) => PaymentInstrument::Card,
            // Brand names only where the brand IS the instrument. Bare "Paytm"
            // or "Cashfree" is a gateway that sells all of them, so it is left
            // unclassified on purpose.
            self::has($flat, ['paytm balance', 'mobikwik', 'freecharge', 'amazon pay balance', 'airtel money', 'jio money', 'ola money', 'payzapp']) => PaymentInstrument::Wallet,
            default => null,
        };
    }

    /**
     * Short tokens like `upi` and `emi` are matched on word boundaries so they
     * cannot fire inside an unrelated word; longer phrases match anywhere.
     *
     * @param  list<string>  $needles
     */
    private static function has(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            $matched = strlen($needle) <= 4
                ? preg_match('/\b'.preg_quote($needle, '/').'\b/', $haystack) === 1
                : str_contains($haystack, $needle);

            if ($matched) {
                return true;
            }
        }

        return false;
    }
}
