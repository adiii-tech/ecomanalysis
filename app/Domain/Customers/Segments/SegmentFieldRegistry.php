<?php

declare(strict_types=1);

namespace App\Domain\Customers\Segments;

/**
 * The fields a segment may be built from, and how each one behaves.
 *
 * A segment is a saved query against customer data, so the field list is a
 * whitelist: the builder can never reach a column it was not given, and no
 * user-supplied string reaches the query as SQL.
 */
class SegmentFieldRegistry
{
    public const TYPE_MONEY = 'money';

    public const TYPE_NUMBER = 'number';

    public const TYPE_TEXT = 'text';

    public const TYPE_DATE = 'date';

    public const TYPE_ENUM = 'enum';

    public const TYPE_BOOLEAN = 'boolean';

    /** @return array<string, array<string, mixed>> */
    public static function fields(): array
    {
        return [
            'total_spent' => ['label' => 'Lifetime spend', 'type' => self::TYPE_MONEY, 'column' => 'total_spent'],
            'ltv' => ['label' => 'Predicted LTV', 'type' => self::TYPE_MONEY, 'column' => 'ltv'],
            'aov' => ['label' => 'Average order value', 'type' => self::TYPE_MONEY, 'column' => 'aov'],
            'total_margin' => ['label' => 'Margin earned', 'type' => self::TYPE_MONEY, 'column' => 'total_margin'],
            'orders_count' => ['label' => 'Orders placed', 'type' => self::TYPE_NUMBER, 'column' => 'orders_count'],
            'returns_count' => ['label' => 'Returns', 'type' => self::TYPE_NUMBER, 'column' => 'returns_count'],
            'days_since_last_order' => ['label' => 'Days since last order', 'type' => self::TYPE_NUMBER, 'column' => 'days_since_last_order'],
            'churn_risk_score' => ['label' => 'Churn risk score', 'type' => self::TYPE_NUMBER, 'column' => 'churn_risk_score'],
            'first_order_at' => ['label' => 'First ordered', 'type' => self::TYPE_DATE, 'column' => 'first_order_at'],
            'last_order_at' => ['label' => 'Last ordered', 'type' => self::TYPE_DATE, 'column' => 'last_order_at'],
            'state' => ['label' => 'State', 'type' => self::TYPE_TEXT, 'column' => 'state'],
            'city' => ['label' => 'City', 'type' => self::TYPE_TEXT, 'column' => 'city'],
            'rfm_segment' => [
                'label' => 'RFM segment',
                'type' => self::TYPE_ENUM,
                'column' => 'rfm_segment',
                'options' => ['champions', 'loyal', 'potential', 'new', 'at_risk', 'hibernating', 'lost'],
            ],
            'is_vip' => ['label' => 'VIP', 'type' => self::TYPE_BOOLEAN, 'column' => 'is_vip'],
            'accepts_marketing' => ['label' => 'Accepts marketing', 'type' => self::TYPE_BOOLEAN, 'column' => 'accepts_marketing'],
        ];
    }

    /** @return list<string> */
    public static function operatorsFor(string $type): array
    {
        return match ($type) {
            self::TYPE_MONEY, self::TYPE_NUMBER => ['gt', 'gte', 'lt', 'lte', 'eq', 'between'],
            self::TYPE_DATE => ['before', 'after', 'within_days', 'not_within_days'],
            self::TYPE_TEXT => ['eq', 'not_eq', 'contains', 'in'],
            self::TYPE_ENUM => ['eq', 'not_eq', 'in'],
            self::TYPE_BOOLEAN => ['is'],
            default => ['eq'],
        };
    }

    public static function has(string $field): bool
    {
        return array_key_exists($field, self::fields());
    }

    /** @return array<string, mixed>|null */
    public static function get(string $field): ?array
    {
        return self::fields()[$field] ?? null;
    }

    /**
     * The catalogue the builder UI renders from, so the front end never invents
     * a field or an operator the back end will reject.
     *
     * @return list<array<string, mixed>>
     */
    public static function catalogue(): array
    {
        return collect(self::fields())
            ->map(static fn (array $meta, string $key): array => [
                'key' => $key,
                'label' => $meta['label'],
                'type' => $meta['type'],
                'operators' => self::operatorsFor($meta['type']),
                'options' => $meta['options'] ?? null,
            ])
            ->values()
            ->all();
    }
}
