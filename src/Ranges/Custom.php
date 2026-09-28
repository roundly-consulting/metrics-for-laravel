<?php

declare(strict_types=1);

namespace RoundlyConsulting\Metrics\Ranges;

use Carbon\CarbonImmutable;

/**
 * An explicit window. The bounds are wall-clock strings read on the reporting clock.
 *
 * Its previous period is the window of exactly the same length that ends one second before
 * this one starts, measured on the wall clock so a daylight-saving change inside either
 * window does not move it by an hour.
 */
final class Custom extends BaseRange
{
    public function __construct(protected ?string $start, protected ?string $end) {}

    public function start(): CarbonImmutable
    {
        if ($this->previous) {
            return $this->onReportingClock($this->wallClock($this->start)->subSeconds($this->lengthInSeconds() + 1));
        }

        return $this->parse($this->start);
    }

    public function end(): CarbonImmutable
    {
        if ($this->previous) {
            return $this->onReportingClock($this->wallClock($this->start)->subSecond());
        }

        return $this->parse($this->end);
    }

    private function lengthInSeconds(): int
    {
        return $this->wallClock($this->end)->getTimestamp() - $this->wallClock($this->start)->getTimestamp();
    }

    private function parse(?string $datetime): CarbonImmutable
    {
        return CarbonImmutable::parse($datetime, $this->timezoneName())->setTimezone($this->timezoneName());
    }

    /**
     * The bound's reporting-clock wall time, re-read as UTC so arithmetic on it never
     * crosses a daylight-saving change.
     */
    private function wallClock(?string $datetime): CarbonImmutable
    {
        return CarbonImmutable::parse($this->parse($datetime)->format('Y-m-d H:i:s'), 'UTC');
    }

    private function onReportingClock(CarbonImmutable $wallClock): CarbonImmutable
    {
        return CarbonImmutable::parse($wallClock->format('Y-m-d H:i:s'), $this->timezoneName());
    }
}
