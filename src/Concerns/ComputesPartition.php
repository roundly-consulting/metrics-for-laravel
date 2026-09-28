<?php

declare(strict_types=1);

namespace RoundlyConsulting\Metrics\Concerns;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Metrics\Support\RawExpression;
use RoundlyConsulting\Metrics\Types\Partition\PartitionResult;

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
        return $this->resultOf(PartitionResult::class);
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
        return ['limit' => $this->partitionLimit, 'other' => $this->otherLabel];
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

        $results = $this->capPartitions(
            $this->getResults($query, $function, $groupBy, $dateColumn, $valueColumn),
        );

        return new PartitionResult(
            results: $results,
            labels: $this->resolveLabels($results),
        );
    }

    /**
     * @param  array<array-key, float>  $results
     * @return array<array-key, float>
     */
    protected function capPartitions(array $results): array
    {
        if ($this->partitionLimit === null || count($results) <= $this->partitionLimit) {
            return $results;
        }

        $top = array_slice($results, 0, $this->partitionLimit, true);
        $tail = array_slice($results, $this->partitionLimit, null, true);

        $top[$this->resolveOtherLabel()] = round(
            array_sum($tail),
            $this->roundingPrecision,
            $this->roundingMode,
        );

        return $top;
    }

    protected function resolveOtherLabel(): string
    {
        if ($this->otherLabel !== null) {
            return $this->otherLabel;
        }

        $configured = config('metrics.partition.other_label');

        return (string) __(is_string($configured) ? $configured : 'Other');
    }

    /**
     * @param  array<array-key, float>  $results
     * @return array<string, string>
     */
    protected function resolveLabels(array $results): array
    {
        if ($this->labelResolver === null) {
            return [];
        }

        $resolver = $this->labelResolver;
        $other = $this->resolveOtherLabel();
        $labels = [];

        foreach (array_keys($results) as $key) {
            $labels[(string) $key] = (string) $key === $other ? $other : (string) $resolver($key);
        }

        return $labels;
    }

    /**
     * @param  Builder<covariant Model>  $query
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
