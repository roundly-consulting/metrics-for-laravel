<?php

declare(strict_types=1);

namespace RoundlyConsulting\Metrics\Ranges;

use Carbon\CarbonImmutable;

/**
 * Its previous period is stepped back from the start of the quarter, never from today: a
 * month-end day overflows `subQuarter()` into the current quarter.
 */
final class ThisQuarter extends BaseRange
{
    public function start(): CarbonImmutable
    {
        return $this->quarter()->startOfQuarter();
    }

    public function end(): CarbonImmutable
    {
        return $this->quarter()->endOfQuarter();
    }

    private function quarter(): CarbonImmutable
    {
        $quarter = $this->now()->startOfQuarter();

        return $this->previous ? $quarter->subQuarter() : $quarter;
    }
}
