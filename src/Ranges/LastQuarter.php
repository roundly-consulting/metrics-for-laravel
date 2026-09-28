<?php

declare(strict_types=1);

namespace RoundlyConsulting\Metrics\Ranges;

use Carbon\CarbonImmutable;

/**
 * Stepped back from the start of the current quarter: `subQuarter()` from a month-end day
 * overflows (12-31 minus three months is "09-31", i.e. 10-01 — this quarter again).
 */
final class LastQuarter extends BaseRange
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
        return $this->now()->startOfQuarter()->subQuarters($this->previous ? 2 : 1);
    }
}
