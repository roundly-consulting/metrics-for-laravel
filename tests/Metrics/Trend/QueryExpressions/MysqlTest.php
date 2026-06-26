<?php

declare(strict_types=1);

use RoundlyConsulting\Metrics\Exceptions\InvalidUnitException;
use RoundlyConsulting\Metrics\Metrics\Trend\QueryExpressions\Mysql;

it('returns correct query expression for each format', function () {
    $mysql = new Mysql;

    expect([
        $mysql->toSql('MINUTE', 'created_at'),
        $mysql->toSql('HOUR', 'created_at'),
        $mysql->toSql('DAY', 'created_at'),
        $mysql->toSql('WEEK', 'created_at'),
        $mysql->toSql('MONTH', 'created_at'),
        $mysql->toSql('YEAR', 'created_at'),
    ])->toBe([
        "date_format(created_at, '%Y-%m-%d %H:%i:00')",
        "date_format(created_at, '%Y-%m-%d %H:00')",
        "date_format(created_at, '%Y-%m-%d')",
        "date_format(created_at, '%Y-%v')",
        "date_format(created_at, '%Y-%m')",
        "date_format(created_at, '%Y')",
    ]);
});

it('throws exception when invalid unit is used', function () {
    $mysql = new Mysql;

    $mysql->toSql('unknown', 'created_at');
})->throws(InvalidUnitException::class, 'Provided `unknown` could not be mapped to correct value.');
