<?php

declare(strict_types=1);

namespace RoundlyConsulting\Metrics\Ranges;

use Carbon\CarbonImmutable;

final class Custom extends BaseRange
{
    public function __construct(protected ?string $start, protected ?string $end) {}

    public function start(): CarbonImmutable
    {
        if ($this->previous) {
            return CarbonImmutable::parse($this->start)->subDays(
                $this->daysBetween(),
            );
        }

        return CarbonImmutable::parse($this->start);
    }

    public function end(): CarbonImmutable
    {
        if ($this->previous) {
            return CarbonImmutable::parse($this->end)->subDays(
                $this->daysBetween(),
            );
        }

        return CarbonImmutable::parse($this->end);
    }

    protected function daysBetween(): int
    {
        $start = CarbonImmutable::parse($this->start);
        $end = CarbonImmutable::parse($this->end);

        return (int) $start->diffInDays($end);
    }
}
