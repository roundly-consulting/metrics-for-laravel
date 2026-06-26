<?php

declare(strict_types=1);

namespace RoundlyConsulting\Metrics\Types\Value;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Metrics\Concerns\AggregatesValues;
use RoundlyConsulting\Metrics\Metrics;
use RoundlyConsulting\Metrics\Types\Result;

abstract class Value extends Metrics
{
    use AggregatesValues;

    /**
     * @param  Builder<Model>  $query
     */
    protected function count(Builder $query, ?string $column = null, ?string $dateColumn = null): Result
    {
        return $this->resolveResult($this->aggregate($query, 'count', $column, $dateColumn));
    }

    /**
     * @param  Builder<Model>  $query
     */
    protected function average(Builder $query, ?string $column = null, ?string $dateColumn = null): Result
    {
        return $this->resolveResult($this->aggregate($query, 'avg', $column, $dateColumn));
    }

    /**
     * @param  Builder<Model>  $query
     */
    protected function sum(Builder $query, ?string $column = null, ?string $dateColumn = null): Result
    {
        return $this->resolveResult($this->aggregate($query, 'sum', $column, $dateColumn));
    }

    /**
     * @param  Builder<Model>  $query
     */
    protected function max(Builder $query, ?string $column = null, ?string $dateColumn = null): Result
    {
        return $this->resolveResult($this->aggregate($query, 'max', $column, $dateColumn));
    }

    /**
     * @param  Builder<Model>  $query
     */
    protected function min(Builder $query, ?string $column = null, ?string $dateColumn = null): Result
    {
        return $this->resolveResult($this->aggregate($query, 'min', $column, $dateColumn));
    }
}
