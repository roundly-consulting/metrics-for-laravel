<?php

declare(strict_types=1);

namespace RoundlyConsulting\Metrics\Concerns;

use stdClass;

/**
 * Grouped rows combined with the metric's own aggregate — a partition's "Other" bucket, and
 * the groups whose keys collide once they are read back as array keys (NULL and '').
 *
 * @internal
 */
trait RollsUpGroups
{
    /**
     * @param  array<array-key, stdClass>  $groups
     */
    protected function rollUp(string $function, array $groups): float
    {
        $values = array_values(array_filter(
            array_map(fn (stdClass $group): ?float => is_numeric($group->aggregate) ? (float) $group->aggregate : null, $groups),
            fn (?float $value): bool => $value !== null,
        ));

        return match ($function) {
            'max' => $values === [] ? 0.0 : max($values),
            'min' => $values === [] ? 0.0 : min($values),
            'avg' => $this->weightedAverage($groups),
            default => array_sum($values),
        };
    }

    /**
     * @param  array<array-key, stdClass>  $groups
     */
    protected function weightedAverage(array $groups): float
    {
        $sum = 0.0;
        $count = 0.0;

        foreach ($groups as $group) {
            $sum += is_numeric($group->aggregate_sum ?? null) ? (float) $group->aggregate_sum : 0.0;
            $count += is_numeric($group->aggregate_count ?? null) ? (float) $group->aggregate_count : 0.0;
        }

        return $count > 0 ? $sum / $count : 0.0;
    }

    /**
     * A group value as an array key. NULL and '' share the key '', and a boolean is 0 or 1
     * — the key MySQL and SQLite already return for it, where PostgreSQL returns a bool.
     */
    protected function groupKey(mixed $value): int|string
    {
        return match (true) {
            is_int($value), is_string($value) => $value,
            is_bool($value) => (int) $value,
            is_scalar($value) => (string) $value,
            default => '',
        };
    }

    /**
     * Two rows whose keys collide, combined into one with the metric's aggregate — for an
     * average, weighted by the sum and count each row carries — so no row is dropped.
     */
    protected function mergeGroups(string $function, stdClass $group, stdClass $other): stdClass
    {
        $merged = clone $group;
        $merged->aggregate = $this->rollUp($function, [$group, $other]);

        if ($function === 'avg') {
            foreach (['aggregate_sum', 'aggregate_count'] as $part) {
                $merged->{$part} = (is_numeric($group->{$part} ?? null) ? (float) $group->{$part} : 0.0)
                    + (is_numeric($other->{$part} ?? null) ? (float) $other->{$part} : 0.0);
            }
        }

        return $merged;
    }
}
