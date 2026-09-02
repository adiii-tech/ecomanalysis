<?php

declare(strict_types=1);

namespace App\Domain\Customers\Segments;

use App\Models\Customer;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use InvalidArgumentException;

/**
 * Turns a saved rule set into a customer query.
 *
 * Rules arrive as data — field, operator, value — and every one of them is
 * checked against the whitelist before it touches the query. Nothing a user
 * types is ever interpolated into SQL.
 */
class SegmentBuilder
{
    /**
     * @param  array{match?: string, conditions?: list<array<string, mixed>>}  $rules
     * @return Builder<Customer>
     */
    public function query(array $rules): Builder
    {
        $conditions = $rules['conditions'] ?? [];
        $matchAll = ($rules['match'] ?? 'all') !== 'any';

        $query = Customer::query();

        if ($conditions === []) {
            return $query;
        }

        $query->where(function (Builder $group) use ($conditions, $matchAll): void {
            foreach ($conditions as $condition) {
                $matchAll
                    ? $group->where(fn (Builder $inner) => $this->apply($inner, $condition))
                    : $group->orWhere(fn (Builder $inner) => $this->apply($inner, $condition));
            }
        });

        return $query;
    }

    /**
     * Validates a rule set without running it, so the editor can refuse a bad
     * rule before it is saved.
     *
     * @param  array<string, mixed>  $rules
     * @return list<string> human-readable problems, empty when the set is valid
     */
    public function problems(array $rules): array
    {
        $problems = [];

        foreach (($rules['conditions'] ?? []) as $index => $condition) {
            $field = $condition['field'] ?? null;
            $operator = $condition['operator'] ?? null;
            $position = $index + 1;

            if (! is_string($field) || ! SegmentFieldRegistry::has($field)) {
                $problems[] = sprintf('Rule %d uses a field that does not exist.', $position);

                continue;
            }

            $meta = SegmentFieldRegistry::get($field);

            if (! is_string($operator) || ! in_array($operator, SegmentFieldRegistry::operatorsFor($meta['type']), true)) {
                $problems[] = sprintf('Rule %d: "%s" cannot be compared that way.', $position, $meta['label']);

                continue;
            }

            if ($operator === 'between' && ! is_array($condition['value'] ?? null)) {
                $problems[] = sprintf('Rule %d: a range needs two values.', $position);
            }

            if (($meta['type'] ?? null) === SegmentFieldRegistry::TYPE_ENUM) {
                $values = (array) ($condition['value'] ?? []);

                foreach ($values as $value) {
                    if (! in_array($value, $meta['options'], true)) {
                        $problems[] = sprintf('Rule %d: "%s" is not a valid %s.', $position, (string) $value, $meta['label']);
                    }
                }
            }
        }

        return $problems;
    }

    /**
     * @param  Builder<Customer>  $query
     * @param  array<string, mixed>  $condition
     */
    private function apply(Builder $query, array $condition): void
    {
        $field = (string) ($condition['field'] ?? '');
        $meta = SegmentFieldRegistry::get($field);

        if ($meta === null) {
            throw new InvalidArgumentException("Unknown segment field [{$field}].");
        }

        $operator = (string) ($condition['operator'] ?? 'eq');

        if (! in_array($operator, SegmentFieldRegistry::operatorsFor($meta['type']), true)) {
            throw new InvalidArgumentException("Operator [{$operator}] is not allowed on [{$field}].");
        }

        $column = $meta['column'];
        $value = $condition['value'] ?? null;

        // Money is entered in rupees and stored in paise.
        if ($meta['type'] === SegmentFieldRegistry::TYPE_MONEY) {
            $value = is_array($value)
                ? array_map(static fn ($item): int => Money::fromRupees((float) $item), $value)
                : Money::fromRupees((float) $value);
        }

        match ($operator) {
            'gt' => $query->where($column, '>', $value),
            'gte' => $query->where($column, '>=', $value),
            'lt' => $query->where($column, '<', $value),
            'lte' => $query->where($column, '<=', $value),
            'eq' => $query->where($column, '=', $value),
            'not_eq' => $query->where(fn (Builder $inner) => $inner->where($column, '!=', $value)->orWhereNull($column)),
            'between' => $query->whereBetween($column, [$value[0] ?? 0, $value[1] ?? 0]),
            'contains' => $query->where($column, 'like', '%'.$this->escapeLike((string) $value).'%'),
            'in' => $query->whereIn($column, (array) $value),
            'before' => $query->whereDate($column, '<', $value),
            'after' => $query->whereDate($column, '>', $value),
            'within_days' => $query->where($column, '>=', now()->subDays((int) $value)),
            'not_within_days' => $query->where(fn (Builder $inner) => $inner->where($column, '<', now()->subDays((int) $value))->orWhereNull($column)),
            'is' => $query->where($column, '=', (bool) $value),
            default => throw new InvalidArgumentException("Unsupported operator [{$operator}]."),
        };
    }

    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $value);
    }
}
