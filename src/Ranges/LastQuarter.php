<?php

declare(strict_types=1);

namespace RoundlyConsulting\Metrics\Ranges;

use Carbon\CarbonImmutable;

final class LastQuarter extends BaseRange
{
    public function start(): CarbonImmutable
    {
        if ($this->previous) {
            return $this->now()->subQuarters(2)->startOfQuarter();
        }

        return $this->now()->subQuarter()->startOfQuarter();
    }

    public function end(): CarbonImmutable
    {
        if ($this->previous) {
            return $this->now()->subQuarters(2)->endOfQuarter();
        }

        return $this->now()->subQuarter()->endOfQuarter();
    }
}
