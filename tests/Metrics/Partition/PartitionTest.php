<?php

declare(strict_types=1);

use RoundlyConsulting\Metrics\Tests\Metrics\Partition\Users;

it('returns count partition metrics for all users', function () {
    createUsersForMetricsTesting([
        ['balance' => 100, 'type' => 'user'],
        ['balance' => 150, 'type' => 'user'],
        ['balance' => 130, 'type' => 'admin'],
        ['balance' => 81, 'type' => 'admin'],
        ['balance' => 5, 'type' => 'admin'],
    ]);

    $metrics = Users::make('type')->toArray();

    expect($metrics['result'])
        ->toBeArray()
        ->toBe([
            'partitions' => [
                'admin' => 3.0,
                'user' => 2.0,
            ],
        ]);
});

it('returns sum partition metrics for all users', function () {
    createUsersForMetricsTesting([
        ['balance' => 100, 'type' => 'user'],
        ['balance' => 150, 'type' => 'user'],
        ['balance' => 130, 'type' => 'admin'],
        ['balance' => 81, 'type' => 'admin'],
        ['balance' => 5, 'type' => 'admin'],
    ]);

    $metrics = Users::make('type', 'sum', 'balance')->toArray();

    expect($metrics['result'])
        ->toBeArray()
        ->toBe([
            'partitions' => [
                'user' => 250.0,
                'admin' => 216.0,
            ],
        ]);
});

it('returns sum partition metrics for all users registered today', function () {
    createUsersForMetricsTesting([
        ['balance' => 100, 'type' => 'user', 'created_at' => '2023-03-09 10:00:00'],
        ['balance' => 150, 'type' => 'user', 'created_at' => '2023-03-10 10:00:00'],
        ['balance' => 130, 'type' => 'admin', 'created_at' => '2023-03-09 10:00:00'],
        ['balance' => 81, 'type' => 'admin', 'created_at' => '2023-03-10 10:00:00'],
        ['balance' => 5, 'type' => 'admin', 'created_at' => '2023-03-10 10:00:00'],
    ]);

    $metrics = Users::make('type', 'sum', 'balance')->range('TODAY')->toArray();

    expect($metrics['result'])
        ->toBeArray()
        ->toBe([
            'partitions' => [
                'user' => 150.0,
                'admin' => 86.0,
            ],
        ]);
});

it('returns min partition metrics for all users', function () {
    createUsersForMetricsTesting([
        ['balance' => 100, 'type' => 'user'],
        ['balance' => 150, 'type' => 'user'],
        ['balance' => 130, 'type' => 'admin'],
        ['balance' => 81, 'type' => 'admin'],
        ['balance' => 5, 'type' => 'admin'],
    ]);

    $metrics = Users::make('type', 'min', 'balance')->toArray();

    expect($metrics['result'])
        ->toBeArray()
        ->toBe([
            'partitions' => [
                'user' => 100.0,
                'admin' => 5.0,
            ],
        ]);
});

it('returns max partition metrics for all users', function () {
    createUsersForMetricsTesting([
        ['balance' => 100, 'type' => 'user'],
        ['balance' => 150, 'type' => 'user'],
        ['balance' => 130, 'type' => 'admin'],
        ['balance' => 81, 'type' => 'admin'],
        ['balance' => 5, 'type' => 'admin'],
    ]);

    $metrics = Users::make('type', 'max', 'balance')->toArray();

    expect($metrics['result'])
        ->toBeArray()
        ->toBe([
            'partitions' => [
                'user' => 150.0,
                'admin' => 130.0,
            ],
        ]);
});

it('returns average partition metrics for all users', function () {
    createUsersForMetricsTesting([
        ['balance' => 100, 'type' => 'user'],
        ['balance' => 150, 'type' => 'user'],
        ['balance' => 130, 'type' => 'admin'],
        ['balance' => 81, 'type' => 'admin'],
        ['balance' => 5, 'type' => 'admin'],
    ]);

    $metrics = Users::make('type', 'average', 'balance')->toArray();

    expect($metrics['result'])
        ->toBeArray()
        ->toBe([
            'partitions' => [
                'user' => 125.0,
                'admin' => 72.0,
            ],
        ]);
});
