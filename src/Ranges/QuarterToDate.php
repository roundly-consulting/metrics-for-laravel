<?php

declare(strict_types=1);

namespace RoundlyConsulting\Metrics\Ranges;

use Carbon\CarbonImmutable;

/**
 * Its previous period runs from the start of the last quarter to the same point in it —
 * clamped to that quarter's month end (05-31 compares against 02-28), never overflowing.
 */
final class QuarterToDate extends BaseRange
{
    public function start(): CarbonImmutable
    {
        if ($this->previous) {
            return $this->now()->startOfQuarter()->subQuarter();
        }

        return $this->now()->startOfQuarter();
    }

    public function end(): CarbonImmutable
    {
        if ($this->previous) {
            return $this->now()->subQuarterWithoutOverflow();
        }

        return $this->now();
    }
}
