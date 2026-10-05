<?php

declare(strict_types=1);

namespace RoundlyConsulting\Metrics\Ranges;

use Carbon\CarbonImmutable;

/**
 * An explicit window. The bounds are wall-clock strings read on the reporting clock. An end
 * with no time — a bare date such as `2026-01-31`, what a date picker sends — means the
 * whole of that day, so it ends at 23:59:59; a bound with a time is taken as given.
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
            return $this->onReportingClock($this->wallClock($this->parse($this->start))->subSeconds($this->lengthInSeconds() + 1));
        }

        return $this->parse($this->start);
    }

    public function end(): CarbonImmutable
    {
        if ($this->previous) {
            return $this->onReportingClock($this->wallClock($this->parse($this->start))->subSecond());
        }

        return $this->parseEnd();
    }

    private function lengthInSeconds(): int
    {
        return $this->wallClock($this->parseEnd())->getTimestamp() - $this->wallClock($this->parse($this->start))->getTimestamp();
    }

    private function parse(?string $datetime): CarbonImmutable
    {
        return CarbonImmutable::parse($datetime, $this->timezoneName())->setTimezone($this->timezoneName());
    }

    private function parseEnd(): CarbonImmutable
    {
        $end = $this->parse($this->end);

        return self::isDateOnly($this->end) ? $end->endOfDay() : $end;
    }

    /**
     * Whether a bound names a calendar date and no time of day. Relative phrases (`now`,
     * `first day of next month`) carry the current time, so they are taken as given.
     */
    private static function isDateOnly(?string $datetime): bool
    {
        if ($datetime === null) {
            return false;
        }

        $parsed = date_parse($datetime);

        return $parsed['error_count'] === 0
            && $parsed['hour'] === false
            && ! isset($parsed['relative'])
            && $parsed['year'] !== false && $parsed['month'] !== false && $parsed['day'] !== false;
    }

    /**
     * The moment's reporting-clock wall time, re-read as UTC so arithmetic on it never
     * crosses a daylight-saving change.
     */
    private function wallClock(CarbonImmutable $moment): CarbonImmutable
    {
        return CarbonImmutable::parse($moment->format('Y-m-d H:i:s'), 'UTC');
    }

    private function onReportingClock(CarbonImmutable $wallClock): CarbonImmutable
    {
        return CarbonImmutable::parse($wallClock->format('Y-m-d H:i:s'), $this->timezoneName());
    }
}
