<?php

declare(strict_types=1);

use RoundlyConsulting\Metrics\Tests\Metrics\Progress\Users;

it('returns progress metrics for total users', function () {
    createUsersForMetricsTesting([100, 100]);

    $metrics = Users::make()->target(5)->toArray();

    expect($metrics['result'])
        ->toBeArray()
        ->toBe([
            'value' => 2.0,
            'progress' => 40.0,
            'previous' => null,
            'previous_progress' => null,
            'target' => 5.0,
            'avoid' => false,
            'change' => [
                'percentage' => null,
                'is_increase' => null,
                'progress' => null,
                'value' => null,
            ],
        ]);
});

it('returns decreased progress metrics for users created today vs yesterday', function () {
    createUsersForMetricsTesting([
        ['balance' => 100, 'created_at' => now()->startOfDay()->subDay()],
        ['balance' => 100, 'created_at' => now()->startOfDay()->subDay()],
        ['balance' => 100, 'created_at' => now()->startOfDay()->subDay()],
        ['balance' => 100, 'created_at' => now()],
    ]);

    $metrics = Users::make()->withChangeAgainstPreviousPeriod()->range('TODAY')->target(4)->shouldBeAvoided()->toArray();

    expect($metrics['result'])
        ->toBeArray()
        ->toBe([
            'value' => 1.0,
            'progress' => 25.0,
            'previous' => 3.0,
            'previous_progress' => 75.0,
            'target' => 4.0,
            'avoid' => true,
            'change' => [
                'percentage' => -67.0,
                'is_increase' => false,
                'progress' => -50.0,
                'value' => -2.0,
            ],
        ]);
});

it('returns increased progress metrics for users created today vs yesterday', function () {
    createUsersForMetricsTesting([
        ['balance' => 100, 'created_at' => now()->startOfDay()->subDay()],
        ['balance' => 100, 'created_at' => now()],
        ['balance' => 100, 'created_at' => now()->addMinute()],
    ]);

    $metrics = Users::make()->withChangeAgainstPreviousPeriod()->range('TODAY')->target(4)->toArray();

    expect($metrics['result'])
        ->toBeArray()
        ->toBe([
            'value' => 2.0,
            'progress' => 50.0,
            'previous' => 1.0,
            'previous_progress' => 25.0,
            'target' => 4.0,
            'avoid' => false,
            'change' => [
                'percentage' => 100.0,
                'is_increase' => true,
                'progress' => 25.0,
                'value' => 1.0,
            ],
        ]);
});

it('returns 100% progress when no target is set and value is greater than 0', function () {
    createUsersForMetricsTesting([
        ['balance' => 100, 'created_at' => now()],
    ]);

    $metrics = Users::make()->range('TODAY')->target(0)->toArray();

    expect($metrics['result'])
        ->toBeArray()
        ->toBe([
            'value' => 1.0,
            'progress' => 100.0,
            'previous' => null,
            'previous_progress' => null,
            'target' => 0.0,
            'avoid' => false,
            'change' => [
                'percentage' => null,
                'is_increase' => null,
                'progress' => null,
                'value' => null,
            ],
        ]);
});

it('returns 0% progress when no target is set and value is 0', function () {
    $metrics = Users::make()->range('TODAY')->target(0)->toArray();

    expect($metrics['result'])
        ->toBeArray()
        ->toBe([
            'value' => 0.0,
            'progress' => 0.0,
            'previous' => null,
            'previous_progress' => null,
            'target' => 0.0,
            'avoid' => false,
            'change' => [
                'percentage' => null,
                'is_increase' => null,
                'progress' => null,
                'value' => null,
            ],
        ]);
});
