<?php

declare(strict_types=1);

namespace RoundlyConsulting\Metrics\Traits;

use RoundlyConsulting\Metrics\Enums\Period;
use RoundlyConsulting\Metrics\Exceptions\InvalidRangeException;
use RoundlyConsulting\Metrics\Ranges\Range;

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
        return Period::options();
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
     * Resolve this metric's ranges in an explicit timezone, overriding
     * config('metrics.timezone') and the application timezone.
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
}
