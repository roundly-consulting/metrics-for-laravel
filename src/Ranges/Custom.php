<?php

declare(strict_types=1);

namespace RoundlyConsulting\Metrics\Ranges;

use Carbon\CarbonImmutable;

/**
 * An explicit window. The bounds are wall-clock strings read on the reporting clock.
 */
final class Custom extends BaseRange
{
    public function __construct(protected ?string $start, protected ?string $end) {}

    public function start(): CarbonImmutable
    {
        if ($this->previous) {
            return $this->parse($this->start)->subDays(
                $this->daysBetween(),
            );
        }

        return $this->parse($this->start);
    }

    public function end(): CarbonImmutable
    {
        if ($this->previous) {
            return $this->parse($this->end)->subDays(
                $this->daysBetween(),
            );
        }

        return $this->parse($this->end);
    }

    protected function daysBetween(): int
    {
        $start = $this->parse($this->start);
        $end = $this->parse($this->end);

        return (int) $start->diffInDays($end);
    }

    private function parse(?string $datetime): CarbonImmutable
    {
        return CarbonImmutable::parse($datetime, $this->timezoneName());
    }
}
