<?php

declare(strict_types=1);

namespace RoundlyConsulting\Metrics\Ranges;

use Carbon\CarbonImmutable;

final class MonthToDate extends BaseRange
{
    public function start(): CarbonImmutable
    {
        if ($this->previous) {
            return $this->now()->subMonthWithoutOverflow()->startOfMonth();
        }

        return $this->now()->startOfMonth();
    }

    public function end(): CarbonImmutable
    {
        if ($this->previous) {
            return $this->now()->subMonthWithoutOverflow();
        }

        return $this->now();
    }
}
