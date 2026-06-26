<?php

declare(strict_types=1);

use RoundlyConsulting\Metrics\Tests\Metrics\Trend\UsersBalance;

it('fills empty buckets with zero by default', function (): void {
    createUsersForMetricsTesting([
        ['balance' => 100, 'created_at' => '2023-02-26 10:00:00'],
        ['balance' => 300, 'created_at' => '2023-02-28 10:00:00'],
    ]);

    $metrics = UsersBalance::make()->daily()->toArray();

    expect($metrics['result']['trends'])->toBe([
        '2023-02-26' => 100.0,
        '2023-02-27' => 0.0,
        '2023-02-28' => 300.0,
    ]);
});

it('omits empty buckets when gap filling is disabled', function (): void {
    createUsersForMetricsTesting([
        ['balance' => 100, 'created_at' => '2023-02-26 10:00:00'],
        ['balance' => 300, 'created_at' => '2023-02-28 10:00:00'],
    ]);

    $metrics = UsersBalance::make()->daily()->withoutGapFilling()->toArray();

    expect($metrics['result']['trends'])->toBe([
        '2023-02-26' => 100.0,
        '2023-02-28' => 300.0,
    ]);
});
