<?php

declare(strict_types=1);

namespace RoundlyConsulting\Metrics\Ranges;

use Carbon\CarbonImmutable;

final class LastMonth extends BaseRange
{
    public function start(): CarbonImmutable
    {
        if ($this->previous) {
            return $this->now()->subMonthsWithoutOverflow(2)->startOfMonth();
        }

        return $this->now()->subMonthWithoutOverflow()->startOfMonth();
    }

    public function end(): CarbonImmutable
    {
        if ($this->previous) {
            return $this->now()->subMonthsWithoutOverflow(2)->endOfMonth();
        }

        return $this->now()->subMonthWithoutOverflow()->endOfMonth();
    }
}
