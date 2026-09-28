<?php

declare(strict_types=1);

use Illuminate\Support\Number;
use RoundlyConsulting\Metrics\Facades\Metrics;
use RoundlyConsulting\Metrics\Tests\Metrics\Trend\UsersBalance;
use RoundlyConsulting\Metrics\Tests\Metrics\Value\Users;
use RoundlyConsulting\Metrics\Tests\Models\User;

it('omits formatting by default', function (): void {
    createUsersForMetricsTesting([1200, 1200]);

    $metrics = Users::make()->toArray();

    expect($metrics['result'])->not->toHaveKey('formatted');
});

it('formats a value via the Number helper', function (): void {
    createUsersForMetricsTesting([['balance' => 1500, 'created_at' => now()]]);

    $metrics = Metrics::value()
        ->sum(User::query(), 'balance')
        ->formatUsing(fn (float $value): string => Number::currency($value, 'USD'))
        ->toArray();

    expect($metrics['result']['value'])->toBe(1500.0)
        ->and($metrics['result']['formatted'])->toBe('$1,500.00');
});

it('formats each point of a trend', function (): void {
    createUsersForMetricsTesting([
        ['balance' => 100, 'created_at' => '2023-02-26 10:00:00'],
        ['balance' => 80, 'created_at' => '2023-02-27 10:00:00'],
    ]);

    $metrics = UsersBalance::make()
        ->daily()
        ->formatUsing(fn (float $value): string => '$'.number_format($value))
        ->toArray();

    expect($metrics['result']['formatted'])->toBe([
        '2023-02-26' => '$100',
        '2023-02-27' => '$80',
    ]);
});

it('formats each partition bucket', function (): void {
    createUsersForMetricsTesting([
        ['balance' => 100, 'type' => 'user'],
        ['balance' => 130, 'type' => 'admin'],
    ]);

    $metrics = Metrics::partition()
        ->sum(User::query(), 'type', 'balance')
        ->formatUsing(fn (float $value): string => number_format($value).' pts')
        ->toArray();

    expect($metrics['result']['formatted'])->toBe([
        'admin' => '130 pts',
        'user' => '100 pts',
    ]);
});
