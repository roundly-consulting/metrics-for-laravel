<?php

declare(strict_types=1);

use RoundlyConsulting\Metrics\Tests\Metrics\Value\FullyPredefinedUsersMetrics;
use RoundlyConsulting\Metrics\Tests\Metrics\Value\Users;

it('returns metrics for total users', function () {
    createUsersForMetricsTesting([100, 100]);

    $metrics = Users::make()
        ->name('Fancy users')
        ->description('This metrics shows how many fancy users registered')
        ->prefix('Fancy')
        ->suffix('Users')
        ->withChangeAgainstPreviousPeriod()
        ->toArray();

    expect($metrics)
        ->toBeArray()
        ->toBe([
            'name' => 'Fancy users',
            'description' => 'This metrics shows how many fancy users registered',
            'prefix' => 'Fancy',
            'suffix' => 'Users',
            'range' => [
                'current' => 'ALL',
                'available' => [
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
                ],
                'custom' => [
                    'start' => null,
                    'end' => null,
                ],
            ],
            'result' => [
                'value' => 2.0,
                'previous' => null,
                'change' => [
                    'percentage' => null,
                    'is_increase' => true,
                ],
            ],
        ]);
});

it('returns predefined users metrics', function () {
    createUsersForMetricsTesting([
        ['balance' => 150.12346, 'created_at' => '2023-03-09 05:00:00'],
        ['balance' => 180.5231442, 'created_at' => '2023-03-10 10:00:00'],
    ]);

    $metrics = FullyPredefinedUsersMetrics::make()->toArray();

    expect($metrics)
        ->toBeArray()
        ->toBe([
            'name' => 'My custom name',
            'description' => 'My custom description',
            'prefix' => 'Balance:',
            'suffix' => 'USD',
            'range' => [
                'current' => 'TODAY',
                'available' => [
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
                ],
                'custom' => [
                    'start' => null,
                    'end' => null,
                ],
            ],
            'result' => [
                'value' => 180.5231,
                'previous' => 150.1235,
                'change' => [
                    'percentage' => 20.2497,
                    'is_increase' => true,
                ],
            ],
        ]);
});

it('returns metrics for users created today vs yesterday', function () {
    createUsersForMetricsTesting([
        ['balance' => 100, 'created_at' => now()->startOfDay()->subDay()],
        ['balance' => 100, 'created_at' => now()->startOfDay()->subDay()],
        ['balance' => 100, 'created_at' => now()],
    ]);

    $metrics = Users::make()
        ->name('Fancy users today')
        ->description('This metrics shows how many fancy users registered today')
        ->prefix('Fancy')
        ->suffix('Users')
        ->withChangeAgainstPreviousPeriod()
        ->range('TODAY')
        ->toArray();

    expect($metrics)
        ->toBeArray()
        ->toBe([
            'name' => 'Fancy users today',
            'description' => 'This metrics shows how many fancy users registered today',
            'prefix' => 'Fancy',
            'suffix' => 'Users',
            'range' => [
                'current' => 'TODAY',
                'available' => [
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
                ],
                'custom' => [
                    'start' => null,
                    'end' => null,
                ],
            ],
            'result' => [
                'value' => 1.0,
                'previous' => 2.0,
                'change' => [
                    'percentage' => -50.0,
                    'is_increase' => false,
                ],
            ],
        ]);
});

it('returns 100% change when no previous data were found', function () {
    createUsersForMetricsTesting([
        ['balance' => 100, 'created_at' => now()],
    ]);

    $metrics = Users::make()
        ->withChangeAgainstPreviousPeriod()
        ->range('TODAY')
        ->toArray();

    expect($metrics['result'])
        ->toBeArray()
        ->toBe([
            'value' => 1.0,
            'previous' => 0.0,
            'change' => [
                'percentage' => 100.0,
                'is_increase' => true,
            ],
        ]);
});

it('returns zero values when no previous and current data were found', function () {
    $metrics = Users::make()
        ->withChangeAgainstPreviousPeriod()
        ->range('TODAY')
        ->toArray();

    expect($metrics['result'])
        ->toBeArray()
        ->toBe([
            'value' => 0.0,
            'previous' => 0.0,
            'change' => [
                'percentage' => 0.0,
                'is_increase' => false,
            ],
        ]);
});

it('returns metrics for users created for last 30 days', function () {
    createUsersForMetricsTesting([
        ['balance' => 100, 'created_at' => now()->subMonths(2)],
        ['balance' => 50, 'created_at' => now()->subDays(15)],
        ['balance' => 30, 'created_at' => now()],
    ]);

    $metrics = Users::make()->withChangeAgainstPreviousPeriod()->range('30')->toArray();

    expect($metrics['result'])
        ->toBeArray()
        ->toBe([
            'value' => 2.0,
            'previous' => 1.0,
            'change' => [
                'percentage' => 100.0,
                'is_increase' => true,
            ],
        ]);
});

