<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use RoundlyConsulting\Metrics\Events\MetricCalculated;
use RoundlyConsulting\Metrics\Exceptions\UnexpectedResultException;
use RoundlyConsulting\Metrics\Exceptions\UnknownMetricException;
use RoundlyConsulting\Metrics\Facades\Metrics;
use RoundlyConsulting\Metrics\MetricsManager;
use RoundlyConsulting\Metrics\Tests\Fixtures\MistypedValue;
use RoundlyConsulting\Metrics\Tests\Metrics\Partition\Users as UsersByType;
use RoundlyConsulting\Metrics\Tests\Metrics\Progress\Users as UsersProgress;
use RoundlyConsulting\Metrics\Tests\Metrics\Trend\Users as UsersTrend;
use RoundlyConsulting\Metrics\Tests\Metrics\Value\Users;
use RoundlyConsulting\Metrics\Tests\Models\User;
use RoundlyConsulting\Metrics\Types\Partition\PartitionResult;
use RoundlyConsulting\Metrics\Types\Progress\ProgressResult;
use RoundlyConsulting\Metrics\Types\Trend\TrendResult;
use RoundlyConsulting\Metrics\Types\Value\ValueResult;

// No toReachEveryAction(): metrics has no src/Actions. It is a stateless calculation package
// (see the skill's exemption for stateless computation) — the builders and metric classes are
// the behaviour, and every one of them is reached from the facade below.
it('documents its root and is fakeable', function (): void {
    expect(Metrics::class)->toDocumentItsRoot()->toBeFakeable();
});

beforeEach(function (): void {
    createUsersForMetricsTesting([
        ['balance' => 10, 'type' => 'admin', 'created_at' => now()],
        ['balance' => 20, 'type' => 'user', 'created_at' => now()],
        ['balance' => 30, 'type' => 'user', 'created_at' => now()->subDays(3)],
    ]);
});

it('returns the typed result of each registered metric type through the facade', function (): void {
    Metrics::register('users', Users::class)
        ->register('trend', fn () => UsersTrend::make()->range('7'))
        ->register('progress', fn () => UsersProgress::make()->target(6))
        ->register('by-type', fn () => UsersByType::make('type'));

    $value = Metrics::get('users')->result();
    $trend = Metrics::get('trend')->result();
    $progress = Metrics::get('progress')->result();
    $partition = Metrics::get('by-type')->result();

    expect($value)->toBeInstanceOf(ValueResult::class)
        ->and($value->value())->toBe(3.0)
        ->and($trend)->toBeInstanceOf(TrendResult::class)
        ->and(array_sum($trend->values()))->toBe(3.0)
        ->and($progress)->toBeInstanceOf(ProgressResult::class)
        ->and($progress->progress())->toBe(50.0)
        ->and($progress->target())->toBe(6.0)
        ->and($progress->avoid())->toBeFalse()
        ->and($partition)->toBeInstanceOf(PartitionResult::class)
        ->and($partition->keys())->toBe(['user', 'admin']);
});

it('narrows result() on every ad-hoc builder', function (): void {
    expect(Metrics::value()->sum(User::query(), 'balance')->result()->value())->toBe(60.0)
        ->and(Metrics::trend()->count(User::query(), 'id')->range('7')->result())->toBeInstanceOf(TrendResult::class)
        ->and(Metrics::progress()->count(User::query())->target(3)->result()->progress())->toBe(100.0)
        ->and(Metrics::partition()->count(User::query(), 'type')->result()->values())->toBe([2.0, 1.0]);
});

it('carries the percentage change on a progress result', function (): void {
    $result = UsersProgress::make()->target(4)->range('TODAY')->withChangeAgainstPreviousPeriod()->result();

    expect($result->value())->toBe(2.0)
        ->and($result->previous())->toBe(0.0)
        ->and($result->change())->toBe(100.0)
        ->and($result->previousProgress())->toBe(0.0)
        ->and($result->isIncrease())->toBeTrue();
});

it('restores the typed result from the cache', function (string $key, Closure $metric, string $type): void {
    config()->set('metrics.cache.enabled', true);

    Metrics::register($key, $metric);

    $fresh = Metrics::get($key)->result();

    Event::fake();

    $cached = Metrics::get($key)->result();

    expect($cached)->toBeInstanceOf($type)
        ->and($cached)->not->toBe($fresh)
        ->and($cached->toArray())->toBe($fresh->toArray());

    Event::assertDispatched(MetricCalculated::class, fn (MetricCalculated $event): bool => $event->fromCache);
})->with([
    'value' => ['users', fn () => Users::make()->range('7')->withChangeAgainstPreviousPeriod(), ValueResult::class],
    'trend' => ['trend', fn () => UsersTrend::make()->range('7')->groupBy('type'), TrendResult::class],
    'progress' => ['progress', fn () => UsersProgress::make()->target(4)->range('7')->withChangeAgainstPreviousPeriod(), ProgressResult::class],
    'partition' => ['by-type', fn () => UsersByType::make('type')->labelUsing(fn (int|string $key): string => strtoupper((string) $key)), PartitionResult::class],
]);

