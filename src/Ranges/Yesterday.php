<?php

declare(strict_types=1);

namespace RoundlyConsulting\Metrics\Ranges;

use Carbon\CarbonImmutable;

final class Yesterday extends BaseRange
{
    public function start(): CarbonImmutable
    {
        if ($this->previous) {
            return CarbonImmutable::now()->subDays(2)->startOfDay();
        }

        return CarbonImmutable::now()->subDay()->startOfDay();
    }

    public function end(): CarbonImmutable
    {
        if ($this->previous) {
            return CarbonImmutable::now()->subDays(2)->endOfDay();
        }

        return CarbonImmutable::now()->subDay()->endOfDay();
    }
}
