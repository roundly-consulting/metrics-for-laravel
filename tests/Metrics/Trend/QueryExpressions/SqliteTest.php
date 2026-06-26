<?php

declare(strict_types=1);

use RoundlyConsulting\Metrics\Exceptions\InvalidUnitException;
use RoundlyConsulting\Metrics\Metrics\Trend\QueryExpressions\Sqlite;

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
        "strftime('%Y-%W', datetime(created_at))",
        "strftime('%Y-%m', datetime(created_at))",
        "strftime('%Y', datetime(created_at))",
    ]);
});

it('throws exception when invalid unit is used', function () {
    $sqlite = new Sqlite;

    $sqlite->toSql('unknown', 'created_at');
})->throws(InvalidUnitException::class, 'Provided `unknown` could not be mapped to correct value.');
