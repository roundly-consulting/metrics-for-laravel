<?php

declare(strict_types=1);

namespace RoundlyConsulting\Metrics\Traits;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

trait Unit
{
    protected string $unit = 'DAY';

    public function unit(string $unit): self
    {
        if (in_array($unit, [
            'MINUTE',
            'HOUR',
            'DAY',
            'WEEK',
            'MONTH',
            'YEAR',
        ])) {
            $this->unit = $unit;
        }

        return $this;
    }

    public function perMinute(): self
    {
        return $this->unit('MINUTE');
    }

    public function hourly(): self
    {
        return $this->unit('HOUR');
    }

    public function daily(): self
    {
        return $this->unit('DAY');
    }

    public function weekly(): self
    {
        return $this->unit('WEEK');
    }

    public function monthly(): self
    {
        return $this->unit('MONTH');
    }

    public function yearly(): self
    {
        return $this->unit('YEAR');
    }

    protected function formatDatetimeToUnit(CarbonInterface $datetime): string
    {
        return match ($this->unit) {
            'MINUTE' => $datetime->format('Y-m-d H:i:00'),
            'HOUR' => $datetime->format('Y-m-d H:00'),
            'DAY' => $datetime->format('Y-m-d'),
            'WEEK' => $datetime->format('Y-W'),
            'MONTH' => $datetime->format('Y-m'),
            'YEAR' => $datetime->format('Y'),
            default => '',
        };
    }

    protected function fromUnitFormatToDatetime(string $datetime, bool $end = false): CarbonInterface
    {
        return match ($this->unit) {
            'MINUTE' => Carbon::createFromFormat('Y-m-d H:i:00', $datetime)
                ->when($end, fn (Carbon $datetime) => $datetime->endOfMinute()),
            'HOUR' => Carbon::createFromFormat('Y-m-d H:00', $datetime)
                ->when($end, fn (Carbon $datetime) => $datetime->endOfHour()),
            'DAY' => Carbon::createFromFormat('Y-m-d', $datetime)
                ->when($end, fn (Carbon $datetime) => $datetime->endOfDay()),
            'WEEK' => Carbon::now()
                ->setISODate(
                    (int) str($datetime)->before('-')->toString(),
                    (int) str($datetime)->after('-')->toString(),
                )
                ->when($end, fn (Carbon $datetime) => $datetime->endOfWeek()),
            'MONTH' => Carbon::createFromFormat('Y-m', $datetime)
                ->when($end, fn (Carbon $datetime) => $datetime->endOfMonth()),
            'YEAR' => Carbon::createFromFormat('Y', $datetime)
                ->when($end, fn (Carbon $datetime) => $datetime->endOfYear()),
            default => Carbon::parse($datetime),
        };
    }
}
