<?php

declare(strict_types=1);

namespace RoundlyConsulting\Metrics\Types\Trend;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Metrics\Concerns\ComputesTrend;
use RoundlyConsulting\Metrics\Metrics;
use RoundlyConsulting\Metrics\Types\Result;

abstract class Trend extends Metrics
{
    use ComputesTrend;

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     */
    protected function count(Builder $query, string $column, ?string $dateColumn = null): Result
    {
        return $this->resolveTrend($query, 'count', $column, $dateColumn);
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     */
    protected function average(Builder $query, string $column, ?string $dateColumn = null): Result
    {
        return $this->resolveTrend($query, 'avg', $column, $dateColumn);
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     */
    protected function sum(Builder $query, string $column, ?string $dateColumn = null): Result
    {
        return $this->resolveTrend($query, 'sum', $column, $dateColumn);
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     */
    protected function max(Builder $query, string $column, ?string $dateColumn = null): Result
    {
        return $this->resolveTrend($query, 'max', $column, $dateColumn);
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     */
    protected function min(Builder $query, string $column, ?string $dateColumn = null): Result
    {
        return $this->resolveTrend($query, 'min', $column, $dateColumn);
    }
}
