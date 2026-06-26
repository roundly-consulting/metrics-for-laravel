<?php

declare(strict_types=1);

use RoundlyConsulting\Metrics\Enums\Period;
use RoundlyConsulting\Metrics\Enums\Unit;
use RoundlyConsulting\Metrics\Exceptions\InvalidRangeException;
use RoundlyConsulting\Metrics\Tests\Metrics\Trend\UsersBalance;
use RoundlyConsulting\Metrics\Tests\Metrics\Value\Users;

it('accepts a Period enum and a string interchangeably', function (): void {
    createUsersForMetricsTesting([
        ['balance' => 1, 'created_at' => now()->startOfDay()],
    ]);

    $fromEnum = Users::make()->range(Period::Today)->toArray();
    $fromString = Users::make()->range('TODAY')->toArray();

    expect($fromEnum)->toBe($fromString)
        ->and($fromEnum['range']['current'])->toBe('TODAY');
});

it('accepts a Unit enum and a string interchangeably', function (): void {
    createUsersForMetricsTesting([
        ['balance' => 100, 'created_at' => '2023-02-26 10:00:00'],
        ['balance' => 80, 'created_at' => '2023-02-27 10:00:00'],
    ]);

    $fromEnum = UsersBalance::make()->unit(Unit::Day)->toArray();
    $fromString = UsersBalance::make()->unit('DAY')->toArray();

    expect($fromEnum)->toBe($fromString);
});

it('ignores an unknown unit string', function (): void {
    $metric = UsersBalance::make()->daily()->unit('NONSENSE');

    expect($metric->toArray())->toBeArray();
});

it('lists period options with translatable labels', function (): void {
    expect(Period::options())->toBe([
        7 => '7 Days',
        14 => '14 Days',
        30 => '30 Days',
        60 => '60 Days',
        90 => '90 Days',
        365 => '365 Days',
        'YESTERDAY' => 'Yesterday',
        'TODAY' => 'Today',
        'WTD' => 'Week To Date',
        'MTD' => 'Month To Date',
        'QTD' => 'Quarter To Date',
        'YTD' => 'Year To Date',
        'THIS_WEEK' => 'This Week',
        'LAST_WEEK' => 'Last Week',
        'THIS_MONTH' => 'This Month',
        'LAST_MONTH' => 'Last Month',
        'THIS_QUARTER' => 'This Quarter',
        'LAST_QUARTER' => 'Last Quarter',
        'THIS_YEAR' => 'This Year',
        'LAST_YEAR' => 'Last Year',
        'CUSTOM' => 'Custom',
        'ALL' => 'All',
    ]);
});

it('throws when resolving a range for the unbounded all period', function (): void {
    Period::All->toRange();
})->throws(InvalidRangeException::class);

it('resolves a custom range through the period enum', function (): void {
    $range = Period::Custom->toRange('2023-03-01 00:00:00', '2023-03-05 00:00:00');

    expect($range->start()->toDateString())->toBe('2023-03-01')
        ->and($range->end()->toDateString())->toBe('2023-03-05');
});
