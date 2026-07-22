<?php

declare(strict_types=1);

namespace RoundlyConsulting\Metrics\Types\Partition;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Metrics\Concerns\ComputesPartition;
use RoundlyConsulting\Metrics\Metrics;

abstract class Partition extends Metrics
{
    use ComputesPartition;

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
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
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
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
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
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
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
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
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     */
    protected function min(
        Builder $query,
        string $groupBy,
        ?string $valueColumn = null,
        ?string $dateColumn = null
    ): PartitionResult {
        return $this->aggregate($query, 'min', $groupBy, $valueColumn, $dateColumn);
    }
}