it('returns metrics for users created for last 60 days', function () {
    createUsersForMetricsTesting([
        ['balance' => 100, 'created_at' => now()->subMonths(4)],
        ['balance' => 100, 'created_at' => now()->subMonths(3)],
        ['balance' => 100, 'created_at' => now()->subMonths(3)],
        ['balance' => 100, 'created_at' => now()->subMonths(2)],
        ['balance' => 100, 'created_at' => now()->subMonths(2)],
        ['balance' => 50, 'created_at' => now()->subDays(15)],
        ['balance' => 30, 'created_at' => now()],
    ]);

    $metrics = Users::make()->withChangeAgainstPreviousPeriod()->range('60')->toArray();

    expect($metrics['result'])
        ->toBeArray()
        ->toBe([
            'value' => 4.0,
            'previous' => 3.0,
            'change' => [
                'percentage' => 33.0,
                'is_increase' => true,
            ],
        ]);
});

it('returns metrics for users created for last 90 days', function () {
    createUsersForMetricsTesting([
        ['balance' => 100, 'created_at' => now()->subMonths(4)],
        ['balance' => 100, 'created_at' => now()->subMonths(3)],
        ['balance' => 100, 'created_at' => now()->subMonths(3)],
        ['balance' => 100, 'created_at' => now()->subMonths(2)],
        ['balance' => 100, 'created_at' => now()->subMonths(2)],
        ['balance' => 50, 'created_at' => now()->subDays(15)],
        ['balance' => 30, 'created_at' => now()],
    ]);

    $metrics = Users::make()->withChangeAgainstPreviousPeriod()->range('90')->toArray();

    expect($metrics['result'])
        ->toBeArray()
        ->toBe([
            'value' => 6.0,
            'previous' => 1.0,
            'change' => [
                'percentage' => 500.0,
                'is_increase' => true,
            ],
        ]);
});

it('returns metrics for users created for last 365 days', function () {
    createUsersForMetricsTesting([
        ['balance' => 100, 'created_at' => now()->subDays(366)->subMonths(3)],
        ['balance' => 100, 'created_at' => now()->subDays(366)],
        ['balance' => 100, 'created_at' => now()->subMonths(4)],
        ['balance' => 100, 'created_at' => now()->subMonths(3)],
        ['balance' => 100, 'created_at' => now()->subMonths(3)],
        ['balance' => 100, 'created_at' => now()->subMonths(2)],
        ['balance' => 100, 'created_at' => now()->subMonths(2)],
        ['balance' => 50, 'created_at' => now()->subDays(15)],
        ['balance' => 30, 'created_at' => now()],
    ]);

    $metrics = Users::make()->withChangeAgainstPreviousPeriod()->range('365')->toArray();

    expect($metrics['result'])
        ->toBeArray()
        ->toBe([
            'value' => 7.0,
            'previous' => 2.0,
            'change' => [
                'percentage' => 250.0,
                'is_increase' => true,
            ],
        ]);
});

it('returns metrics for users created yesterday', function () {
    createUsersForMetricsTesting([
        ['balance' => 100, 'created_at' => now()->startOfDay()->subDays(2)],
        ['balance' => 100, 'created_at' => now()->startOfDay()->subDays(2)],
        ['balance' => 100, 'created_at' => now()->startOfDay()->subDays(2)],
        ['balance' => 100, 'created_at' => now()->startOfDay()->subDay()],
        ['balance' => 50, 'created_at' => now()->startOfDay()->subDay()],
        ['balance' => 30, 'created_at' => now()->startOfDay()],
    ]);

    $metrics = Users::make()->withChangeAgainstPreviousPeriod()->range('YESTERDAY')->toArray();

    expect($metrics['result'])
        ->toBeArray()
        ->toBe([
            'value' => 2.0,
            'previous' => 3.0,
            'change' => [
                'percentage' => -33.0,
                'is_increase' => false,
            ],
        ]);
});

it('returns metrics for users created today', function () {
    createUsersForMetricsTesting([
        ['balance' => 100, 'created_at' => now()->startOfDay()->subDays(2)],
        ['balance' => 100, 'created_at' => now()->startOfDay()->subDays(2)],
        ['balance' => 100, 'created_at' => now()->startOfDay()->subDays(2)],
        ['balance' => 100, 'created_at' => now()->startOfDay()->subDay()],
        ['balance' => 50, 'created_at' => now()->startOfDay()],
        ['balance' => 30, 'created_at' => now()->startOfDay()],
    ]);

    $metrics = Users::make()->withChangeAgainstPreviousPeriod()->range('TODAY')->toArray();

    expect($metrics['result'])
        ->toBeArray()
        ->toBe([
            'value' => 2.0,
            'previous' => 1.0,
            'change' => [
                'percentage' => 100.0,
                'is_increase' => true,
            ],
        ]);
});

