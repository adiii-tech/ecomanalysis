<?php

declare(strict_types=1);

use App\Domain\Sales\Support\PaymentInstrumentResolver;
use App\Enums\PaymentInstrument;

it('reads the instrument out of however the gateway spelled it', function (string $method, PaymentInstrument $expected): void {
    expect(PaymentInstrumentResolver::resolve($method))->toBe($expected);
})->with([
    'razorpay upi' => ['upi', PaymentInstrument::Upi],
    'cashfree upi' => ['UPI', PaymentInstrument::Upi],
    'upi app' => ['Google Pay', PaymentInstrument::Upi],
    'paytm on upi rails' => ['Paytm UPI', PaymentInstrument::Upi],
    'card word' => ['Credit Card', PaymentInstrument::Card],
    'card network only' => ['RuPay', PaymentInstrument::Card],
    'unspaced network' => ['MasterCard', PaymentInstrument::Card],
    'debit' => ['Debit Card', PaymentInstrument::Card],
    'spaced net banking' => ['Net Banking', PaymentInstrument::NetBanking],
    'unspaced netbanking' => ['netbanking', PaymentInstrument::NetBanking],
    'wallet' => ['Wallet', PaymentInstrument::Wallet],
    'wallet brand' => ['MobiKwik', PaymentInstrument::Wallet],
    'pay later' => ['Simpl', PaymentInstrument::PayLater],
    'emi' => ['EMI', PaymentInstrument::PayLater],
    'gift card' => ['Gift Card', PaymentInstrument::GiftCard],
    'store credit' => ['Store Credit', PaymentInstrument::GiftCard],
]);

it('prefers an explicit wallet over the brand that also runs UPI', function (): void {
    expect(PaymentInstrumentResolver::resolve('PhonePe Wallet'))->toBe(PaymentInstrument::Wallet);
});

it('does not let a gift card be counted as a card', function (): void {
    expect(PaymentInstrumentResolver::resolve('Gift card'))->not->toBe(PaymentInstrument::Card);
});

it('falls back to the gateway only when the method says nothing', function (): void {
    expect(PaymentInstrumentResolver::resolve(null, 'Razorpay UPI'))->toBe(PaymentInstrument::Upi)
        ->and(PaymentInstrumentResolver::resolve('Visa', 'Razorpay UPI'))->toBe(PaymentInstrument::Card);
});

it('leaves a bare gateway name unclassified rather than guessing', function (string $name): void {
    expect(PaymentInstrumentResolver::resolve($name))->toBe(PaymentInstrument::Other);
})->with(['Razorpay', 'Cashfree', 'Paytm', 'shopify_payments', '', 'manual']);

it('does not match a short token inside an unrelated word', function (): void {
    // "premium" contains "emi"; a substring match would call it pay-later.
    expect(PaymentInstrumentResolver::resolve('Premium Gateway'))->toBe(PaymentInstrument::Other);
});
