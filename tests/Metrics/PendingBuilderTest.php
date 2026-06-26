<?php

declare(strict_types=1);

use RoundlyConsulting\Metrics\Enums\Period;
use RoundlyConsulting\Metrics\Exceptions\IncompleteMetricException;
use RoundlyConsulting\Metrics\Facades\Metric;
use RoundlyConsulting\Metrics\Tests\Metrics\Partition\Users as PartitionUsers;
use RoundlyConsulting\Metrics\Tests\Metrics\Progress\Users as ProgressUsers;
use RoundlyConsulting\Metrics\Tests\Metrics\Trend\UsersBalance;
use RoundlyConsulting\Metrics\Tests\Metrics\Value\Users as ValueUsers;
use RoundlyConsulting\Metrics\Tests\Models\User;

it('builds an ad-hoc value identical to a subclass', function (): void {
    createUsersForMetricsTesting([
        ['balance' => 100, 'created_at' => now()->startOfDay()->subDay()],
        ['balance' => 100, 'created_at' => now()->startOfDay()],
    ]);

    $subclass = ValueUsers::make()
        ->name('X')
        ->range(Period::Today)
        ->withChangeAgainstPreviousPeriod()
        ->toArray();

    $inline = Metric::value()
        ->count(User::query())
        ->name('X')
        ->range(Period::Today)
        ->withChangeAgainstPreviousPeriod()
        ->toArray();

    expect($inline)->toBe($subclass);
});

it('builds an ad-hoc trend with a unit and range identical to a subclass', function (): void {
    createUsersForMetricsTesting([
        ['balance' => 100, 'created_at' => '2023-02-26 10:00:00'],
        ['balance' => 80, 'created_at' => '2023-02-27 10:00:00'],
        ['balance' => 40, 'created_at' => '2023-02-27 11:05:00'],
    ]);

    $subclass = UsersBalance::make()->name('X')->daily()->toArray();

    $inline = Metric::trend()
        ->sum(User::query(), 'balance')
        ->name('X')
        ->daily()
        ->toArray();

    expect($inline)->toBe($subclass)
        ->and($inline['result']['trends'])->toBe([
            '2023-02-26' => 100.0,
            '2023-02-27' => 120.0,
        ]);
});

it('builds an ad-hoc progress with a target identical to a subclass', function (): void {
    createUsersForMetricsTesting([100, 100]);

    $subclass = ProgressUsers::make()->name('X')->target(5)->toArray();

    $inline = Metric::progress()
        ->count(User::query())
        ->name('X')
        ->target(5)
        ->toArray();

    expect($inline)->toBe($subclass)
        ->and($inline['result']['progress'])->toBe(40.0);
});

it('builds an ad-hoc partition identical to a subclass', function (): void {
    createUsersForMetricsTesting([
        ['balance' => 100, 'type' => 'user'],
        ['balance' => 150, 'type' => 'user'],
        ['balance' => 130, 'type' => 'admin'],
    ]);

    $subclass = PartitionUsers::make('type')->name('X')->toArray();

    $inline = Metric::partition()
        ->count(User::query(), 'type')
        ->name('X')
        ->toArray();

    expect($inline)->toBe($subclass);
});

it('throws when an ad-hoc value is resolved without a query', function (): void {
    Metric::value()->toArray();
})->throws(IncompleteMetricException::class);

it('throws when an ad-hoc trend is resolved without a query', function (): void {
    Metric::trend()->toArray();
})->throws(IncompleteMetricException::class);

it('throws when an ad-hoc partition is resolved without a query', function (): void {
    Metric::partition()->toArray();
})->throws(IncompleteMetricException::class);
