<?php

declare(strict_types=1);

namespace RoundlyConsulting\Metrics\Ranges;

use Carbon\CarbonImmutable;

final class Today extends BaseRange
{
    public function start(): CarbonImmutable
    {
        if ($this->previous) {
            return CarbonImmutable::now()->subDay()->startOfDay();
        }

        return CarbonImmutable::now()->startOfDay();
    }

    public function end(): CarbonImmutable
    {
        if ($this->previous) {
            return CarbonImmutable::now()->subDay()->endOfDay();
        }

        return CarbonImmutable::now()->endOfDay();
    }
}
