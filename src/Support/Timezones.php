<?php

declare(strict_types=1);

namespace RoundlyConsulting\Metrics\Support;

use Carbon\CarbonImmutable;
use DateTimeZone;
use RoundlyConsulting\Metrics\Types\Trend\QueryExpressions\QueryExpression;

/**
 * The two clocks a metric works with.
 *
 * - The **storage** clock: the application timezone, which is what Eloquent writes
 *   timestamps in (as a bare `Y-m-d H:i:s` wall clock).
 * - The **reporting** clock: `timezone()` on the metric, else `metrics.timezone`, else the
 *   storage clock. Ranges are resolved and trend buckets are labelled in it.
 *
 * Laravel binds a Carbon as its wall clock and drops the zone, so every range bound is
 * converted to the storage clock before it reaches the query, and a trend's date column is
 * moved onto the reporting clock before it is bucketed.
 *
 * @internal
 */
final class Timezones
{
    public static function storage(): string
    {
        $timezone = config('app.timezone');

        return is_string($timezone) && $timezone !== '' ? $timezone : date_default_timezone_get();
    }

    public static function reporting(?string $override = null): string
    {
        if ($override !== null) {
            return $override !== '' ? $override : self::storage();
        }

        return MetricsConfig::optionalString('metrics.timezone', config('metrics.timezone')) ?? self::storage();
    }

    /**
     * SQL that re-reads a stored wall-clock column on the reporting clock, over the span
     * `[$from, $to]`.
     *
     * The distance between two zones is not constant — either may observe daylight saving
     * — so the span is cut at every transition of either zone and each piece gets its own
     * minute offset, as a `CASE` keyed on the stored wall clock. Every real-world offset is
     * a whole number of minutes, so half- and quarter-hour zones shift exactly.
     */
    public static function reportingColumn(
        QueryExpression $grammar,
        string $column,
        string $storage,
        string $reporting,
        CarbonImmutable $from,
        CarbonImmutable $to,
    ): string {
        $segments = self::segments(new DateTimeZone($storage), new DateTimeZone($reporting), $from, $to);

        if (count($segments) === 1) {
            $minutes = $segments[0][1];

            return $minutes === 0 ? $column : $grammar->addMinutes($column, $minutes);
        }

        $sql = 'case';

        for ($i = 1, $count = count($segments); $i < $count; $i++) {
            $boundary = CarbonImmutable::createFromTimestamp($segments[$i][0], $storage)->format('Y-m-d H:i:s');

            $sql .= " when {$column} < '{$boundary}' then ".$grammar->addMinutes($column, $segments[$i - 1][1]);
        }

        return $sql.' else '.$grammar->addMinutes($column, $segments[$count - 1][1]).' end';
    }

    /**
     * The span cut into pieces of constant offset: each entry is the piece's first instant
     * (a Unix timestamp) and the reporting-minus-storage offset in minutes.
     *
     * @return list<array{int, int}>
     */
    private static function segments(DateTimeZone $storage, DateTimeZone $reporting, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $start = $from->getTimestamp();
        $end = max($start, $to->getTimestamp());

        $instants = [$start];

        foreach ([$storage, $reporting] as $zone) {
            foreach ($zone->getTransitions($start, $end) ?: [] as $transition) {
                if ($transition['ts'] > $start && $transition['ts'] <= $end) {
                    $instants[] = $transition['ts'];
                }
            }
        }

        $instants = array_values(array_unique($instants));
        sort($instants);

        $segments = [];

        foreach ($instants as $instant) {
            $moment = CarbonImmutable::createFromTimestamp($instant);
            $minutes = intdiv($reporting->getOffset($moment) - $storage->getOffset($moment), 60);

            if ($segments !== [] && $segments[array_key_last($segments)][1] === $minutes) {
                continue;
            }

            $segments[] = [$instant, $minutes];
        }

        return $segments;
    }
}
