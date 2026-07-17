<?php

declare(strict_types=1);

namespace RoundlyConsulting\Metrics\Types\Trend\QueryExpressions;

use RoundlyConsulting\Metrics\Exceptions\InvalidUnitException;

final class Sqlite implements QueryExpression
{
    public function toSql(string $unit, string $column): string
    {
        return match ($unit) {
            'YEAR' => "strftime('%Y', datetime({$column}))",
            'MONTH' => "strftime('%Y-%m', datetime({$column}))",
            'WEEK' => self::isoWeek($column),
            'DAY' => "strftime('%Y-%m-%d', datetime({$column}))",
            'HOUR' => "strftime('%Y-%m-%d %H:00', datetime({$column}))",
            'MINUTE' => "strftime('%Y-%m-%d %H:%M:00', datetime({$column}))",
            default => throw InvalidUnitException::for($unit),
        };
    }

    /**
     * An ISO-8601 `IYYY-IW` key, computed — because SQLite has no ISO week.
     *
     * `strftime('%W')` is NOT an ISO week: it is a plain week-of-year counted from the
     * first Monday, with no year correction, so it yields `00` for the days before that
     * Monday and pairs the result with the calendar year. 2023-01-01 came out as `2023-00`
     * where Postgres said `2022-52` and the PHP side said `2023-52` — three answers for one
     * instant, and a gap-filled bucket that silently read 0.
     *
     * The standard construction, and the one Postgres' `IYYY-IW` implements internally:
     * **the Thursday of a week decides that week's ISO year**, since an ISO week belongs to
     * whichever year holds the majority of its days. `date(col, '-3 days', 'weekday 4')`
     * resolves to that Thursday for any input day — SQLite's `weekday 4` advances to the
     * next Thursday (staying put if already Thursday), so stepping back 3 days first pins
     * it to the current ISO week for all seven weekdays.
     *
     * From that Thursday: the ISO year is simply its calendar year, and the ISO week is its
     * day-of-year divided into 7-day blocks — `(dayOfYear - 1) / 7 + 1`, integer division.
     * `printf('%02d')` zero-pads to match `IW` and PHP's `W`.
     *
     * Verified identical to PHP `o-W` and Postgres `IYYY-IW` across every boundary shape:
     * a year starting mid-week (2023-01-01 → 2022-52), a 53-week year (2020-12-31 →
     * 2020-53), a December week owned by the next year (2024-12-30 → 2025-01), and an
     * ordinary mid-year week (2023-03-08 → 2023-10).
     */
    private static function isoWeek(string $column): string
    {
        $thursday = "date({$column}, '-3 days', 'weekday 4')";

        return "strftime('%Y', {$thursday}) || '-' || "
            ."printf('%02d', (strftime('%j', {$thursday}) - 1) / 7 + 1)";
    }
}
