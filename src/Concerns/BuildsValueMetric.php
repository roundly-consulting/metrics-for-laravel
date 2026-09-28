<?php

declare(strict_types=1);

namespace RoundlyConsulting\Metrics\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Metrics\Exceptions\IncompleteMetricException;
use RoundlyConsulting\Metrics\Types\Result;

trait BuildsValueMetric
{
    /**
     * @var Builder<covariant Model>|null
     */
    private ?Builder $query = null;

    private string $aggregateFunction = 'count';

    private ?string $aggregateColumn = null;

    private ?string $aggregateDateColumn = null;

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     */
    public function count(Builder $query, ?string $column = null, ?string $dateColumn = null): static
    {
        return $this->withAggregate('count', $query, $column, $dateColumn);
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     */
    public function sum(Builder $query, ?string $column = null, ?string $dateColumn = null): static
    {
        return $this->withAggregate('sum', $query, $column, $dateColumn);
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     */
    public function average(Builder $query, ?string $column = null, ?string $dateColumn = null): static
    {
        return $this->withAggregate('avg', $query, $column, $dateColumn);
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     */
    public function max(Builder $query, ?string $column = null, ?string $dateColumn = null): static
    {
        return $this->withAggregate('max', $query, $column, $dateColumn);
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     */
    public function min(Builder $query, ?string $column = null, ?string $dateColumn = null): static
    {
        return $this->withAggregate('min', $query, $column, $dateColumn);
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     */
    private function withAggregate(string $function, Builder $query, ?string $column, ?string $dateColumn): static
    {
        $this->aggregateFunction = $function;
        $this->query = $query;
        $this->aggregateColumn = $column;
        $this->aggregateDateColumn = $dateColumn;

        return $this;
    }

    /**
     * @return array<array-key, mixed>
     */
    protected function cacheIdentity(): array
    {
        return [$this->aggregateFunction, $this->aggregateColumn, $this->aggregateDateColumn, $this->queryIdentity($this->query)];
    }

    protected function calculate(): Result
    {
        if ($this->query === null) {
            throw IncompleteMetricException::missingQuery();
        }

        return $this->resolveResult(
            $this->aggregate($this->query, $this->aggregateFunction, $this->aggregateColumn, $this->aggregateDateColumn),
        );
    }
}
