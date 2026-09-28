<?php

declare(strict_types=1);

use RoundlyConsulting\Metrics\Exceptions\MissingTrendQueryExpressionException;
use RoundlyConsulting\Metrics\Facades\Metrics;
use RoundlyConsulting\Metrics\Tests\Metrics\Partition\Users as PartitionUsers;
use RoundlyConsulting\Metrics\Tests\Metrics\Progress\Users as ProgressUsers;
use RoundlyConsulting\Metrics\Tests\Metrics\Trend\UsersBalance;
use RoundlyConsulting\Metrics\Tests\Models\User;

beforeEach(function (): void {
    createUsersForMetricsTesting([
        ['balance' => 8, 'type' => 'user', 'created_at' => '2023-02-26 10:00:00'],
        ['balance' => 4, 'type' => 'user', 'created_at' => '2023-02-27 10:00:00'],
        ['balance' => 2, 'type' => 'admin', 'created_at' => '2023-02-28 10:00:00'],
    ]);
});

it('builds ad-hoc value metrics for every aggregate', function (string $method, float $expected): void {
    $metrics = Metrics::value()->{$method}(User::query(), 'balance')->toArray();

    expect($metrics['result']['value'])->toBe($expected);
})->with([
    'sum' => ['sum', 14.0],
    'average' => ['average', 5.0],
    'max' => ['max', 8.0],
    'min' => ['min', 2.0],
]);

it('builds ad-hoc trend metrics for every aggregate', function (string $method): void {
    $metrics = Metrics::trend()->{$method}(User::query(), 'balance')->daily()->toArray();

    expect($metrics['result']['trends'])->toBeArray()->not->toBeEmpty();
})->with(['count', 'sum', 'average', 'max', 'min']);

it('builds ad-hoc partition metrics for every aggregate', function (string $method): void {
    $metrics = Metrics::partition()->{$method}(User::query(), 'type', 'balance')->toArray();

    expect($metrics['result']['partitions'])->toHaveKeys(['user', 'admin']);
})->with(['count', 'sum', 'average', 'max', 'min']);

it('caches trend, progress and partition metrics', function (): void {
    config()->set('metrics.cache.enabled', true);

    expect(UsersBalance::make()->daily()->toArray()['result'])->toHaveKey('trends')
        ->and(ProgressUsers::make()->target(10)->toArray()['result'])->toHaveKey('progress')
        ->and(PartitionUsers::make('type')->toArray()['result'])->toHaveKey('partitions');
});

it('caches a value metric for a fixed number of seconds', function (): void {
    $metrics = Metrics::value()->count(User::query())->cacheFor(60)->toArray();

    expect($metrics['result']['value'])->toBe(3.0);
});

it('ignores a non-array trend driver configuration', function (): void {
    config()->set('metrics.trend_drivers', 'not-an-array');

    UsersBalance::make()->daily()->toArray();
})->throws(MissingTrendQueryExpressionException::class);