it('recalculates when the cached entry is not a result envelope', function (): void {
    config()->set('metrics.cache.enabled', true);

    $metric = Users::make()->cacheKey('users:legacy');

    Cache::put('users:legacy', ['value' => 99.0], 300);

    expect($metric->result()->value())->toBe(3.0)
        ->and(Cache::get('users:legacy'))->toMatchArray(['type' => ValueResult::class]);
});

it('ignores a cached envelope naming a class that is not a result', function (): void {
    config()->set('metrics.cache.enabled', true);

    Users::make()->cacheKey('users:k')->result();

    Cache::put('users:k', [...Cache::get('users:k'), 'type' => stdClass::class], 300);

    expect(Users::make()->cacheKey('users:k')->result()->value())->toBe(3.0);
});

it('refuses a metric whose calculate() returns another result type', function (): void {
    Metrics::register('mistyped', MistypedValue::class);

    Metrics::get('mistyped')->result();
})->throws(
    UnexpectedResultException::class,
    'Metric ['.MistypedValue::class.'] must calculate a ['.ValueResult::class.'], but calculated a ['.TrendResult::class.'].',
);

it('forgets every cached range of one registered metric and nothing else', function (): void {
    config()->set('metrics.cache.enabled', true);

    Metrics::register('users', Users::class)->register('other', Users::class);

    Metrics::get('users')->range('TODAY')->result();
    Metrics::get('users')->range('7')->result();
    Metrics::get('other')->range('7')->result();

    createUsersForMetricsTesting([['created_at' => now()]]);

    Metrics::forget('users');

    expect(Metrics::get('users')->range('TODAY')->result()->value())->toBe(3.0)
        ->and(Metrics::get('users')->range('7')->result()->value())->toBe(4.0)
        ->and(Metrics::get('other')->range('7')->result()->value())->toBe(3.0);
});

it('forgets a metric that sets its own cache key', function (): void {
    config()->set('metrics.cache.enabled', true);

    Metrics::register('users', fn () => Users::make()->cacheKey('dashboard:users'));

    Metrics::get('users')->result();
    createUsersForMetricsTesting([['created_at' => now()]]);

    expect(Metrics::get('users')->result()->value())->toBe(3.0);

    Metrics::forget('users');

    expect(Metrics::get('users')->result()->value())->toBe(4.0);
});

it('forgets an unregistered metric by instance', function (): void {
    $metric = fn () => Users::make()->cache();

    $metric()->result();
    createUsersForMetricsTesting([['created_at' => now()]]);

    expect($metric()->result()->value())->toBe(3.0);

    Metrics::forget($metric());

    expect($metric()->result()->value())->toBe(4.0);
});

it('refuses to forget a key that is not registered', function (): void {
    Metrics::forget('nope');
})->throws(UnknownMetricException::class, 'No metric is registered under the `nope` key.');

it('flushes every cached result, custom keys included', function (): void {
    config()->set('metrics.cache.enabled', true);

    Metrics::register('users', Users::class);

    Metrics::get('users')->result();
    Metrics::value()->count(User::query())->cacheKey('adhoc')->result();

    createUsersForMetricsTesting([['created_at' => now()]]);

    Metrics::flushCache();

    expect(Metrics::get('users')->result()->value())->toBe(4.0)
        ->and(Metrics::value()->count(User::query())->cacheKey('adhoc')->result()->value())->toBe(4.0);
});

it('unregisters a metric', function (): void {
    Metrics::register('users', Users::class)->register('other', Users::class);

    Metrics::unregister('users')->unregister('never-registered');

    expect(Metrics::has('users'))->toBeFalse()
        ->and(Metrics::keys())->toBe(['other']);
});

it('serves the same API from the injected manager', function (): void {
    $manager = app(MetricsManager::class);

    $manager->register('users', Users::class);

    expect(Metrics::get('users')->result()->value())->toBe(3.0)
        ->and($manager->get('users'))->toBeInstanceOf(Users::class)
        ->and($manager->value()->count(User::query())->result()->value())->toBe(3.0);
});

it('serves the same result through the metric class without the manager', function (): void {
    expect(Users::make()->result()->value())->toBe(3.0)
        ->and(app(Users::class)->result())->toBeInstanceOf(ValueResult::class);
});
