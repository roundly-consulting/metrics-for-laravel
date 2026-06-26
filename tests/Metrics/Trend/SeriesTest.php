<?php

declare(strict_types=1);

use RoundlyConsulting\Metrics\Tests\Metrics\Trend\Users;

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
