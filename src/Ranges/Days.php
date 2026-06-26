<?php

declare(strict_types=1);

namespace RoundlyConsulting\Metrics\Ranges;

use Carbon\CarbonImmutable;

final class Days extends BaseRange
{
    public function __construct(protected int $days) {}

    public function start(): CarbonImmutable
    {
        if ($this->previous) {
            return $this->now()->subDays($this->days * 2);
        }

        return $this->now()->subDays($this->days);
    }

    public function end(): CarbonImmutable
    {
        if ($this->previous) {
            return $this->now()->subDays($this->days)->subSecond();
        }

        return $this->now();
    }
}
