<?php

declare(strict_types=1);

namespace RoundlyConsulting\Metrics\Ranges;

use Carbon\CarbonImmutable;

final class WeekToDate extends BaseRange
{
    public function start(): CarbonImmutable
    {
        if ($this->previous) {
            return $this->now()->subWeek()->startOfWeek();
        }

        return $this->now()->startOfWeek();
    }

    public function end(): CarbonImmutable
    {
        if ($this->previous) {
            return $this->now()->subWeek();
        }

        return $this->now();
    }
}
