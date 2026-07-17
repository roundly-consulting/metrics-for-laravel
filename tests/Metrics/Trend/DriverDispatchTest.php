<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use RoundlyConsulting\Metrics\Enums\Unit;
use RoundlyConsulting\Metrics\Tests\Metrics\Trend\UsersBalance;
use RoundlyConsulting\Metrics\Types\Trend\QueryExpressions\Postgres;
use RoundlyConsulting\Metrics\Types\Trend\QueryExpressions\Sqlite;
use RoundlyConsulting\PackageToolkit\Enums\DatabaseDriver;
use RoundlyConsulting\Testing\Database\DriverMatrix;

/**
 * D — the driver matrix. Metrics is the package the toolkit row's lesson was written for:
 * `ComputesTrend::resolveQueryExpression()` dispatches a **different QueryExpression class
 * per driver**, so the SQL it runs is not the SQL its suite ever exercised.
 *
 * Before this row the Postgres branch had **never executed even once**. It was covered
 * only by `QueryExpressions/PostgresTest.php`, which asserts the *string* `toSql()` returns
 * and never lets a database see it — the precise shape of the toolkit's `ilike` finding,
 * where a divergent branch that had never run passed the whole sqlite suite.
 *
 * These cases run on every leg and are the real proof only on the pgsql one.
 */

/**
 * The driver-truth assertion. It compares the **env-declared** driver against what the
 * **connection itself answers**, which makes a lying leg impossible: a `test-pgsql` job
 * that quietly stayed on sqlite goes red here rather than reporting green having asserted
 * nothing. It fires automatically, so it is strictly stronger than reading a skip count by
 * hand — that stays as the backstop, not the primary control.
 */
it('runs on the driver the leg declares', function (): void {
    expect(DatabaseDriver::current())->toBe(DatabaseDriver::from(DriverMatrix::driver()));
});

/**
 * The dispatch itself: the class chosen must match the live connection, not a default.
 * This is what proves the Postgres branch is reached on the pgsql leg rather than merely
 * existing in the config map.
 */
it('dispatches the query expression that matches the live driver', function (): void {
    $expected = match (DriverMatrix::driver()) {
        'pgsql' => Postgres::class,
        'sqlite' => Sqlite::class,
        default => null,
    };

    expect($expected)->not->toBeNull('unmapped driver in this assertion')
        ->and(config('metrics.trend_drivers')[DB::connection()->getDriverName()])->toBe($expected);
});

/**
 * The end-to-end proof, and the one that matters: every bucket unit, executed as real SQL
 * on whatever engine the leg configured, with gap-filling **off** so the assertion reads
 * the aggregate's own keys rather than keys PHP generated.
 *
 * This is the sharp edge of a per-driver grammar. The SQL side formats the bucket key
 * (`to_char(...)` on Postgres, `strftime(...)` on sqlite) and the PHP side formats the same
 * key independently ({@see Unit::format()}); gap-filling then looks the SQL key up in a PHP
 * dict. If the two grammars disagree by even one character, the lookup misses and the trend
 * silently reports **0 for real data** — no error, just wrong numbers.
 */
it('buckets every unit through the live driver grammar', function (Unit $unit, string $expectedKey): void {
    createUsersForMetricsTesting([
        ['balance' => 100, 'created_at' => '2023-03-08 14:30:00'],
        ['balance' => 50, 'created_at' => '2023-03-08 14:30:00'],
    ]);

    $result = UsersBalance::make()->unit($unit)->range('ALL')->withoutGapFilling()->toArray();

    // The engine's own key, and the value summed under it.
    expect($result['result']['trends'])->toBe([$expectedKey => 150.0])
        ->and(DB::connection()->getDriverName())->toBe(DriverMatrix::driver());
})->with([
    'minute' => [Unit::Minute, '2023-03-08 14:30:00'],
    'hour' => [Unit::Hour, '2023-03-08 14:00'],
    'day' => [Unit::Day, '2023-03-08'],
    'month' => [Unit::Month, '2023-03'],
    'year' => [Unit::Year, '2023'],
]);

/**
 * WEEK is deliberately excluded from the set above and pinned separately here, because the
 * two grammars only agree away from the year boundary — see the note below.
 */
it('buckets a mid-year week identically on the SQL and PHP sides', function (): void {
    createUsersForMetricsTesting([
        ['balance' => 100, 'created_at' => '2023-03-08 14:30:00'],
    ]);

    $result = UsersBalance::make()->weekly()->range('ALL')->withoutGapFilling()->toArray();

    // The SQL side's key…
    expect(array_keys($result['result']['trends']))->toBe(['2023-10'])
        // …and the PHP side's key for the same instant, which gap-filling looks up.
        ->and(Unit::Week->format(now()->parse('2023-03-08 14:30:00')))->toBe('2023-10');
});

/**
 * KNOWN, PRE-EXISTING, NOT FIXED HERE — recorded so the next row does not rediscover it.
 *
 * The WEEK bucket keys disagree between the PHP side and BOTH driver grammars at ISO
 * week/year boundaries, so a weekly trend that gap-fills reports **0 for real data** in the
 * first and last week of a year:
 *
 *   date        PHP `Y-W`   pgsql `IYYY-IW`   sqlite `%Y-%W`
 *   2023-01-01  2023-52     2022-52           2023-00
 *   2021-01-01  2021-53     2020-53           2021-00
 *   2024-12-30  2024-01     2025-01           2024-53
 *
 * Three different answers for one instant. PHP's `Y` is the *calendar* year pinned to an
 * *ISO* week (`o` is the ISO year); Postgres' `IYYY-IW` is correctly ISO throughout;
 * sqlite's `%W` is not an ISO week at all. Postgres is the most correct of the three — this
 * is not a defect the pgsql branch introduced.
 *
 * It is left alone on purpose: every candidate fix changes the bucket keys a trend
 * **returns**, which is outside an adoption row's remit. The suite's data is mid-year
 * (2023-02/03), which is exactly why it has stayed invisible.
 */
