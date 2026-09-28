<?php

declare(strict_types=1);

namespace RoundlyConsulting\Metrics\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;
use RoundlyConsulting\Metrics\Enums\Unit as UnitEnum;
use RoundlyConsulting\Metrics\Exceptions\MissingTrendQueryExpressionException;
use RoundlyConsulting\Metrics\Ranges\Custom;
use RoundlyConsulting\Metrics\Ranges\Range;
use RoundlyConsulting\Metrics\Support\RawExpression;
use RoundlyConsulting\Metrics\Traits\Unit;
use RoundlyConsulting\Metrics\Types\Trend\QueryExpressions\QueryExpression;
use RoundlyConsulting\Metrics\Types\Trend\TrendResult;
use stdClass;

trait ComputesTrend
{
    use Unit;

    /**
     * @var array<string, class-string<QueryExpression>>
     */
    public static array $queryExpressions = [];

    protected bool $gapFilling = true;

    protected ?string $seriesColumn = null;

    protected function applyConfiguredDefaults(): void
    {
        parent::applyConfiguredDefaults();

        $unit = config('metrics.default_unit');

        if (is_string($unit) && ($resolved = UnitEnum::tryFrom($unit)) !== null) {
            $this->unit = $resolved;
        }
    }

    /**
     * Stop filling empty buckets with zero. By default every bucket in the
     * selected range is present; opting out returns only buckets with rows.
     */
    public function withoutGapFilling(bool $withoutGapFilling = true): static
    {
        $this->gapFilling = ! $withoutGapFilling;

        return $this;
    }

    /**
     * Split the trend by a dimension into multiple series over time, exposed
     * under an additive "series" key alongside the combined "trends" totals.
     */
    public function groupBy(string $column): static
    {
        $this->seriesColumn = $column;

        return $this;
    }

    /**
     * @return array<array-key, mixed>
     */
    protected function cacheDiscriminators(): array
    {
        return [
            'unit' => $this->unit->value,
            'gap_filling' => $this->gapFilling,
            'series' => $this->seriesColumn,
        ];
    }

    /**
     * The metric's result, calculated or restored from the result cache.
     */
    public function result(): TrendResult
    {
        return $this->resultOf(TrendResult::class);
    }

    /**
     * Resolve the trend, branching to the grouped path when a series column is set.
     *
     * @param  Builder<covariant Model>  $query
     */
    protected function resolveTrend(Builder $query, string $function, string $column, ?string $dateColumn = null): TrendResult
    {
        if ($this->seriesColumn === null) {
            return $this->toResult($this->aggregate($query, $function, $column, $dateColumn));
        }

        return $this->toSeriesResult($query, $function, $column, $dateColumn);
    }

    /**
     * @param  Collection<array-key, mixed>  $aggregateResults
     */
    protected function toResult(Collection $aggregateResults): TrendResult
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
     * @param  Builder<covariant Model>  $query
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
        if (! $this->gapFilling) {
            return $aggregateResults->mapWithKeys(fn (mixed $value, int|string $key): array => [
                (string) $key => round(
                    num: (float) $value,
                    precision: $this->roundingPrecision,
                    mode: $this->roundingMode,
                ),
            ]);
        }

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
     * Compute a grouped, multi-series trend.
     *
     * @param  Builder<covariant Model>  $query
     */
    protected function toSeriesResult(Builder $query, string $function, string $column, ?string $dateColumn): TrendResult
    {
        $dateColumn = $dateColumn ?? $query->getModel()->getQualifiedCreatedAtColumn();

        $rows = $this->aggregateSeries($query, $function, $column, (string) $this->seriesColumn, $dateColumn);

        /** @var array<string, array<string, float>> $bySeries */
        $bySeries = [];

        foreach ($rows as $row) {
            $seriesKey = (string) $row['aggregate_series'];
            $bySeries[$seriesKey][(string) $row['aggregate_date']] = (float) $row['aggregate'];
        }

        $range = $this->seriesRange($rows);

        if ($range === null) {
            return new TrendResult;
        }

        $series = [];

        foreach ($bySeries as $seriesKey => $buckets) {
            $series[$seriesKey] = $this->mergeRangeDatesWithAggregateResults($range, collect($buckets))->all();
        }

        return new TrendResult(results: $this->totalsAcrossSeries($series), series: $series);
    }

    /**
     * @param  Collection<int, array<array-key, mixed>>  $rows
     */
    protected function seriesRange(Collection $rows): ?Range
    {
        if ($this->range !== 'ALL') {
            return $this->getRange();
        }

        if ($rows->isEmpty()) {
            return null;
        }

        $dates = $rows->map(fn (array $row): string => (string) $row['aggregate_date'])->sort()->values();

        return new Custom(
            start: $this->fromUnitFormatToDatetime((string) $dates->first())->toDateTimeString(),
            end: $this->fromUnitFormatToDatetime((string) $dates->last(), true)->toDateTimeString(),
        );
    }

    /**
     * @param  array<string, array<string, float>>  $series
     * @return array<string, float>
     */
    protected function totalsAcrossSeries(array $series): array
    {
        $totals = [];

        foreach ($series as $buckets) {
            foreach ($buckets as $date => $value) {
                $totals[$date] = ($totals[$date] ?? 0.0) + $value;
            }
        }

        ksort($totals);

        return $totals;
    }

    /**
     * @param  Builder<covariant Model>  $query
     * @return Collection<int, array<array-key, mixed>>
     */
    protected function aggregateSeries(Builder $query, string $function, string $column, string $series, string $dateColumn): Collection
    {
        $grammar = $query->getQuery()->getGrammar();

        $column = $grammar->wrap($column);
        $series = $grammar->wrap($series);

        $expression = $this->resolveQueryExpression($query)->toSql($this->unit->value, $dateColumn);

        return $query->applyScopes()->getQuery()
            ->select([
                new RawExpression("{$function}({$column}) as aggregate"),
                new RawExpression("{$expression} as aggregate_date"),
                new RawExpression("{$series} as aggregate_series"),
            ])
            ->when($this->range !== 'ALL', fn (QueryBuilder $query) => $query->whereBetween(
                column: $dateColumn,
                values: [
                    $this->getRange()->start(),
                    $this->getRange()->end(),
                ],
            ))
            ->groupBy(new RawExpression($expression), new RawExpression($series))
            ->orderBy('aggregate_date')
            ->get()
            ->map(fn (stdClass $row): array => (array) $row);
    }

    /**
     * @param  Builder<covariant Model>  $query
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
