<?php

declare(strict_types=1);

namespace RoundlyConsulting\Metrics\Metrics\Progress;

use RoundlyConsulting\Metrics\Metrics\Result;
use RoundlyConsulting\Metrics\Metrics\Value\Value;
use RoundlyConsulting\Metrics\Metrics\Value\ValueResult;

abstract class Progress extends Value
{
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

    protected function resolveResult(ValueResult $result): Result
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
        );
    }
}
