<?php

declare(strict_types=1);

namespace RoundlyConsulting\Metrics\Enums;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use RoundlyConsulting\Enums\Helpers;
use RoundlyConsulting\Metrics\Types\Trend\QueryExpressions\QueryExpression;

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
     *
     * This must agree, character for character, with the SQL the active
     * {@see QueryExpression}
     * emits: the aggregate's keys come from SQL and the gap-filled range's keys come from
     * here, and gap-filling looks one up in the other. A disagreement is silent — the
     * bucket simply reports 0 for real data.
     *
     * WEEK uses `o`, the ISO-8601 week-numbering year, NOT `Y`. `W` is already an ISO week
     * number, and pairing an ISO week with a calendar year is wrong by construction at
     * every year boundary: 2023-01-01 is ISO week 52 of 2022, which `Y-W` filed as
     * `2023-52` — a key no engine ever produced, and a year that week does not belong to.
     * `parse()` below has always used `setISODate()`, so this was a mismatch inside PHP
     * itself before any driver was involved.
     */
    public function format(CarbonInterface $datetime): string
    {
        return match ($this) {
            self::Minute => $datetime->format('Y-m-d H:i:00'),
            self::Hour => $datetime->format('Y-m-d H:00'),
            self::Day => $datetime->format('Y-m-d'),
            self::Week => $datetime->format('o-W'),
            self::Month => $datetime->format('Y-m'),
            self::Year => $datetime->format('Y'),
        };
    }

    /**
     * The first instant of the bucket holding `$datetime`, in its own timezone. Weeks are
     * ISO weeks and start on Monday whatever the locale, to agree with the `o-W` key.
     */
    public function startOfBucket(CarbonInterface $datetime): CarbonInterface
    {
        return match ($this) {
            self::Minute => $datetime->startOfMinute(),
            self::Hour => $datetime->startOfHour(),
            self::Day => $datetime->startOfDay(),
            self::Week => $datetime->startOfWeek(CarbonInterface::MONDAY),
            self::Month => $datetime->startOfMonth(),
            self::Year => $datetime->startOfYear(),
        };
    }

    /**
     * Parse a bucket key back into a datetime — the start of the bucket, or its last
     * second when `$end` is set.
     *
     * Every format starts with `!`: without it `createFromFormat()` fills the fields the
     * key leaves out from "now", so on the 29th-31st `2026-02` parsed to March. Weeks end
     * on Sunday whatever the locale, to agree with the ISO `o-W` key.
     */
    public function parse(string $datetime, bool $end = false): CarbonInterface
    {
        $start = match ($this) {
            self::Minute => Carbon::createFromFormat('!Y-m-d H:i:00', $datetime),
            self::Hour => Carbon::createFromFormat('!Y-m-d H:00', $datetime),
            self::Day => Carbon::createFromFormat('!Y-m-d', $datetime),
            self::Week => Carbon::now()
                ->setISODate(
                    (int) str($datetime)->before('-')->toString(),
                    (int) str($datetime)->after('-')->toString(),
                )
                ->startOfDay(),
            self::Month => Carbon::createFromFormat('!Y-m', $datetime),
            self::Year => Carbon::createFromFormat('!Y', $datetime),
        };

        if (! $end) {
            return $start;
        }

        return match ($this) {
            self::Minute => $start->endOfMinute(),
            self::Hour => $start->endOfHour(),
            self::Day => $start->endOfDay(),
            self::Week => $start->endOfWeek(CarbonInterface::SUNDAY),
            self::Month => $start->endOfMonth(),
            self::Year => $start->endOfYear(),
        };
    }
}
