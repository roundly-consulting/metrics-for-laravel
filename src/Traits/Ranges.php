<?php

declare(strict_types=1);

namespace RoundlyConsulting\Metrics\Traits;

use Carbon\CarbonImmutable;
use RoundlyConsulting\Metrics\Enums\Period;
use RoundlyConsulting\Metrics\Exceptions\InvalidRangeException;
use RoundlyConsulting\Metrics\Ranges\Range;
use RoundlyConsulting\Metrics\Support\Timezones;

trait Ranges
{
    protected string $range = 'ALL';

    protected ?string $customRangeStart = null;

    protected ?string $customRangeEnd = null;

    protected ?string $timezoneOverride = null;

    /**
     * The available periods keyed by value with translatable labels.
     *
     * @return array<array-key, string>
     */
    public function ranges(): array
    {
        return Period::toOptions()->all();
    }

    public function range(Period|string $range, ?string $customRangeStart = null, ?string $customRangeEnd = null): self
    {
        $this->range = $range instanceof Period ? $range->value : $range;

        if ($this->range === Period::Custom->value) {
            $this->customRangeStart = $customRangeStart;
            $this->customRangeEnd = $customRangeEnd;
        }

        return $this;
    }

    /**
     * Resolve this metric's ranges — and label its trend buckets — in an explicit
     * timezone, overriding config('metrics.timezone') and the application timezone.
     */
    public function timezone(?string $timezone): self
    {
        $this->timezoneOverride = $timezone;

        return $this;
    }

    protected function getRange(): Range
    {
        $period = Period::tryFrom($this->range);

        if ($period === null) {
            throw InvalidRangeException::for($this->range);
        }

        return $period->toRange($this->customRangeStart, $this->customRangeEnd)
            ->usingTimezone($this->timezoneOverride);
    }

    /**
     * The zone this metric's ranges are resolved and its buckets labelled in.
     */
    protected function reportingTimezone(): string
    {
        return Timezones::reporting($this->timezoneOverride);
    }

    /**
     * A range's bounds on the storage clock, ready to bind. Laravel formats a bound Carbon
     * as its bare wall clock, so a bound left on the reporting clock would be compared with
     * rows stored on another clock and shift the window by the difference.
     *
     * @return list<CarbonImmutable>
     */
    protected function storageBounds(Range $range): array
    {
        $storage = Timezones::storage();

        return [$range->start()->setTimezone($storage), $range->end()->setTimezone($storage)];
    }
}
