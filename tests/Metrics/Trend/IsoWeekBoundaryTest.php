<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use RoundlyConsulting\Metrics\Enums\Unit;
use RoundlyConsulting\Metrics\Tests\Metrics\Trend\Users;
use RoundlyConsulting\Testing\Database\DriverMatrix;

/**
 * The WEEK bucket at ISO year boundaries — the one place the PHP side and the SQL side
 * used to disagree, on every driver, in three different ways.
 *
 * A trend computes each bucket key twice: once in SQL (the driver grammar, which produces
 * the aggregate's keys) and once in PHP ({@see Unit::format()}, which produces the
 * gap-filled range). Gap-filling then looks the SQL key up in the PHP-keyed range. If the
 * two disagree by one character the lookup misses and the bucket reports **0 for real
 * data** — no error, no warning, just a wrong number.
 *
 * Before this was fixed, one instant produced three different answers:
 *
 *   date        PHP `Y-W`   pgsql `IYYY-IW`   sqlite `%Y-%W`
 *   2023-01-01  2023-52     2022-52           2023-00
 *   2021-01-01  2021-53     2020-53           2021-00
 *   2024-12-30  2024-01     2025-01           2024-53
 *
 * Each was wrong in its own way. PHP's `Y` is the *calendar* year pinned to an *ISO* week
 * (`o` is the ISO week-numbering year) — so the last days of December and the first days
 * of January were filed under a year the week does not belong to. sqlite's `%W` is not an
 * ISO week at all: it is week-of-year with a Monday start and no year correction, hence
 * `00`. Only Postgres was already right.
 *
 * All three now agree on ISO-8601. The suite's other data is mid-year (2023-02/03), where
 * the three grammars coincide — which is exactly why this hid for the package's whole life
 * and why these cases are pinned at the boundary rather than anywhere convenient.
 */

/**
 * The unit-level agreement, checked directly against the engine rather than through a
 * metric, so a failure names the grammar rather than the trend.
 */
it('agrees on the ISO week key between PHP and the live driver', function (string $date, string $expected): void {
    // The very expression the trend runs: resolved from metrics.trend_drivers by the LIVE
    // driver, so this asserts the grammar actually in use rather than a chosen one.
    $expression = resolve(config('metrics.trend_drivers')[DB::connection()->getDriverName()])
        ->toSql('WEEK', 'created_at');

    createUsersForMetricsTesting([['balance' => 1, 'created_at' => $date]]);

    $sqlKey = DB::table('users')->selectRaw("{$expression} as k")->value('k');

    expect(Unit::Week->format(now()->parse($date)))->toBe($expected)
        ->and((string) $sqlKey)->toBe($expected)
        ->and(DB::connection()->getDriverName())->toBe(DriverMatrix::driver());
})->with([
    // The first week of a year that belongs to the PREVIOUS ISO year.
    '2023-01-01 -> 2022-52' => ['2023-01-01 12:00:00', '2022-52'],
    '2021-01-01 -> 2020-53' => ['2021-01-01 12:00:00', '2020-53'],
    '2016-01-03 -> 2015-53' => ['2016-01-03 12:00:00', '2015-53'],
    // The last week of a year that belongs to the NEXT ISO year.
    '2024-12-30 -> 2025-01' => ['2024-12-30 12:00:00', '2025-01'],
    '2019-12-30 -> 2020-01' => ['2019-12-30 12:00:00', '2020-01'],
    // A 53-week year's own final week, and an ordinary mid-year week for contrast.
    '2020-12-31 -> 2020-53' => ['2020-12-31 12:00:00', '2020-53'],
    '2023-03-08 -> 2023-10' => ['2023-03-08 12:00:00', '2023-10'],
]);

/**
 * The end-to-end consequence, and the reason the mismatch mattered: with gap-filling ON,
 * a key the PHP range does not contain is simply never found, so a week with real rows in
 * it reports 0.
 */
it('gap-fills a year-boundary week with the real count, not zero', function (): void {
    // Three users created in the ISO week that spans the 2022/2023 new year (2022-52).
    createUsersForMetricsTesting([
        ['balance' => 10, 'created_at' => '2022-12-30 10:00:00'],
        ['balance' => 20, 'created_at' => '2023-01-01 10:00:00'],
        ['balance' => 30, 'created_at' => '2023-01-01 23:00:00'],
    ]);

    $trends = Users::make()
        ->weekly()
        ->range('CUSTOM', '2022-12-26 00:00:00', '2023-01-01 23:59:59')
        ->toArray()['result']['trends'];

    // The gap-filled range and the aggregate must land on the same key, so the count is
    // real. Before the fix PHP filled '2023-52' while the engines reported '2022-52' (pgsql)
    // or '2023-00' (sqlite), and this bucket read 0.0 with three rows sitting in it.
    expect($trends)->toBe(['2022-52' => 3.0]);
});

/**
 * The other edge: a week filed under the FOLLOWING ISO year. Without this, the fix could
 * pass by treating every boundary week as belonging to the earlier year.
 */
it('gap-fills a week that belongs to the next ISO year', function (): void {
    createUsersForMetricsTesting([
        ['balance' => 10, 'created_at' => '2024-12-30 10:00:00'],
        ['balance' => 20, 'created_at' => '2024-12-31 10:00:00'],
    ]);

    $trends = Users::make()
        ->weekly()
        ->range('CUSTOM', '2024-12-30 00:00:00', '2025-01-05 23:59:59')
        ->toArray()['result']['trends'];

    expect($trends)->toBe(['2025-01' => 2.0]);
});

/**
 * `Unit::parse()` is the inverse of `format()` and drives the gap-filled range's start and
 * end. It already used `setISODate()` — ISO throughout — which is precisely why `format()`
 * emitting a calendar year was a mismatch *within PHP itself*, before any driver was
 * involved.
 */
it('round-trips an ISO week key through parse and format', function (string $key): void {
    expect(Unit::Week->format(Unit::Week->parse($key)))->toBe($key);
})->with(['2022-52', '2020-53', '2025-01', '2023-10', '2015-53']);
