<?php

declare(strict_types=1);

use RoundlyConsulting\Metrics\Exceptions\InvalidUnitException;
use RoundlyConsulting\Metrics\Types\Trend\QueryExpressions\Sqlite;

it('returns correct query expression for each format', function () {
    $sqlite = new Sqlite;

    expect([
        $sqlite->toSql('MINUTE', 'created_at'),
        $sqlite->toSql('HOUR', 'created_at'),
        $sqlite->toSql('DAY', 'created_at'),
        $sqlite->toSql('WEEK', 'created_at'),
        $sqlite->toSql('MONTH', 'created_at'),
        $sqlite->toSql('YEAR', 'created_at'),
    ])->toBe([
        "strftime('%Y-%m-%d %H:%M:00', datetime(created_at))",
        "strftime('%Y-%m-%d %H:00', datetime(created_at))",
        "strftime('%Y-%m-%d', datetime(created_at))",
        // ISO-8601, computed: sqlite has no ISO week, and `%W` is not one — it counts
        // from the first Monday with no year correction, so it produced `2023-00` where
        // the other drivers said `2022-52`. The Thursday of a week decides its ISO year.
        "strftime('%Y', date(created_at, '-3 days', 'weekday 4')) || '-' || "
        ."printf('%02d', (strftime('%j', date(created_at, '-3 days', 'weekday 4')) - 1) / 7 + 1)",
        "strftime('%Y-%m', datetime(created_at))",
        "strftime('%Y', datetime(created_at))",
    ]);
});

it('throws exception when invalid unit is used', function () {
    $sqlite = new Sqlite;

    $sqlite->toSql('unknown', 'created_at');
})->throws(InvalidUnitException::class, 'Provided `unknown` could not be mapped to correct value.');