it('returns metrics for users created month to date', function () {
    createUsersForMetricsTesting([
        ['balance' => 1, 'created_at' => now()->subMonth()->subDay()],
        ['balance' => 1, 'created_at' => now()->subDays(5)],
        ['balance' => 1, 'created_at' => now()->startOfDay()],
    ]);

    $metrics = Users::make()->withChangeAgainstPreviousPeriod()->range('MTD')->toArray();

    expect($metrics['result'])
        ->toBeArray()
        ->toBe([
            'value' => 2.0,
            'previous' => 1.0,
            'change' => [
                'percentage' => 100.0,
                'is_increase' => true,
            ],
        ]);
});

it('returns metrics for users created year to date', function () {
    createUsersForMetricsTesting([
        ['balance' => 1, 'created_at' => now()->subYear()->subDay()],
        ['balance' => 1, 'created_at' => now()->subMonth()->subDay()],
        ['balance' => 1, 'created_at' => now()->subDays(5)],
        ['balance' => 1, 'created_at' => now()->startOfDay()],
    ]);

    $metrics = Users::make()->withChangeAgainstPreviousPeriod()->range('YTD')->toArray();

    expect($metrics['result'])
        ->toBeArray()
        ->toBe([
            'value' => 3.0,
            'previous' => 1.0,
            'change' => [
                'percentage' => 200.0,
                'is_increase' => true,
            ],
        ]);
});

it('returns metrics for users created with custom range', function () {
    createUsersForMetricsTesting([
        ['balance' => 1, 'created_at' => '2023-03-03 10:00:00'],
        ['balance' => 1, 'created_at' => '2023-03-05 10:00:01'],
        ['balance' => 1, 'created_at' => '2023-03-09 10:00:00'],
        ['balance' => 1, 'created_at' => '2023-03-10 10:00:00'],
    ]);

    $metrics = Users::make()
        ->withChangeAgainstPreviousPeriod()
        ->range('CUSTOM', '2023-03-05 10:00:00', '2023-03-10 10:00:00')
        ->toArray();

    expect($metrics['result'])
        ->toBeArray()
        ->toBe([
            'value' => 3.0,
            'previous' => 1.0,
            'change' => [
                'percentage' => 200.0,
                'is_increase' => true,
            ],
        ]);
});

it('returns average users balance', function () {
    createUsersForMetricsTesting([
        ['balance' => 8, 'created_at' => '2023-03-03 10:00:00'],
        ['balance' => 4, 'created_at' => '2023-03-05 10:00:01'],
        ['balance' => 2, 'created_at' => '2023-03-09 10:00:00'],
        ['balance' => 3, 'created_at' => '2023-03-10 10:00:00'],
    ]);

    $metrics = Users::make('average', 'balance')
        ->precision(2)
        ->withChangeAgainstPreviousPeriod()
        ->toArray();

    expect($metrics['result'])
        ->toBeArray()
        ->toBe([
            'value' => 4.25,
            'previous' => null,
            'change' => [
                'percentage' => null,
                'is_increase' => true,
            ],
        ]);
});

it('returns sum of users balance', function () {
    createUsersForMetricsTesting([
        ['balance' => 8, 'created_at' => '2023-03-03 10:00:00'],
        ['balance' => 4, 'created_at' => '2023-03-05 10:00:01'],
        ['balance' => 2, 'created_at' => '2023-03-09 10:00:00'],
        ['balance' => 3, 'created_at' => '2023-03-10 10:00:00'],
    ]);

    $metrics = Users::make('sum', 'balance')
        ->withChangeAgainstPreviousPeriod()
        ->toArray();

    expect($metrics['result'])
        ->toBeArray()
        ->toBe([
            'value' => 17.0,
            'previous' => null,
            'change' => [
                'percentage' => null,
                'is_increase' => true,
            ],
        ]);
});

it('returns max of users balance', function () {
    createUsersForMetricsTesting([
        ['balance' => 8, 'created_at' => '2023-03-03 10:00:00'],
        ['balance' => 4, 'created_at' => '2023-03-05 10:00:01'],
        ['balance' => 2, 'created_at' => '2023-03-09 10:00:00'],
        ['balance' => 3, 'created_at' => '2023-03-10 10:00:00'],
    ]);

    $metrics = Users::make('max', 'balance')
        ->withChangeAgainstPreviousPeriod()
        ->toArray();

    expect($metrics['result'])
        ->toBeArray()
        ->toBe([
            'value' => 8.0,
            'previous' => null,
            'change' => [
                'percentage' => null,
                'is_increase' => true,
            ],
        ]);
});

it('returns min of users balance', function () {
    createUsersForMetricsTesting([
        ['balance' => 8, 'created_at' => '2023-03-03 10:00:00'],
        ['balance' => 4, 'created_at' => '2023-03-05 10:00:01'],
        ['balance' => 2, 'created_at' => '2023-03-09 10:00:00'],
        ['balance' => 3, 'created_at' => '2023-03-10 10:00:00'],
    ]);

    $metrics = Users::make('min', 'balance')
        ->withChangeAgainstPreviousPeriod()
        ->toArray();

    expect($metrics['result'])
        ->toBeArray()
        ->toBe([
            'value' => 2.0,
            'previous' => null,
            'change' => [
                'percentage' => null,
                'is_increase' => true,
            ],
        ]);
});
