<?php

declare(strict_types=1);

namespace RoundlyConsulting\Metrics\Traits;

use Carbon\CarbonInterface;
use RoundlyConsulting\Metrics\Enums\Unit as UnitEnum;

trait Unit
{
    protected UnitEnum $unit = UnitEnum::Day;

    public function unit(UnitEnum|string $unit): static
    {
        $resolved = $unit instanceof UnitEnum ? $unit : UnitEnum::tryFrom($unit);

        if ($resolved !== null) {
            $this->unit = $resolved;
        }

        return $this;
    }

    public function perMinute(): static
    {
        return $this->unit(UnitEnum::Minute);
    }

    public function hourly(): static
    {
        return $this->unit(UnitEnum::Hour);
    }

    public function daily(): static
    {
        return $this->unit(UnitEnum::Day);
    }

    public function weekly(): static
    {
        return $this->unit(UnitEnum::Week);
    }

    public function monthly(): static
    {
        return $this->unit(UnitEnum::Month);
    }

    public function yearly(): static
    {
        return $this->unit(UnitEnum::Year);
    }

    protected function formatDatetimeToUnit(CarbonInterface $datetime): string
    {
        return $this->unit->format($datetime);
    }

    protected function fromUnitFormatToDatetime(string $datetime, bool $end = false): CarbonInterface
    {
        return $this->unit->parse($datetime, $end);
    }
}
