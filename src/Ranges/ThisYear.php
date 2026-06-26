<?php

declare(strict_types=1);

namespace RoundlyConsulting\Metrics\Ranges;

use Carbon\CarbonImmutable;

final class ThisYear extends BaseRange
{
    public function start(): CarbonImmutable
    {
        if ($this->previous) {
            return $this->now()->subYear()->startOfYear();
        }

        return $this->now()->startOfYear();
    }

    public function end(): CarbonImmutable
    {
        if ($this->previous) {
            return $this->now()->subYear()->endOfYear();
        }

        return $this->now()->endOfYear();
    }
}
