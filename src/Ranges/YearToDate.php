<?php

declare(strict_types=1);

namespace RoundlyConsulting\Metrics\Ranges;

use Carbon\CarbonImmutable;

final class YearToDate extends BaseRange
{
    public function start(): CarbonImmutable
    {
        if ($this->previous) {
            return $this->now()->subYear()->startOfYear();
        }

        return $this->now()->startOfYear();
    }

    public function end(): CarbonImmutable
    {
        if ($this->previous) {
            // Clamped: on a leap day the same point last year is 02-28, not 03-01.
            return $this->now()->subYearWithoutOverflow();
        }

        return $this->now();
    }
}
