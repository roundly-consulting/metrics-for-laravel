<?php

declare(strict_types=1);

namespace RoundlyConsulting\Metrics\Ranges;

use Carbon\CarbonImmutable;
use RoundlyConsulting\Metrics\Support\Timezones;

/**
 * A range resolved on the reporting clock: the per-metric timezone override, else
 * `metrics.timezone`, else the application timezone. The query converts its bounds to the
 * storage clock before binding them.
 */
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
     * The zone this range is resolved in.
     */
    protected function timezoneName(): string
    {
        return Timezones::reporting($this->timezone);
    }

    /**
     * The current moment on the reporting clock.
     */
    protected function now(): CarbonImmutable
    {
        return CarbonImmutable::now($this->timezoneName());
    }
}
