<?php

declare(strict_types=1);

namespace App\Domain\Sales\Support;

use App\Enums\PaymentMode;

/**
 * Reads how an order was paid for out of its checkout gateway names.
 *
 * Every checkout spells cash on delivery differently — Shopify's own
 * `cash_on_delivery`, a manual method named "Cash on Delivery (COD)", GoKwik's
 * partial-prepaid "Gokwik PPCOD" — so separators are flattened before matching.
 * Reading it wrong moves COD orders into prepaid and takes the COD charge, the
 * gateway fee and every RTO number with them.
 */
final class PaymentModeResolver
{
    /** @param list<string> $gatewayNames */
    public static function fromGateways(array $gatewayNames): PaymentMode
    {
        $flattened = strtolower((string) preg_replace('/[^a-zA-Z0-9]+/', ' ', implode(' ', $gatewayNames)));

        return str_contains($flattened, 'cash on delivery')
            || str_contains($flattened, 'pay on delivery')
            || preg_match('/\b(pp)?cod\b/', $flattened) === 1
                ? PaymentMode::Cod
                : PaymentMode::Prepaid;
    }

    /**
     * The gateway names as the checkout reported them, kept on the order so a
     * questioned payment mode can be checked against the source.
     *
     * @param  list<string>  $gatewayNames
     */
    public static function label(array $gatewayNames): ?string
    {
        $names = array_values(array_filter(array_map(
            // Checkout apps pad names with invisible characters; they only make the label unreadable.
            static fn (string $name): string => trim((string) preg_replace('/[\p{Z}\p{Cf}\s]+/u', ' ', $name)),
            $gatewayNames,
        )));

        return $names === [] ? null : mb_substr(implode(', ', $names), 0, 64);
    }
}
