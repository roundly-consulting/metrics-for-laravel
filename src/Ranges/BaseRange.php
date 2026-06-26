<?php

declare(strict_types=1);

namespace RoundlyConsulting\Metrics\Ranges;

use Carbon\CarbonImmutable;

abstract class BaseRange implements Range
{
    protected bool $previous = false;

    public function previous(): Range
    {
        $instance = clone $this;
        $instance->previous = true;

        return $instance;
    }

    /**
     * The current moment in the configured reporting timezone
     * (falls back to the application timezone).
     */
    protected function now(): CarbonImmutable
    {
        $timezone = config('metrics.timezone');

        return CarbonImmutable::now(is_string($timezone) ? $timezone : null);
    }
}
