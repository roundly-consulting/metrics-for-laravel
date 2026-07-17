<?php

declare(strict_types=1);

use RoundlyConsulting\Metrics\Exceptions\InvalidRangeException;
use RoundlyConsulting\Metrics\Exceptions\MissingTrendQueryExpressionException;
use RoundlyConsulting\Metrics\Tests\Metrics\Trend\AverageUsersBalance;
use RoundlyConsulting\Metrics\Tests\Metrics\Trend\MaxUsersBalanceHourly;
use RoundlyConsulting\Metrics\Tests\Metrics\Trend\MinUsersBalanceMinutely;
use RoundlyConsulting\Metrics\Tests\Metrics\Trend\Users;
use RoundlyConsulting\Metrics\Tests\Metrics\Trend\UsersBalance;
use RoundlyConsulting\Metrics\Tests\Metrics\Trend\UsersForMissingQueryExpression;
use RoundlyConsulting\Testing\Database\DriverMatrix;

it('returns balance metrics for all users daily', function () {
    createUsersForMetricsTesting([
        ['balance' => 100, 'created_at' => '2023-02-26 10:00:00'],
        ['balance' => 80, 'created_at' => '2023-02-27 10:00:00'],
        ['balance' => 40, 'created_at' => '2023-02-27 11:05:00'],
        ['balance' => 300, 'created_at' => '2023-02-28 10:00:00'],
    ]);

    $metrics = UsersBalance::make()->daily()->toArray();

    expect($metrics)
        ->toBeArray()
        ->and($metrics['result'])
        ->toBe([
            'trends' => [
                '2023-02-26' => 100.0,
                '2023-02-27' => 120.0,
                '2023-02-28' => 300.0,
            ],
        ]);
});

it('returns balance metrics for all users monthly', function () {
    createUsersForMetricsTesting([
        ['balance' => 100, 'created_at' => '2023-02-26 10:00:00'],
        ['balance' => 80, 'created_at' => '2023-02-27 10:00:00'],
        ['balance' => 40, 'created_at' => '2023-03-27 11:05:00'],
        ['balance' => 300, 'created_at' => '2023-03-28 10:00:00'],
    ]);

    $metrics = UsersBalance::make()->monthly()->toArray();

    expect($metrics)
        ->toBeArray()
        ->and($metrics['result'])
        ->toBe([
            'trends' => [
                '2023-02' => 180.0,
                '2023-03' => 340.0,
            ],
        ]);
});

it('returns balance metrics for all users yearly', function () {
    createUsersForMetricsTesting([
        ['balance' => 821, 'created_at' => '2022-02-26 10:00:00'],
        ['balance' => 100, 'created_at' => '2023-02-26 10:00:00'],
        ['balance' => 80, 'created_at' => '2023-02-27 10:00:00'],
        ['balance' => 40, 'created_at' => '2023-03-27 11:05:00'],
        ['balance' => 300, 'created_at' => '2023-03-28 10:00:00'],
    ]);

    $metrics = UsersBalance::make()->yearly()->toArray();

    expect($metrics)
        ->toBeArray()
        ->and($metrics['result'])
        ->toBe([
            'trends' => [
                '2022' => 821.0,
                '2023' => 520.0,
            ],
        ]);
});

it('returns average balance metrics for all users daily', function () {
    createUsersForMetricsTesting([
        ['balance' => 100, 'created_at' => '2023-02-26 10:00:00'],
        ['balance' => 80, 'created_at' => '2023-02-27 10:00:00'],
        ['balance' => 40, 'created_at' => '2023-02-27 11:05:00'],
        ['balance' => 300, 'created_at' => '2023-02-28 10:00:00'],
        ['balance' => 900, 'created_at' => '2023-02-28 15:00:00'],
    ]);

    $metrics = AverageUsersBalance::make()->daily()->toArray();

    expect($metrics)
        ->toBeArray()
        ->and($metrics['result'])
        ->toBe([
            'trends' => [
                '2023-02-26' => 100.0,
                '2023-02-27' => 60.0,
                '2023-02-28' => 600.0,
            ],
        ]);
});

it('returns trend metrics for created users by week', function () {
    createUsersForMetricsTesting([
        ['balance' => 100, 'created_at' => '2023-02-26 10:00:00'],
        ['balance' => 80, 'created_at' => '2023-02-27 10:00:00'],
        ['balance' => 40, 'created_at' => '2023-02-27 11:05:00'],
        ['balance' => 300, 'created_at' => '2023-02-28 10:00:00'],
        ['balance' => 900, 'created_at' => '2023-02-28 15:00:00'],
        ['balance' => 900, 'created_at' => '2023-03-06 15:00:00'],
        ['balance' => 900, 'created_at' => '2023-03-08 15:00:00'],
    ]);

    $metrics = Users::make()->weekly()->toArray();

    expect($metrics)
        ->toBeArray()
        ->and($metrics['result'])
        ->toBe([
            'trends' => [
                '2023-08' => 1.0,
                '2023-09' => 4.0,
                '2023-10' => 2.0,
            ],
        ]);
});

