<?php

declare(strict_types=1);

namespace RoundlyConsulting\Metrics\Concerns;

use RoundlyConsulting\Metrics\Traits\PercentageCalculator;
use RoundlyConsulting\Metrics\Types\Progress\ProgressResult;
use RoundlyConsulting\Metrics\Types\Value\ValueResult;

trait CalculatesProgress
{
    use PercentageCalculator;

    protected float $target = 100.0;

    protected bool $avoid = false;

    public function target(float $target): self
    {
        $this->target = $target;

        return $this;
    }

    public function shouldBeAvoided(bool $avoid = true): self
    {
        $this->avoid = $avoid;

        return $this;
    }

    /**
     * @return array<array-key, mixed>
     */
    protected function cacheDiscriminators(): array
    {
        return [
            'target' => $this->target,
            'avoid' => $this->avoid,
            'change' => $this->withChange,
            'compare' => $this->compareTo,
            'compare_start' => $this->compareToStart,
            'compare_end' => $this->compareToEnd,
        ];
    }

    /**
     * The metric's result, calculated or restored from the result cache.
     */
    public function result(): ProgressResult
    {
        return $this->resultOf(ProgressResult::class);
    }

    protected function resolveResult(ValueResult $result): ProgressResult
    {
        $value = $result->value();
        $previous = $result->previous();

        return new ProgressResult(
            value: $value,
            target: $this->target,
            progress: $this->calculatePercentage(
                current: $value,
                max: $this->target,
                roundingPrecision: $this->roundingPrecision,
                roundingMode: $this->roundingMode,
            ),
            avoid: $this->avoid,
            previous: $previous,
            previousProgress: $previous === null ? null : $this->calculatePercentage(
                current: $previous,
                max: $this->target,
                roundingPrecision: $this->roundingPrecision,
                roundingMode: $this->roundingMode,
            ),
            change: $result->change(),
        );
    }
}
