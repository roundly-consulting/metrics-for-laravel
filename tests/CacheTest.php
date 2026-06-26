<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use RoundlyConsulting\Metrics\Facades\Metric;
use RoundlyConsulting\Metrics\Tests\Metrics\Value\Users;
use RoundlyConsulting\Metrics\Tests\Models\User;

it('does not cache when disabled by default', function (): void {
    createUsersForMetricsTesting([['balance' => 1, 'created_at' => now()]]);

    $first = Users::make()->range('TODAY')->toArray();

    createUsersForMetricsTesting([['balance' => 1, 'created_at' => now()]]);

    $second = Users::make()->range('TODAY')->toArray();

    expect($first['result']['value'])->toBe(1.0)
        ->and($second['result']['value'])->toBe(2.0);
});

it('caches the metric result for the configured ttl', function (): void {
    config()->set('metrics.cache.enabled', true);
    config()->set('metrics.cache.store', 'array');

    createUsersForMetricsTesting([['balance' => 1, 'created_at' => now()]]);

    expect(Users::make()->range('TODAY')->toArray()['result']['value'])->toBe(1.0);

    createUsersForMetricsTesting([['balance' => 1, 'created_at' => now()]]);

    // Within the TTL the cached value is returned unchanged.
    expect(Users::make()->range('TODAY')->toArray()['result']['value'])->toBe(1.0);

    Carbon::setTestNow(now()->addSeconds(400));

    // Past the TTL the metric is recomputed.
    expect(Users::make()->range('TODAY')->toArray()['result']['value'])->toBe(2.0);
});

it('uses a distinct cache key per range', function (): void {
    config()->set('metrics.cache.enabled', true);

    createUsersForMetricsTesting([
        ['balance' => 1, 'created_at' => now()],
        ['balance' => 1, 'created_at' => now()->subDays(3)],
    ]);

    $today = Users::make()->range('TODAY')->toArray()['result']['value'];
    $monthToDate = Users::make()->range('MTD')->toArray()['result']['value'];

    expect($today)->toBe(1.0)
        ->and($monthToDate)->toBe(2.0);
});

it('does not cache when dontCache is used', function (): void {
    config()->set('metrics.cache.enabled', true);

    createUsersForMetricsTesting([['balance' => 1, 'created_at' => now()]]);

    $first = Users::make()->range('TODAY')->dontCache()->toArray();

    createUsersForMetricsTesting([['balance' => 1, 'created_at' => now()]]);

    $second = Users::make()->range('TODAY')->dontCache()->toArray();

    expect($first['result']['value'])->toBe(1.0)
        ->and($second['result']['value'])->toBe(2.0);
});

it('caches via a per-metric ttl and a custom key', function (): void {
    createUsersForMetricsTesting([['balance' => 1, 'created_at' => now()]]);

    Users::make()->range('TODAY')->cacheKey('users:today')->cache(120)->toArray();

    createUsersForMetricsTesting([['balance' => 1, 'created_at' => now()]]);

    $cached = Users::make()->range('TODAY')->cacheKey('users:today')->cache(120)->toArray();

    expect($cached['result']['value'])->toBe(1.0)
        ->and(cache()->has('users:today'))->toBeTrue();
});

it('caches until a given datetime', function (): void {
    createUsersForMetricsTesting([['balance' => 1, 'created_at' => now()]]);

    $metric = Metric::value()
        ->count(User::query())
        ->range('TODAY')
        ->cacheFor(now()->addHour());

    expect($metric->toArray()['result']['value'])->toBe(1.0);

    createUsersForMetricsTesting([['balance' => 1, 'created_at' => now()]]);

    expect(
        Metric::value()->count(User::query())->range('TODAY')->cacheFor(now()->addHour())->toArray()['result']['value']
    )->toBe(1.0);
});
