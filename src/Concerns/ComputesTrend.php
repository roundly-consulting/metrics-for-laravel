<?php

declare(strict_types=1);

namespace RoundlyConsulting\Metrics\Concerns;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;
use RoundlyConsulting\Metrics\Enums\Unit as UnitEnum;
use RoundlyConsulting\Metrics\Exceptions\InvalidConfigurationException;
use RoundlyConsulting\Metrics\Exceptions\MissingTrendQueryExpressionException;
use RoundlyConsulting\Metrics\Ranges\Custom;
use RoundlyConsulting\Metrics\Ranges\Range;
use RoundlyConsulting\Metrics\Support\MetricsConfig;
use RoundlyConsulting\Metrics\Support\RawExpression;
use RoundlyConsulting\Metrics\Support\Timezones;
use RoundlyConsulting\Metrics\Traits\Unit;
use RoundlyConsulting\Metrics\Types\Trend\QueryExpressions\QueryExpression;
use RoundlyConsulting\Metrics\Types\Trend\TrendResult;
use RoundlyConsulting\PackageToolkit\Support\Config;
use stdClass;

trait ComputesTrend
{
    use Unit;

    protected bool $gapFilling = true;

    protected ?string $seriesColumn = null;

    protected function applyConfiguredDefaults(): void
    {
        parent::applyConfiguredDefaults();

        // Not set (absent, null or blank) keeps the trend's own unit; an unknown one throws
        // instead of being ignored.
        if (! MetricsConfig::isUnset(config('metrics.default_unit'))) {
            $this->unit = Config::using(InvalidConfigurationException::class)
                ->enum('metrics.default_unit', UnitEnum::class);
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

            $range = (new Custom(
                start: $this->fromUnitFormatToDatetime((string) $aggregateResults->keys()->first())->toDateTimeString(),
                end: $this->fromUnitFormatToDatetime((string) $aggregateResults->keys()->last(), true)->toDateTimeString(),
            ))->usingTimezone($this->timezoneOverride);
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

        $expression = $this->bucketExpression($query, $dateColumn);

        return $this->scopedBase($query)
            ->select([
                new RawExpression("{$function}({$column}) as aggregate"),
                new RawExpression("{$expression} as aggregate_date"),
            ])
            ->when($this->range !== 'ALL', fn (QueryBuilder $query) => $query->whereBetween(
                column: $dateColumn,
                values: $this->storageBounds($this->getRange()),
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

        // Step from the start of the bucket that holds the range start, not the start
        // itself: a raw start (a Thursday, the 30th) steps past the bucket holding the end
        // — or, a month at a time, straight over a shorter month — and drops it.
        $period = $this->unit->startOfBucket($range->start())->toPeriod($range->end(), 1, $this->unit->value);

        foreach ($period as $stepInPeriod) {
            $dates[$this->formatDatetimeToUnit($stepInPeriod)] = 0.0;
        }

        // Every bucket the database returned is kept, even one the axis did not generate
        // (a custom driver whose keys disagree with Unit::format()): a visible stray key
        // beats real rows silently reported as 0.
        foreach ($aggregateResults as $key => $value) {
            $dates[(string) $key] = (float) $value;
        }

        ksort($dates, SORT_STRING);

        return collect($dates)->map(fn (float $value): float => round(
            num: $value,
            precision: $this->roundingPrecision,
            mode: $this->roundingMode,
        ));
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

        return (new Custom(
            start: $this->fromUnitFormatToDatetime((string) $dates->first())->toDateTimeString(),
            end: $this->fromUnitFormatToDatetime((string) $dates->last(), true)->toDateTimeString(),
        ))->usingTimezone($this->timezoneOverride);
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

        $expression = $this->bucketExpression($query, $dateColumn);

        return $this->scopedBase($query)
            ->select([
                new RawExpression("{$function}({$column}) as aggregate"),
                new RawExpression("{$expression} as aggregate_date"),
                new RawExpression("{$series} as aggregate_series"),
            ])
            ->when($this->range !== 'ALL', fn (QueryBuilder $query) => $query->whereBetween(
                column: $dateColumn,
                values: $this->storageBounds($this->getRange()),
            ))
            ->groupBy(new RawExpression($expression), new RawExpression($series))
            ->orderBy('aggregate_date')
            ->get()
            ->map(fn (stdClass $row): array => (array) $row);
    }

    /**
     * The SQL bucket key of `$dateColumn`, on the reporting clock.
     *
     * @param  Builder<covariant Model>  $query
     */
    protected function bucketExpression(Builder $query, string $dateColumn): string
    {
        $grammar = $this->resolveQueryExpression($query);

        return $grammar->toSql($this->unit->value, $this->reportingDateColumn($grammar, $query, $dateColumn));
    }

    /**
     * The date column, quoted by the query grammar and re-read on the reporting clock —
     * unchanged when it is the storage clock. The shift is computed over the span the query
     * can return: the range's bounds, or for `ALL` the stored column's own first and last
     * value.
     *
     * @param  Builder<covariant Model>  $query
     */
    protected function reportingDateColumn(QueryExpression $grammar, Builder $query, string $dateColumn): string
    {
        $wrapped = $query->getQuery()->getGrammar()->wrap($dateColumn);

        $storage = Timezones::storage();
        $reporting = $this->reportingTimezone();

        if ($storage === $reporting) {
            return $wrapped;
        }

        $span = $this->range === 'ALL'
            ? $this->storedSpan($query, $dateColumn)
            : $this->storageBounds($this->getRange());

        if ($span === null) {
            return $wrapped;
        }

        return Timezones::reportingColumn($grammar, $wrapped, $storage, $reporting, $span[0], $span[1]);
    }

    /**
     * The first and last stored value of the date column, or null when the query has no
     * rows.
     *
     * @param  Builder<covariant Model>  $query
     * @return list<CarbonImmutable>|null
     */
    protected function storedSpan(Builder $query, string $dateColumn): ?array
    {
        $wrapped = $query->getQuery()->getGrammar()->wrap($dateColumn);

        $row = $this->scopedBase($query)
            ->select([
                new RawExpression("min({$wrapped}) as span_start"),
                new RawExpression("max({$wrapped}) as span_end"),
            ])
            ->first();

        if (! $row instanceof stdClass || ! is_string($row->span_start ?? null) || ! is_string($row->span_end ?? null)) {
            return null;
        }

        $storage = Timezones::storage();

        return [CarbonImmutable::parse($row->span_start, $storage), CarbonImmutable::parse($row->span_end, $storage)];
    }

    /**
     * A copy of the base query with the model's global scopes applied (e.g. soft deletes),
     * so the raw aggregate/grouping SQL runs against the same constraints — without the
     * caller's ORDER BY, which would sort the buckets and break the GROUP BY on PostgreSQL
     * and MySQL's ONLY_FULL_GROUP_BY.
     *
     * Cloned first: `applyScopes()` hands back the builder itself when the model has no
     * global scopes, so the select/where/group added here would otherwise pile onto the
     * metric's own query — a second calculation of the same metric (another range, a
     * re-resolved registered instance) then ran with the first one's window still on it.
     *
     * @param  Builder<covariant Model>  $query
     */
    protected function scopedBase(Builder $query): QueryBuilder
    {
        return (clone $query)->applyScopes()->getQuery()->reorder();
    }

    /**
     * @param  Builder<covariant Model>  $query
     */
    protected function resolveQueryExpression(Builder $query): QueryExpression
    {
        $driver = $query->getModel()->getConnection()->getDriverName();

        $expressions = $this->configuredTrendDrivers();

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
        $drivers = config('metrics.trend_drivers') ?? [];

        if (! is_array($drivers)) {
            throw new InvalidConfigurationException(sprintf(
                'Configuration value [metrics.trend_drivers] must be a driver => class map, [%s] given.',
                get_debug_type($drivers),
            ));
        }

        $valid = [];

        // A broken entry throws naming it, rather than being dropped and surfacing later as
        // a misleading "no query expression for this driver".
        foreach ($drivers as $name => $class) {
            if (! is_string($class) || ! is_a($class, QueryExpression::class, true)) {
                throw new InvalidConfigurationException(sprintf(
                    'Configuration value [metrics.trend_drivers.%s] must be a class-string of [%s], [%s] given.',
                    $name,
                    QueryExpression::class,
                    is_scalar($class) ? var_export($class, true) : get_debug_type($class),
                ));
            }

            $valid[(string) $name] = $class;
        }

        return $valid;
    }
}
