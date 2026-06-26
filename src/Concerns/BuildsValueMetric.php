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
     * @var Builder<Model>|null
     */
    private ?Builder $query = null;

    private string $aggregateFunction = 'count';

    private ?string $aggregateColumn = null;

    private ?string $aggregateDateColumn = null;

    /**
     * @param  Builder<Model>  $query
     */
    public function count(Builder $query, ?string $column = null, ?string $dateColumn = null): static
    {
        return $this->withAggregate('count', $query, $column, $dateColumn);
    }

    /**
     * @param  Builder<Model>  $query
     */
    public function sum(Builder $query, ?string $column = null, ?string $dateColumn = null): static
    {
        return $this->withAggregate('sum', $query, $column, $dateColumn);
    }

    /**
     * @param  Builder<Model>  $query
     */
    public function average(Builder $query, ?string $column = null, ?string $dateColumn = null): static
    {
        return $this->withAggregate('avg', $query, $column, $dateColumn);
    }

    /**
     * @param  Builder<Model>  $query
     */
    public function max(Builder $query, ?string $column = null, ?string $dateColumn = null): static
    {
        return $this->withAggregate('max', $query, $column, $dateColumn);
    }

    /**
     * @param  Builder<Model>  $query
     */
    public function min(Builder $query, ?string $column = null, ?string $dateColumn = null): static
    {
        return $this->withAggregate('min', $query, $column, $dateColumn);
    }

    /**
     * @param  Builder<Model>  $query
     */
    private function withAggregate(string $function, Builder $query, ?string $column, ?string $dateColumn): static
    {
        $this->aggregateFunction = $function;
        $this->query = $query;
        $this->aggregateColumn = $column;
        $this->aggregateDateColumn = $dateColumn;

        return $this;
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
