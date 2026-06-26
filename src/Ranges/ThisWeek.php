<?php

declare(strict_types=1);

namespace RoundlyConsulting\Metrics\Ranges;

use Carbon\CarbonImmutable;

final class ThisWeek extends BaseRange
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
            return $this->now()->subWeek()->endOfWeek();
        }

        return $this->now()->endOfWeek();
    }
}
