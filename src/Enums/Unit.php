<?php

declare(strict_types=1);

namespace RoundlyConsulting\Metrics\Enums;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use RoundlyConsulting\Enums\Helpers;

enum Unit: string
{
    use Helpers;

    case Minute = 'MINUTE';
    case Hour = 'HOUR';
    case Day = 'DAY';
    case Week = 'WEEK';
    case Month = 'MONTH';
    case Year = 'YEAR';

    /**
     * The translatable label for the bucket unit.
     *
     * Overrides the trait's value-based headline because the backed values are
     * uppercase tokens (MINUTE, HOUR, …) that would headline to "M I N U T E".
     */
    public function readable(): string
    {
        return (string) __(Str::headline($this->name));
    }

    /**
     * Format a datetime into the bucket key for this unit (PHP side).
     */
    public function format(CarbonInterface $datetime): string
    {
        return match ($this) {
            self::Minute => $datetime->format('Y-m-d H:i:00'),
            self::Hour => $datetime->format('Y-m-d H:00'),
            self::Day => $datetime->format('Y-m-d'),
            self::Week => $datetime->format('Y-W'),
            self::Month => $datetime->format('Y-m'),
            self::Year => $datetime->format('Y'),
        };
    }

    /**
     * Parse a bucket key back into a datetime, optionally at the end of the bucket.
     */
    public function parse(string $datetime, bool $end = false): CarbonInterface
    {
        return match ($this) {
            self::Minute => Carbon::createFromFormat('Y-m-d H:i:00', $datetime)
                ->when($end, fn (Carbon $datetime) => $datetime->endOfMinute()),
            self::Hour => Carbon::createFromFormat('Y-m-d H:00', $datetime)
                ->when($end, fn (Carbon $datetime) => $datetime->endOfHour()),
            self::Day => Carbon::createFromFormat('Y-m-d', $datetime)
                ->when($end, fn (Carbon $datetime) => $datetime->endOfDay()),
            self::Week => Carbon::now()
                ->setISODate(
                    (int) str($datetime)->before('-')->toString(),
                    (int) str($datetime)->after('-')->toString(),
                )
                ->when($end, fn (Carbon $datetime) => $datetime->endOfWeek()),
            self::Month => Carbon::createFromFormat('Y-m', $datetime)
                ->when($end, fn (Carbon $datetime) => $datetime->endOfMonth()),
            self::Year => Carbon::createFromFormat('Y', $datetime)
                ->when($end, fn (Carbon $datetime) => $datetime->endOfYear()),
        };
    }
}
