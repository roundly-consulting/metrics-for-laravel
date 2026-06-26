<?php

declare(strict_types=1);

namespace RoundlyConsulting\Metrics\Ranges;

use Carbon\CarbonImmutable;

final class LastYear extends BaseRange
{
    public function start(): CarbonImmutable
    {
        if ($this->previous) {
            return $this->now()->subYears(2)->startOfYear();
        }

        return $this->now()->subYear()->startOfYear();
    }

    public function end(): CarbonImmutable
    {
        if ($this->previous) {
            return $this->now()->subYears(2)->endOfYear();
        }

        return $this->now()->subYear()->endOfYear();
    }
}
