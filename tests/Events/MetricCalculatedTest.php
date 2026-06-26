<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use RoundlyConsulting\Metrics\Events\MetricCalculated;
use RoundlyConsulting\Metrics\Facades\Metric;
use RoundlyConsulting\Metrics\Tests\Metrics\Value\Users;
use RoundlyConsulting\Metrics\Tests\Models\User;

it('fires an event when a registered metric is calculated', function (): void {
    Event::fake();

    createUsersForMetricsTesting([['balance' => 1, 'created_at' => now()]]);

    Metric::register('users', fn () => Users::make());

    Metric::get('users')->range('TODAY')->toArray();

    Event::assertDispatched(MetricCalculated::class, fn (MetricCalculated $event): bool => $event->key === 'users'
        && $event->range === 'TODAY'
        && $event->fromCache === false
        && $event->durationMs >= 0.0);
});

it('reports a null key for ad-hoc metrics', function (): void {
    Event::fake();

    Metric::value()->count(User::query())->toArray();

    Event::assertDispatched(MetricCalculated::class, fn (MetricCalculated $event): bool => $event->key === null);
});

it('reports cache hits in the event', function (): void {
    config()->set('metrics.cache.enabled', true);
    config()->set('metrics.cache.store', 'array');

    createUsersForMetricsTesting([['balance' => 1, 'created_at' => now()]]);

    Metric::value()->count(User::query())->range('TODAY')->cacheKey('k')->cache(120)->toArray();

    Event::fake();

    Metric::value()->count(User::query())->range('TODAY')->cacheKey('k')->cache(120)->toArray();

    Event::assertDispatched(MetricCalculated::class, fn (MetricCalculated $event): bool => $event->fromCache === true);
});
