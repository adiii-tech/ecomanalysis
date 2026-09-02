<?php

declare(strict_types=1);

namespace App\Domain\Customers\Segments;

use App\Models\Customer;
use App\Models\CustomerSegment;
use Illuminate\Support\Collection;

/**
 * Turns a segment into the file each destination actually accepts.
 *
 * Meta wants SHA-256 hashes of normalised emails and phones — the raw values
 * never leave this system for that destination. Klaviyo and WhatsApp need real
 * identifiers, so those exports carry plaintext and say so plainly.
 */
class SegmentExporter
{
    public const DESTINATIONS = [
        'meta' => [
            'label' => 'Meta Custom Audience',
            'format' => 'csv',
            'pii' => 'hashed',
            'note' => 'Emails and phones are SHA-256 hashed the way Meta requires, so no raw contact detail leaves your account.',
        ],
        'klaviyo' => [
            'label' => 'Klaviyo list',
            'format' => 'csv',
            'pii' => 'plain',
            'note' => 'Klaviyo matches on the email itself, so this file contains real addresses. Only export it if your privacy policy allows it.',
        ],
        'whatsapp' => [
            'label' => 'WhatsApp broadcast',
            'format' => 'csv',
            'pii' => 'plain',
            'note' => 'Phone numbers in E.164. Customers who never opted into marketing are excluded.',
        ],
        'csv' => [
            'label' => 'Plain CSV',
            'format' => 'csv',
            'pii' => 'masked',
            'note' => 'Masked contact details, for analysis rather than for sending.',
        ],
    ];

    public function __construct(private readonly SegmentBuilder $builder) {}

    /**
     * @return array{rows: list<array<string, mixed>>, headers: list<string>, note: string, excluded: int}
     */
    public function export(CustomerSegment $segment, string $destination, bool $unmask = false): array
    {
        $meta = self::DESTINATIONS[$destination] ?? self::DESTINATIONS['csv'];

        $query = $this->builder->query($segment->rules);
        $total = (clone $query)->count();

        // Marketing destinations only ever receive people who agreed to be
        // marketed to; that filter is not optional.
        if (in_array($destination, ['meta', 'klaviyo', 'whatsapp'], true)) {
            $query->where('accepts_marketing', true);
        }

        $customers = $query->limit(50000)->get();

        return [
            'headers' => $this->headers($destination),
            'rows' => $this->rows($customers, $destination, $meta['pii'], $unmask),
            'note' => $meta['note'],
            'excluded' => max(0, $total - $customers->count()),
        ];
    }

    /** @return list<string> */
    private function headers(string $destination): array
    {
        return match ($destination) {
            'meta' => ['email', 'phone', 'fn', 'ct', 'st', 'country'],
            'klaviyo' => ['email', 'first_name', 'city', 'state', 'orders', 'total_spent'],
            'whatsapp' => ['phone', 'name', 'city', 'last_order_at'],
            default => ['customer_id', 'name', 'email', 'phone', 'city', 'state', 'orders', 'total_spent', 'rfm_segment'],
        };
    }

    /**
     * @param  Collection<int, Customer>  $customers
     * @return list<array<string, mixed>>
     */
    private function rows(Collection $customers, string $destination, string $pii, bool $unmask): array
    {
        return $customers->map(function (Customer $customer) use ($destination, $pii, $unmask): array {
            $email = $customer->email_encrypted;
            $phone = $customer->phone_encrypted;

            return match ($destination) {
                'meta' => [
                    'email' => $this->hash($email),
                    'phone' => $this->hash($this->normalisePhone($phone)),
                    'fn' => $this->hash($customer->name),
                    'ct' => $this->hash($customer->city),
                    'st' => $this->hash($customer->state),
                    'country' => $this->hash('in'),
                ],
                'klaviyo' => [
                    'email' => $email,
                    'first_name' => str($customer->name ?? '')->before(' ')->toString(),
                    'city' => $customer->city,
                    'state' => $customer->state,
                    'orders' => $customer->orders_count,
                    'total_spent' => round($customer->total_spent / 100, 2),
                ],
                'whatsapp' => [
                    'phone' => $this->normalisePhone($phone, plus: false),
                    'name' => $customer->name,
                    'city' => $customer->city,
                    'last_order_at' => $customer->last_order_at?->toDateString(),
                ],
                default => [
                    'customer_id' => $customer->id,
                    'name' => $customer->name,
                    'email' => $pii === 'plain' || $unmask ? $email : $customer->masked_email,
                    'phone' => $pii === 'plain' || $unmask ? $phone : $customer->masked_phone,
                    'city' => $customer->city,
                    'state' => $customer->state,
                    'orders' => $customer->orders_count,
                    'total_spent' => round($customer->total_spent / 100, 2),
                    'rfm_segment' => $customer->rfm_segment?->value,
                ],
            };
        })->values()->all();
    }

    /** Meta expects lower-cased, trimmed values hashed with SHA-256. */
    private function hash(?string $value): ?string
    {
        $normalised = trim(mb_strtolower((string) $value));

        return $normalised === '' ? null : hash('sha256', $normalised);
    }

    private function normalisePhone(?string $phone, bool $plus = true): ?string
    {
        if ($phone === null || $phone === '') {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        // Indian numbers are stored both with and without the country code.
        if (strlen($digits) === 10) {
            $digits = '91'.$digits;
        }

        return $digits === '' ? null : ($plus ? '+'.$digits : $digits);
    }
}
