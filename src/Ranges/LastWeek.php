<?php

declare(strict_types=1);

namespace RoundlyConsulting\Metrics\Ranges;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * An ISO week — Monday to Sunday — whatever the locale, matching the WEEK trend buckets.
 */
final class LastWeek extends BaseRange
{
    public function start(): CarbonImmutable
    {
        if ($this->previous) {
            return $this->now()->subWeeks(2)->startOfWeek(CarbonInterface::MONDAY);
        }

        return $this->now()->subWeek()->startOfWeek(CarbonInterface::MONDAY);
    }

    public function end(): CarbonImmutable
    {
        if ($this->previous) {
            return $this->now()->subWeeks(2)->endOfWeek(CarbonInterface::SUNDAY);
        }

        return $this->now()->subWeek()->endOfWeek(CarbonInterface::SUNDAY);
    }
}
