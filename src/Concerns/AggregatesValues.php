<?php

declare(strict_types=1);

namespace RoundlyConsulting\Metrics\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Metrics\Enums\Period;
use RoundlyConsulting\Metrics\Exceptions\InvalidRangeException;
use RoundlyConsulting\Metrics\Ranges\Range;
use RoundlyConsulting\Metrics\Traits\PercentageCalculator;
use RoundlyConsulting\Metrics\Types\Result;
use RoundlyConsulting\Metrics\Types\Value\ValueResult;

trait AggregatesValues
{
    use PercentageCalculator;

    protected bool $withChange = false;

    protected ?string $compareTo = null;

    protected ?string $compareToStart = null;

    protected ?string $compareToEnd = null;

    public function withChangeAgainstPreviousPeriod(bool $withChange = true): self
    {
        $this->withChange = $withChange;

        return $this;
    }

    /**
     * Compare the current value against an arbitrary range instead of the
     * immediately-preceding period. Implies withChangeAgainstPreviousPeriod().
     */
    public function compareTo(Period|string $range, ?string $customRangeStart = null, ?string $customRangeEnd = null): self
    {
        $this->compareTo = $range instanceof Period ? $range->value : $range;
        $this->compareToStart = $customRangeStart;
        $this->compareToEnd = $customRangeEnd;
        $this->withChange = true;

        return $this;
    }

    /**
     * @return array<array-key, mixed>
     */
    protected function cacheDiscriminators(): array
    {
        return [
            'change' => $this->withChange,
            'compare' => $this->compareTo,
            'compare_start' => $this->compareToStart,
            'compare_end' => $this->compareToEnd,
        ];
    }

    /**
     * Resolve the range the current value is compared against.
     */
    protected function comparisonRange(): Range
    {
        if ($this->compareTo === null) {
            return $this->getRange()->previous();
        }

        $period = Period::tryFrom($this->compareTo);

        if ($period === null) {
            throw InvalidRangeException::for($this->compareTo);
        }

        return $period->toRange($this->compareToStart, $this->compareToEnd)
            ->usingTimezone($this->timezoneOverride);
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
            range: $this->comparisonRange(),
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
