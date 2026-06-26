<?php

declare(strict_types=1);

use RoundlyConsulting\Metrics\Enums\Period;
use RoundlyConsulting\Metrics\Exceptions\InvalidRangeException;
use RoundlyConsulting\Metrics\Tests\Metrics\Value\Users;

it('compares against an arbitrary range instead of the previous period', function (): void {
    createUsersForMetricsTesting([
        ['balance' => 1, 'created_at' => '2023-03-01 10:00:00'],
        ['balance' => 1, 'created_at' => '2023-03-05 10:00:00'],
        ['balance' => 1, 'created_at' => '2023-02-15 10:00:00'],
        ['balance' => 1, 'created_at' => '2022-01-10 10:00:00'],
        ['balance' => 1, 'created_at' => '2022-06-10 10:00:00'],
        ['balance' => 1, 'created_at' => '2022-09-10 10:00:00'],
    ]);

    $metrics = Users::make()->range(Period::ThisMonth)->compareTo(Period::LastYear)->toArray();

    expect($metrics['result']['value'])->toBe(2.0)
        ->and($metrics['result']['previous'])->toBe(3.0);
});

it('still compares against the previous period without compareTo', function (): void {
    createUsersForMetricsTesting([
        ['balance' => 1, 'created_at' => '2023-03-01 10:00:00'],
        ['balance' => 1, 'created_at' => '2023-03-05 10:00:00'],
        ['balance' => 1, 'created_at' => '2023-02-15 10:00:00'],
        ['balance' => 1, 'created_at' => '2022-06-10 10:00:00'],
    ]);

    $metrics = Users::make()->range(Period::ThisMonth)->withChangeAgainstPreviousPeriod()->toArray();

    expect($metrics['result']['value'])->toBe(2.0)
        ->and($metrics['result']['previous'])->toBe(1.0);
});

it('throws for an invalid comparison range', function (): void {
    Users::make()->range(Period::ThisMonth)->compareTo('NOPE')->toArray();
})->throws(InvalidRangeException::class);
