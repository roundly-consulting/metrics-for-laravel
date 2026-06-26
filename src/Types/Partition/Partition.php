<?php

declare(strict_types=1);

namespace RoundlyConsulting\Metrics\Types\Partition;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Metrics\Metrics;
use RoundlyConsulting\Metrics\Support\RawExpression;

abstract class Partition extends Metrics
{
    /**
     * @param  Builder<Model>  $query
     */
    protected function count(
        Builder $query,
        string $groupBy,
        ?string $valueColumn = null,
        ?string $dateColumn = null
    ): PartitionResult {
        return $this->aggregate($query, 'count', $groupBy, $valueColumn, $dateColumn);
    }

    /**
     * @param  Builder<Model>  $query
     */
    protected function average(
        Builder $query,
        string $groupBy,
        ?string $valueColumn = null,
        ?string $dateColumn = null
    ): PartitionResult {
        return $this->aggregate($query, 'avg', $groupBy, $valueColumn, $dateColumn);
    }

    /**
     * @param  Builder<Model>  $query
     */
    protected function sum(
        Builder $query,
        string $groupBy,
        ?string $valueColumn = null,
        ?string $dateColumn = null
    ): PartitionResult {
        return $this->aggregate($query, 'sum', $groupBy, $valueColumn, $dateColumn);
    }

    /**
     * @param  Builder<Model>  $query
     */
    protected function max(
        Builder $query,
        string $groupBy,
        ?string $valueColumn = null,
        ?string $dateColumn = null
    ): PartitionResult {
        return $this->aggregate($query, 'max', $groupBy, $valueColumn, $dateColumn);
    }

    /**
     * @param  Builder<Model>  $query
     */
    protected function min(
        Builder $query,
        string $groupBy,
        ?string $valueColumn = null,
        ?string $dateColumn = null
    ): PartitionResult {
        return $this->aggregate($query, 'min', $groupBy, $valueColumn, $dateColumn);
    }

    /**
     * @param  Builder<Model>  $query
     */
    protected function aggregate(
        Builder $query,
        string $function,
        string $groupBy,
        ?string $valueColumn = null,
        ?string $dateColumn = null
    ): PartitionResult {
        $dateColumn = $dateColumn ?? $query->getModel()->getQualifiedCreatedAtColumn();

        return new PartitionResult(
            results: $this->getResults($query, $function, $groupBy, $dateColumn, $valueColumn),
        );
    }

    /**
     * @param  Builder<Model>  $query
     * @return array<array-key, float>
     */
    protected function getResults(Builder $query, string $function, string $groupBy, string $dateColumn, ?string $valueColumn): array
    {
        $grammar = $query->getQuery()->getGrammar();

        $groupBy = $grammar->wrap($groupBy);
        $valueColumn = $valueColumn ? $grammar->wrap($valueColumn) : $groupBy;

        // Resolve to the base query builder with global scopes applied so the raw
        // aggregate/grouping SQL runs against the same constraints (e.g. soft deletes).
        $results = $query->applyScopes()->getQuery()
            ->select([
                new RawExpression("{$groupBy} as aggregate_partition"),
                new RawExpression("{$function}({$valueColumn}) as aggregate"),
            ])
            ->groupBy('aggregate_partition')
            ->orderByDesc('aggregate');

        if ($this->range !== 'ALL') {
            $range = $this->getRange();
            $results = $results->whereBetween($dateColumn, [$range->start(), $range->end()]);
        }

        return $results
            ->pluck('aggregate', 'aggregate_partition')
            ->map(fn (mixed $aggregate): float => round(
                (float) $aggregate,
                $this->roundingPrecision,
                $this->roundingMode,
            ))
            ->all();
    }
}
