<?php

declare(strict_types=1);

namespace RoundlyConsulting\Metrics\Concerns;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Metrics\Support\MetricsConfig;
use RoundlyConsulting\Metrics\Support\RawExpression;
use RoundlyConsulting\Metrics\Types\Partition\PartitionResult;
use stdClass;

trait ComputesPartition
{
    protected ?int $partitionLimit = null;

    protected ?string $otherLabel = null;

    /**
     * @var (Closure(int|string): string)|null
     */
    protected ?Closure $labelResolver = null;

    /**
     * The metric's result, calculated or restored from the result cache.
     */
    public function result(): PartitionResult
    {
        $result = $this->resultOf(PartitionResult::class);

        // Labels are presentation, resolved on every read rather than cached: a closure has
        // no stable identity to key the cache on, so a cached label would outlive a changed
        // labelUsing().
        $labels = $this->resolveLabels($result);

        return $labels === [] ? $result : new PartitionResult($result->partitions(), $labels);
    }

    /**
     * Cap the partition to the top N groups, rolling the remainder into a
     * single "Other" bucket.
     */
    public function limit(int $limit): static
    {
        $this->partitionLimit = $limit;

        return $this;
    }

    /**
     * Override the label used for the rolled-up "Other" bucket.
     */
    public function otherLabel(string $label): static
    {
        $this->otherLabel = $label;

        return $this;
    }

    /**
     * Map raw group keys to display labels, exposed under the result's "labels".
     * Raw keys remain available via the partition keys.
     *
     * @param  Closure(int|string): string  $resolver
     */
    public function labelUsing(Closure $resolver): static
    {
        $this->labelResolver = $resolver;

        return $this;
    }

    /**
     * @return array<array-key, mixed>
     */
    protected function cacheDiscriminators(): array
    {
        // The resolved label, not the override: the default runs through the translator,
        // and the bucket's key is part of the cached result.
        return ['limit' => $this->partitionLimit, 'other' => $this->partitionLimit === null ? null : $this->resolveOtherLabel()];
    }

    /**
     * @param  Builder<covariant Model>  $query
     */
    protected function aggregate(
        Builder $query,
        string $function,
        string $groupBy,
        ?string $valueColumn = null,
        ?string $dateColumn = null
    ): PartitionResult {
        $dateColumn = $dateColumn ?? $query->getModel()->getQualifiedCreatedAtColumn();

        $groups = $this->getResults($query, $function, $groupBy, $dateColumn, $valueColumn);

        if ($this->partitionLimit === null || count($groups) <= $this->partitionLimit) {
            return new PartitionResult(
                results: $this->roundAll(array_map(fn (stdClass $group): float => (float) $group->aggregate, $groups)),
            );
        }

        return new PartitionResult(results: $this->capPartitions($function, $groups));
    }

    /**
     * The top N groups, then everything else rolled into the "Other" bucket with the
     * metric's own aggregate — a sum of sums or counts, the largest maximum, the smallest
     * minimum, or the weighted average of the tail's rows.
     *
     * A real group whose key equals the "Other" label is never one of the N: it joins the
     * bucket, so the bucket cannot overwrite it and no row is dropped.
     *
     * @param  array<array-key, stdClass>  $groups
     * @return array<array-key, float>
     */
    protected function capPartitions(string $function, array $groups): array
    {
        $other = $this->resolveOtherLabel();

        $named = array_filter($groups, fn (int|string $key): bool => (string) $key !== $other, ARRAY_FILTER_USE_KEY);

        $top = array_slice($named, 0, $this->partitionLimit, true);
        $tail = array_diff_key($groups, $top);

        $results = $this->roundAll(array_map(fn (stdClass $group): float => (float) $group->aggregate, $top));
        $results[$other] = round($this->rollUp($function, $tail), $this->roundingPrecision, $this->roundingMode);

        return $results;
    }

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
     * @param  array<array-key, float>  $values
     * @return array<array-key, float>
     */
    protected function roundAll(array $values): array
    {
        return array_map(fn (float $value): float => round($value, $this->roundingPrecision, $this->roundingMode), $values);
    }

    protected function resolveOtherLabel(): string
    {
        if ($this->otherLabel !== null) {
            return $this->otherLabel;
        }

        return (string) __(MetricsConfig::string('metrics.partition.other_label', config('metrics.partition.other_label'), 'Other'));
    }

    /**
     * Display labels for a result's keys. The rolled-up "Other" bucket keeps its own label;
     * every other key — including a real group that happens to share that name, when
     * nothing was rolled up — goes through the resolver.
     *
     * @return array<string, string>
     */
    protected function resolveLabels(PartitionResult $result): array
    {
        if ($this->labelResolver === null) {
            return [];
        }

        $resolver = $this->labelResolver;
        $other = $this->resolveOtherLabel();
        $rolledUp = $this->partitionLimit !== null && count($result->partitions()) > $this->partitionLimit;
        $labels = [];

        foreach (array_keys($result->partitions()) as $key) {
            $labels[(string) $key] = $rolledUp && (string) $key === $other ? $other : (string) $resolver($key);
        }

        return $labels;
    }

    /**
     * One row per group — its key, its aggregate and, for an average, the sum and count it
     * was taken over — largest aggregate first. Without a value column, `count()` counts
     * rows (`count(*)`): counting the group column itself reports 0 for the NULL group.
     *
     * @param  Builder<covariant Model>  $query
     * @return array<array-key, stdClass>
     */
    protected function getResults(Builder $query, string $function, string $groupBy, string $dateColumn, ?string $valueColumn): array
    {
        $grammar = $query->getQuery()->getGrammar();

        $groupBy = $grammar->wrap($groupBy);
        $valueColumn = match (true) {
            $valueColumn !== null && $valueColumn !== '' => $grammar->wrap($valueColumn),
            $function === 'count' => '*',
            default => $groupBy,
        };

        $columns = [
            new RawExpression("{$groupBy} as aggregate_partition"),
            new RawExpression("{$function}({$valueColumn}) as aggregate"),
        ];

        if ($function === 'avg') {
            $columns[] = new RawExpression("sum({$valueColumn}) as aggregate_sum");
            $columns[] = new RawExpression("count({$valueColumn}) as aggregate_count");
        }

        // A copy of the base query with global scopes applied (e.g. soft deletes), so the raw
        // aggregate/grouping SQL runs against the same constraints. Cloned first:
        // applyScopes() returns the builder itself when there are no global scopes, and the
        // SQL below would otherwise pile onto the metric's own query on every calculation.
        $results = (clone $query)->applyScopes()->getQuery()
            ->select($columns)
            ->groupBy('aggregate_partition')
            ->orderByDesc('aggregate');

        if ($this->range !== 'ALL') {
            $results = $results->whereBetween($dateColumn, $this->storageBounds($this->getRange()));
        }

        $groups = [];

        foreach ($results->get() as $row) {
            /** @var stdClass $row */
            $key = $row->aggregate_partition;
            $groups[is_int($key) || is_string($key) ? $key : (string) (is_scalar($key) ? $key : '')] = $row;
        }

        return $groups;
    }
}
