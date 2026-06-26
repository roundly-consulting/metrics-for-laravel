<?php

declare(strict_types=1);

namespace RoundlyConsulting\Metrics\Ranges;

use Carbon\CarbonImmutable;

abstract class BaseRange implements Range
{
    protected bool $previous = false;

    protected ?string $timezone = null;

    public function previous(): Range
    {
        $instance = clone $this;
        $instance->previous = true;

        return $instance;
    }

    /**
     * Resolve this range in an explicit timezone, overriding the configured one.
     */
    public function usingTimezone(?string $timezone): Range
    {
        $instance = clone $this;
        $instance->timezone = $timezone;

        return $instance;
    }

    /**
     * The current moment in the per-metric timezone override, falling back to
     * the configured reporting timezone and finally the application timezone.
     */
    protected function now(): CarbonImmutable
    {
        $timezone = $this->timezone ?? config('metrics.timezone');

        return CarbonImmutable::now(is_string($timezone) ? $timezone : null);
    }
}
