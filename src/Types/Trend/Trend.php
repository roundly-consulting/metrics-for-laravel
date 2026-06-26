<?php

declare(strict_types=1);

namespace RoundlyConsulting\Metrics\Types\Trend;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;
use RoundlyConsulting\Metrics\Enums\Unit as UnitEnum;
use RoundlyConsulting\Metrics\Exceptions\MissingTrendQueryExpressionException;
use RoundlyConsulting\Metrics\Metrics;
use RoundlyConsulting\Metrics\Ranges\Custom;
use RoundlyConsulting\Metrics\Ranges\Range;
use RoundlyConsulting\Metrics\Support\RawExpression;
use RoundlyConsulting\Metrics\Traits\Unit;
use RoundlyConsulting\Metrics\Types\Result;
use RoundlyConsulting\Metrics\Types\Trend\QueryExpressions\QueryExpression;

abstract class Trend extends Metrics
{
    use Unit;

    /**
     * @var array<string, class-string<QueryExpression>>
     */
    public static array $queryExpressions = [];

    protected function applyConfiguredDefaults(): void
    {
        parent::applyConfiguredDefaults();

        $unit = config('metrics.default_unit');

        if (is_string($unit) && ($resolved = UnitEnum::tryFrom($unit)) !== null) {
            $this->unit = $resolved;
        }
    }

    /**
     * @param  Builder<Model>  $query
     */
    protected function count(Builder $query, string $column, ?string $dateColumn = null): Result
    {
        return $this->toResult($this->aggregate($query, 'count', $column, $dateColumn));
    }

    /**
     * @param  Builder<Model>  $query
     */
    protected function average(Builder $query, string $column, ?string $dateColumn = null): Result
    {
        return $this->toResult($this->aggregate($query, 'avg', $column, $dateColumn));
    }

    /**
     * @param  Builder<Model>  $query
     */
    protected function sum(Builder $query, string $column, ?string $dateColumn = null): Result
    {
        return $this->toResult($this->aggregate($query, 'sum', $column, $dateColumn));
    }

    /**
     * @param  Builder<Model>  $query
     */
    protected function max(Builder $query, string $column, ?string $dateColumn = null): Result
    {
        return $this->toResult($this->aggregate($query, 'max', $column, $dateColumn));
    }

    /**
     * @param  Builder<Model>  $query
     */
    protected function min(Builder $query, string $column, ?string $dateColumn = null): Result
    {
        return $this->toResult($this->aggregate($query, 'min', $column, $dateColumn));
    }

    /**
     * @param  Collection<array-key, mixed>  $aggregateResults
     */
    protected function toResult(Collection $aggregateResults): Result
    {
        if ($this->range === 'ALL') {
            if ($aggregateResults->isEmpty()) {
                return new TrendResult;
            }

            $range = new Custom(
                start: $this->fromUnitFormatToDatetime((string) $aggregateResults->keys()->first())->toDateTimeString(),
                end: $this->fromUnitFormatToDatetime((string) $aggregateResults->keys()->last(), true)->toDateTimeString(),
            );
        } else {
            $range = $this->getRange();
        }

        return new TrendResult(
            results: $this->mergeRangeDatesWithAggregateResults($range, $aggregateResults)->all(),
        );
    }

    /**
     * @param  Builder<Model>  $query
     * @return Collection<array-key, mixed>
     */
    protected function aggregate(Builder $query, string $function, string $column, ?string $dateColumn = null): Collection
    {
        $grammar = $query->getQuery()->getGrammar();

        $column = $grammar->wrap($column);
        $dateColumn = $dateColumn ?? $query->getModel()->getQualifiedCreatedAtColumn();

        $expression = $this->resolveQueryExpression($query)->toSql($this->unit->value, $dateColumn);

        // Resolve to the base query builder with global scopes applied so the raw
        // aggregate/grouping SQL runs against the same constraints (e.g. soft deletes).
        return $query->applyScopes()->getQuery()
            ->select([
                new RawExpression("{$function}({$column}) as aggregate"),
                new RawExpression("{$expression} as aggregate_date"),
            ])
            ->when($this->range !== 'ALL', fn (QueryBuilder $query) => $query->whereBetween(
                column: $dateColumn,
                values: [
                    $this->getRange()->start(),
                    $this->getRange()->end(),
                ],
            ))
            ->groupBy(new RawExpression($expression))
            ->orderBy('aggregate_date')
            ->pluck('aggregate', 'aggregate_date');
    }

    /**
     * @param  Collection<array-key, mixed>  $aggregateResults
     * @return Collection<string, float>
     */
    protected function mergeRangeDatesWithAggregateResults(Range $range, Collection $aggregateResults): Collection
    {
        $dates = [];

        $period = $range->start()->toPeriod($range->end(), 1, $this->unit->value);

        foreach ($period as $stepInPeriod) {
            $stepInUnitFormat = $this->formatDatetimeToUnit($stepInPeriod);

            $dates[$stepInUnitFormat] = round(
                num: (float) $aggregateResults->get($stepInUnitFormat, 0),
                precision: $this->roundingPrecision,
                mode: $this->roundingMode,
            );
        }

        return collect($dates);
    }

    /**
     * @param  Builder<Model>  $query
     */
    protected function resolveQueryExpression(Builder $query): QueryExpression
    {
        $driver = $query->getModel()->getConnection()->getDriverName();

        $expressions = static::$queryExpressions + $this->configuredTrendDrivers();

        if (array_key_exists($driver, $expressions)) {
            return resolve($expressions[$driver]);
        }

        throw MissingTrendQueryExpressionException::forDriver($driver);
    }

    /**
     * @return array<string, class-string<QueryExpression>>
     */
    protected function configuredTrendDrivers(): array
    {
        $drivers = config('metrics.trend_drivers');

        if (! is_array($drivers)) {
            return [];
        }

        $valid = [];

        foreach ($drivers as $name => $class) {
            if (is_string($name) && is_string($class) && is_a($class, QueryExpression::class, true)) {
                $valid[$name] = $class;
            }
        }

        return $valid;
    }
}
