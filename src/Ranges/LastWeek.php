<?php

declare(strict_types=1);

namespace RoundlyConsulting\Metrics\Ranges;

use Carbon\CarbonImmutable;

final class LastWeek extends BaseRange
{
    public function start(): CarbonImmutable
    {
        if ($this->previous) {
            return $this->now()->subWeeks(2)->startOfWeek();
        }

        return $this->now()->subWeek()->startOfWeek();
    }

    public function end(): CarbonImmutable
    {
        if ($this->previous) {
            return $this->now()->subWeeks(2)->endOfWeek();
        }

        return $this->now()->subWeek()->endOfWeek();
    }
}