it('returns max hourly balance of all users', function () {
    createUsersForMetricsTesting([
        ['balance' => 300, 'created_at' => '2023-02-28 10:00:00'],
        ['balance' => 200, 'created_at' => '2023-02-28 11:00:00'],
        ['balance' => 500, 'created_at' => '2023-02-28 11:30:00'],
        ['balance' => 900, 'created_at' => '2023-02-28 11:40:00'],
        ['balance' => 400, 'created_at' => '2023-02-28 12:00:00'],
        ['balance' => 650, 'created_at' => '2023-02-28 12:59:59'],
    ]);

    $metrics = MaxUsersBalanceHourly::make()->toArray();

    expect($metrics)
        ->toBeArray()
        ->and($metrics['result'])
        ->toBe([
            'trends' => [
                '2023-02-28 10:00' => 300.0,
                '2023-02-28 11:00' => 900.0,
                '2023-02-28 12:00' => 650.0,
            ],
        ]);
});

it('returns min balance per minute of all users', function () {
    createUsersForMetricsTesting([
        ['balance' => 200, 'created_at' => '2023-02-28 11:00:00'],
        ['balance' => 500, 'created_at' => '2023-02-28 11:03:00'],
        ['balance' => 700, 'created_at' => '2023-02-28 11:03:40'],
        ['balance' => 900, 'created_at' => '2023-02-28 11:08:00'],
        ['balance' => 400, 'created_at' => '2023-02-28 11:10:00'],
        ['balance' => 300, 'created_at' => '2023-02-28 11:10:20'],
    ]);

    $metrics = MinUsersBalanceMinutely::make()->toArray();

    expect($metrics)
        ->toBeArray()
        ->and($metrics['result'])
        ->toBe([
            'trends' => [
                '2023-02-28 11:00:00' => 200.0,
                '2023-02-28 11:01:00' => 0.0,
                '2023-02-28 11:02:00' => 0.0,
                '2023-02-28 11:03:00' => 500.0,
                '2023-02-28 11:04:00' => 0.0,
                '2023-02-28 11:05:00' => 0.0,
                '2023-02-28 11:06:00' => 0.0,
                '2023-02-28 11:07:00' => 0.0,
                '2023-02-28 11:08:00' => 900.0,
                '2023-02-28 11:09:00' => 0.0,
                '2023-02-28 11:10:00' => 300.0,
            ],
        ]);
});

it('returns balance metrics for all users daily for last 30 days', function () {
    createUsersForMetricsTesting([
        ['balance' => 100, 'created_at' => '2023-03-10 10:00:00'],
        ['balance' => 80, 'created_at' => '2023-03-10 10:00:00'],
        ['balance' => 40, 'created_at' => '2023-03-01 11:05:00'],
        ['balance' => 300, 'created_at' => '2023-02-28 10:00:00'],
        ['balance' => 300, 'created_at' => '2023-02-25 10:00:00'],
        ['balance' => 250, 'created_at' => '2023-02-14 10:00:00'],
    ]);

    $metrics = UsersBalance::make()
        ->weekly()
        ->range('30')
        ->toArray();

    expect($metrics)
        ->toBeArray()
        ->and($metrics['result'])
        ->toBe([
            'trends' => [
                '2023-06' => 0.0,
                '2023-07' => 250.0,
                '2023-08' => 300.0,
                '2023-09' => 340.0,
                '2023-10' => 180.0,
            ],
        ]);
});

it('throws exception when invalid range is used', function () {
    UsersBalance::make()->range('UNKNOWN')->toArray();
})->throws(InvalidRangeException::class, 'Provided `UNKNOWN` could not be resolved.');

it('returns empty result when no records are retrieved from database', function () {
    $metrics = UsersBalance::make()->toArray();

    expect($metrics)
        ->toBeArray()
        ->and($metrics['result'])
        ->toBe([
            'trends' => [],
        ]);
});

/**
 * The driver name is taken from the live connection rather than hard-coded to 'sqlite'.
 * The literal made this case assert the wrong driver the moment the suite ran anywhere
 * else — it is the exception's whole payload, so pinning it to one engine meant the
 * message was only ever verified on sqlite.
 */
it('throws MissingTrendQueryExpressionException exception when no driver has been found', function () {
    config()->set('metrics.trend_drivers', []);

    expect(fn () => UsersForMissingQueryExpression::make()->toArray())
        ->toThrow(
            MissingTrendQueryExpressionException::class,
            'Missing trend query expression for `'.DriverMatrix::driver().'` driver.',
        );
});
