<?php

declare(strict_types=1);

namespace RoundlyConsulting\Metrics\Ranges;

use Carbon\CarbonImmutable;

final class ThisQuarter extends BaseRange
{
    public function start(): CarbonImmutable
    {
        if ($this->previous) {
            return $this->now()->subQuarter()->startOfQuarter();
        }

        return $this->now()->startOfQuarter();
    }

    public function end(): CarbonImmutable
    {
        if ($this->previous) {
            return $this->now()->subQuarter()->endOfQuarter();
        }

        return $this->now()->endOfQuarter();
    }
}
