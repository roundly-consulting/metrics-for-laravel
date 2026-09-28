<?php

declare(strict_types=1);

namespace RoundlyConsulting\Metrics\Types\Partition;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Metrics\Concerns\ComputesPartition;
use RoundlyConsulting\Metrics\Exceptions\IncompleteMetricException;
use RoundlyConsulting\Metrics\Metric;
use RoundlyConsulting\Metrics\Types\Result;

final class PendingPartition extends Metric
{
    use ComputesPartition;

    /**
     * @var Builder<covariant Model>|null
     */
    private ?Builder $query = null;

    private string $aggregateFunction = 'count';

    private string $groupBy = '';

    private ?string $valueColumn = null;

    private ?string $dateColumn = null;

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     */
    public function count(Builder $query, string $groupBy, ?string $valueColumn = null, ?string $dateColumn = null): static
    {
        return $this->withAggregate('count', $query, $groupBy, $valueColumn, $dateColumn);
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     */
    public function sum(Builder $query, string $groupBy, ?string $valueColumn = null, ?string $dateColumn = null): static
    {
        return $this->withAggregate('sum', $query, $groupBy, $valueColumn, $dateColumn);
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     */
    public function average(Builder $query, string $groupBy, ?string $valueColumn = null, ?string $dateColumn = null): static
    {
        return $this->withAggregate('avg', $query, $groupBy, $valueColumn, $dateColumn);
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     */
    public function max(Builder $query, string $groupBy, ?string $valueColumn = null, ?string $dateColumn = null): static
    {
        return $this->withAggregate('max', $query, $groupBy, $valueColumn, $dateColumn);
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     */
    public function min(Builder $query, string $groupBy, ?string $valueColumn = null, ?string $dateColumn = null): static
    {
        return $this->withAggregate('min', $query, $groupBy, $valueColumn, $dateColumn);
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     */
    private function withAggregate(string $function, Builder $query, string $groupBy, ?string $valueColumn, ?string $dateColumn): static
    {
        $this->aggregateFunction = $function;
        $this->query = $query;
        $this->groupBy = $groupBy;
        $this->valueColumn = $valueColumn;
        $this->dateColumn = $dateColumn;

        return $this;
    }

    /**
     * @return array<array-key, mixed>
     */
    protected function cacheIdentity(): array
    {
        return [$this->aggregateFunction, $this->groupBy, $this->valueColumn, $this->dateColumn, $this->queryIdentity($this->query)];
    }

    protected function calculate(): Result
    {
        if ($this->query === null) {
            throw IncompleteMetricException::missingQuery();
        }

        return $this->aggregate($this->query, $this->aggregateFunction, $this->groupBy, $this->valueColumn, $this->dateColumn);
    }
}
