<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use RoundlyConsulting\Metrics\Enums\Period;
use RoundlyConsulting\Metrics\Ranges\LastMonth;
use RoundlyConsulting\Metrics\Ranges\LastQuarter;
use RoundlyConsulting\Metrics\Ranges\LastWeek;
use RoundlyConsulting\Metrics\Ranges\LastYear;
use RoundlyConsulting\Metrics\Ranges\QuarterToDate;
use RoundlyConsulting\Metrics\Ranges\ThisMonth;
use RoundlyConsulting\Metrics\Ranges\ThisQuarter;
use RoundlyConsulting\Metrics\Ranges\ThisWeek;
use RoundlyConsulting\Metrics\Ranges\ThisYear;
use RoundlyConsulting\Metrics\Ranges\WeekToDate;
use RoundlyConsulting\Metrics\Tests\Metrics\Value\Users;

// Test "now" is fixed at 2023-03-10 10:00:00 (a Friday) by the TestCase.

it('resolves the current window for each new range', function (string $class, string $start, string $end): void {
    $range = new $class;

    expect($range->start()->toDateTimeString())->toBe($start)
        ->and($range->end()->toDateTimeString())->toBe($end);
})->with([
    'week to date' => [WeekToDate::class, '2023-03-06 00:00:00', '2023-03-10 10:00:00'],
    'quarter to date' => [QuarterToDate::class, '2023-01-01 00:00:00', '2023-03-10 10:00:00'],
    'this week' => [ThisWeek::class, '2023-03-06 00:00:00', '2023-03-12 23:59:59'],
    'last week' => [LastWeek::class, '2023-02-27 00:00:00', '2023-03-05 23:59:59'],
    'this month' => [ThisMonth::class, '2023-03-01 00:00:00', '2023-03-31 23:59:59'],
    'last month' => [LastMonth::class, '2023-02-01 00:00:00', '2023-02-28 23:59:59'],
    'this quarter' => [ThisQuarter::class, '2023-01-01 00:00:00', '2023-03-31 23:59:59'],
    'last quarter' => [LastQuarter::class, '2022-10-01 00:00:00', '2022-12-31 23:59:59'],
    'this year' => [ThisYear::class, '2023-01-01 00:00:00', '2023-12-31 23:59:59'],
    'last year' => [LastYear::class, '2022-01-01 00:00:00', '2022-12-31 23:59:59'],
]);

it('shifts to the previous window for each new range', function (string $class): void {
    $range = new $class;
    $previous = $range->previous();

    expect($previous->start()->lessThan($range->start()))->toBeTrue()
        ->and($previous->end()->lessThanOrEqualTo($range->start()))->toBeTrue();
})->with([
    WeekToDate::class,
    QuarterToDate::class,
    ThisWeek::class,
    LastWeek::class,
    ThisMonth::class,
    LastMonth::class,
    ThisQuarter::class,
    LastQuarter::class,
    ThisYear::class,
    LastYear::class,
]);

it('resolves a configured reporting timezone', function (): void {
    config()->set('metrics.timezone', 'Asia/Tokyo');

    $range = new ThisYear;

    expect($range->start()->getTimezone()->getName())->toBe('Asia/Tokyo');
});

it('counts users over a this-month range', function (): void {
    createUsersForMetricsTesting([
        ['balance' => 1, 'created_at' => '2023-02-28 23:00:00'],
        ['balance' => 1, 'created_at' => '2023-03-02 10:00:00'],
        ['balance' => 1, 'created_at' => '2023-03-09 10:00:00'],
    ]);

    $metrics = Users::make()->range(Period::ThisMonth)->toArray();

    expect($metrics['result']['value'])->toBe(2.0);
});

it('counts users over a last-week range', function (): void {
    createUsersForMetricsTesting([
        ['balance' => 1, 'created_at' => '2023-03-01 10:00:00'],
        ['balance' => 1, 'created_at' => '2023-03-02 10:00:00'],
        ['balance' => 1, 'created_at' => '2023-03-08 10:00:00'],
    ]);

    $metrics = Users::make()->range(Period::LastWeek)->toArray();

    expect($metrics['result']['value'])->toBe(2.0);
})->skip(fn () => CarbonImmutable::now()->dayOfWeek === CarbonImmutable::MONDAY, 'boundary-sensitive');
