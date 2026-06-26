<?php

declare(strict_types=1);

namespace RoundlyConsulting\Metrics\Types\Value;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Metrics\Metrics;
use RoundlyConsulting\Metrics\Ranges\Range;
use RoundlyConsulting\Metrics\Traits\PercentageCalculator;
use RoundlyConsulting\Metrics\Types\Result;

abstract class Value extends Metrics
{
    use PercentageCalculator;

    protected bool $withChange = false;

    public function withChangeAgainstPreviousPeriod(bool $withChange = true): self
    {
        $this->withChange = $withChange;

        return $this;
    }

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

    /**
     * Hook for subclasses (e.g. Progress) to transform the value result.
     */
    protected function resolveResult(ValueResult $result): Result
    {
        return $result;
    }

    /**
     * @param  Builder<Model>  $query
     */
    protected function aggregate(Builder $query, string $function, ?string $column = null, ?string $dateColumn = null): ValueResult
    {
        $column = $column ?? $query->getModel()->getQualifiedKeyName();
        $dateColumn = $dateColumn ?? $query->getModel()->getQualifiedCreatedAtColumn();

        if ($this->range === 'ALL') {
            return new ValueResult(value: $this->getResult(
                query: $query,
                function: $function,
                column: $column,
            ));
        }

        $result = $this->getResultForRange(
            query: $query,
            range: $this->getRange(),
            function: $function,
            column: $column,
            dateColumn: $dateColumn,
        );

        if (! $this->withChange) {
            return new ValueResult(value: $result);
        }

        $previousResult = $this->getResultForRange(
            query: $query,
            range: $this->getRange()->previous(),
            function: $function,
            column: $column,
            dateColumn: $dateColumn,
        );

        return new ValueResult(
            value: $result,
            previous: $previousResult,
            change: $this->calculateChangePercentage(
                current: $result,
                previous: $previousResult,
                roundingPrecision: $this->roundingPrecision,
                roundingMode: $this->roundingMode,
            ),
        );
    }

    /**
     * @param  Builder<Model>  $query
     */
    protected function getResult(Builder $query, string $function, string $column): float
    {
        return round(
            (float) ((clone $query)->{$function}($column) ?? 0),
            $this->roundingPrecision,
            $this->roundingMode
        );
    }

    /**
     * @param  Builder<Model>  $query
     */
    protected function getResultForRange(
        Builder $query,
        Range $range,
        string $function,
        string $column,
        string $dateColumn
    ): float {
        return $this->getResult(
            query: (clone $query)->whereBetween(
                $dateColumn, [$range->start(), $range->end()]
            ),
            function: $function,
            column: $column,
        );
    }
}
