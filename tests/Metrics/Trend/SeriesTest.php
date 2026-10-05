<?php

declare(strict_types=1);

use RoundlyConsulting\Metrics\Facades\Metrics;
use RoundlyConsulting\Metrics\Tests\Metrics\Trend\Users;
use RoundlyConsulting\Metrics\Tests\Models\User;

it('splits a trend into series by a dimension', function (): void {
    createUsersForMetricsTesting([
        ['type' => 'pro', 'created_at' => '2023-02-26 10:00:00'],
        ['type' => 'free', 'created_at' => '2023-02-26 11:00:00'],
        ['type' => 'pro', 'created_at' => '2023-02-27 10:00:00'],
    ]);

    $metrics = Users::make()->daily()->groupBy('type')->toArray();

    expect($metrics['result'])
        ->toHaveKey('series')
        ->and($metrics['result']['trends'])->toBe([
            '2023-02-26' => 2.0,
            '2023-02-27' => 1.0,
        ])
        ->and($metrics['result']['series']['pro'])->toBe([
            '2023-02-26' => 1.0,
            '2023-02-27' => 1.0,
        ])
        ->and($metrics['result']['series']['free'])->toBe([
            '2023-02-26' => 1.0,
            '2023-02-27' => 0.0,
        ]);
});

it('keeps the single-series shape when no dimension is set', function (): void {
    createUsersForMetricsTesting([
        ['type' => 'pro', 'created_at' => '2023-02-26 10:00:00'],
    ]);

    $metrics = Users::make()->daily()->toArray();

    expect($metrics['result'])->not->toHaveKey('series')
        ->and($metrics['result'])->toBe(['trends' => ['2023-02-26' => 1.0]]);
});

it('splits a grouped trend over a bounded range', function (): void {
    createUsersForMetricsTesting([
        ['type' => 'pro', 'created_at' => '2023-03-10 09:00:00'],
        ['type' => 'free', 'created_at' => '2023-03-10 10:00:00'],
    ]);

    $metrics = Users::make()->daily()->groupBy('type')->range('TODAY')->toArray();

    expect($metrics['result']['trends'])->toBe(['2023-03-10' => 2.0])
        ->and($metrics['result']['series']['pro'])->toBe(['2023-03-10' => 1.0])
        ->and($metrics['result']['series']['free'])->toBe(['2023-03-10' => 1.0]);
});

it('returns an empty result when grouped with no rows', function (): void {
    $metrics = Users::make()->daily()->groupBy('type')->toArray();

    expect($metrics['result'])->toBe(['trends' => []]);
});

it('regression: totals a grouped trend with the metric\'s own aggregate, not the sum of its series', function (string $method, float $total): void {
    createUsersForMetricsTesting([
        ['type' => 'pro', 'balance' => 100, 'created_at' => '2023-03-10 09:00:00'],
        ['type' => 'free', 'balance' => 300, 'created_at' => '2023-03-10 09:30:00'],
    ]);

    $result = Metrics::trend()->{$method}(User::query(), 'balance')->groupBy('type')->range('TODAY')->result();

    expect($result->trends())->toBe(['2023-03-10' => $total])
        ->and($result->trends())->toBe(Metrics::trend()->{$method}(User::query(), 'balance')->range('TODAY')->result()->trends());
})->with([
    'average' => ['average', 200.0],
    'max' => ['max', 300.0],
    'min' => ['min', 100.0],
    'sum' => ['sum', 400.0],
    'count' => ['count', 2.0],
]);

it('regression: rounds a grouped total once, after aggregating', function (): void {
    createUsersForMetricsTesting([
        ['type' => 'pro', 'balance' => 0.4, 'created_at' => '2023-03-10 09:00:00'],
        ['type' => 'free', 'balance' => 0.4, 'created_at' => '2023-03-10 09:30:00'],
    ]);

    $result = Metrics::trend()->sum(User::query(), 'balance')->groupBy('type')->range('TODAY')->precision(0)->result();

    expect($result->trends())->toBe(['2023-03-10' => 1.0])
        ->and($result->series())->toBe(['free' => ['2023-03-10' => 0.0], 'pro' => ['2023-03-10' => 0.0]]);
});

it('fills a grouped trend\'s totals over a bounded range with no rows, like the ungrouped trend', function (): void {
    $grouped = Metrics::trend()->count(User::query(), 'id')->groupBy('type')->range('7')->result();

    expect($grouped->series())->toBe([])
        ->and($grouped->trends())->toHaveCount(8)
        ->and(array_sum($grouped->trends()))->toBe(0.0)
        ->and($grouped->trends())->toBe(Metrics::trend()->count(User::query(), 'id')->range('7')->result()->trends());
});

it('regression: merges the NULL and empty-string series instead of dropping rows', function (string $method, float $value): void {
    createUsersForMetricsTesting([
        ['plan' => null, 'balance' => 10, 'created_at' => '2023-03-10 09:00:00'],
        ['plan' => null, 'balance' => 20, 'created_at' => '2023-03-10 09:10:00'],
        ['plan' => '', 'balance' => 60, 'created_at' => '2023-03-10 09:20:00'],
    ]);

    expect(Metrics::trend()->{$method}(User::query(), 'balance')->groupBy('plan')->range('TODAY')->result()->series())
        ->toBe(['' => ['2023-03-10' => $value]]);
})->with([
    'count' => ['count', 3.0],
    'sum' => ['sum', 90.0],
    'average' => ['average', 30.0],
    'max' => ['max', 60.0],
    'min' => ['min', 10.0],
]);
