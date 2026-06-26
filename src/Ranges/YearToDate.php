<?php

declare(strict_types=1);

namespace RoundlyConsulting\Metrics\Ranges;

use Carbon\CarbonImmutable;

final class YearToDate extends BaseRange
{
    public function start(): CarbonImmutable
    {
        if ($this->previous) {
            return CarbonImmutable::now()->subYear()->startOfYear();
        }

        return CarbonImmutable::now()->startOfYear();
    }

    public function end(): CarbonImmutable
    {
        if ($this->previous) {
            return CarbonImmutable::now()->subYear();
        }

        return CarbonImmutable::now();
    }
}
