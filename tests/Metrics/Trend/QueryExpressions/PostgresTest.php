<?php

declare(strict_types=1);

use RoundlyConsulting\Metrics\Exceptions\InvalidUnitException;
use RoundlyConsulting\Metrics\Types\Trend\QueryExpressions\Postgres;

it('returns correct query expression for each format', function () {
    $postgres = new Postgres;

    expect([
        $postgres->toSql('MINUTE', 'created_at'),
        $postgres->toSql('HOUR', 'created_at'),
        $postgres->toSql('DAY', 'created_at'),
        $postgres->toSql('WEEK', 'created_at'),
        $postgres->toSql('MONTH', 'created_at'),
        $postgres->toSql('YEAR', 'created_at'),
    ])->toBe([
        "to_char(created_at, 'YYYY-MM-DD HH24:MI:00')",
        "to_char(created_at, 'YYYY-MM-DD HH24:00')",
        "to_char(created_at, 'YYYY-MM-DD')",
        "to_char(created_at, 'IYYY-IW')",
        "to_char(created_at, 'YYYY-MM')",
        "to_char(created_at, 'YYYY')",
    ]);
});

it('throws exception when invalid unit is used', function () {
    $postgres = new Postgres;

    $postgres->toSql('unknown', 'created_at');
})->throws(InvalidUnitException::class, 'Provided `unknown` could not be mapped to correct value.');
