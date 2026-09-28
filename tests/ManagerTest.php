<?php

declare(strict_types=1);

use RoundlyConsulting\Metrics\Exceptions\UnknownMetricException;
use RoundlyConsulting\Metrics\Facades\Metrics;
use RoundlyConsulting\Metrics\MetricsManager;
use RoundlyConsulting\Metrics\Tests\Metrics\Value\Users;
use RoundlyConsulting\Metrics\Types\Partition\PendingPartition;
use RoundlyConsulting\Metrics\Types\Progress\PendingProgress;
use RoundlyConsulting\Metrics\Types\Trend\PendingTrend;
use RoundlyConsulting\Metrics\Types\Value\PendingValue;

it('registers and resolves metrics by key', function (): void {
    Metrics::register('users', Users::class);

    expect(Metrics::get('users'))->toBeInstanceOf(Users::class)
        ->and(Metrics::has('users'))->toBeTrue()
        ->and(Metrics::get('users')->key())->toBe('users');
});

it('lists registered metric keys without resolving them', function (): void {
    Metrics::register('a', Users::class);
    Metrics::register('b', Users::class);

    expect(Metrics::keys())->toBe(['a', 'b']);
});

it('resolves a registered instance and closure', function (): void {
    $instance = Users::make();

    Metrics::register('instance', $instance);
    Metrics::register('closure', fn () => Users::make());

    expect(Metrics::get('instance'))->toBeInstanceOf(Users::class)->not->toBe($instance)
        ->and(Metrics::get('closure'))->toBeInstanceOf(Users::class);
});

it('returns all registered metrics resolved', function (): void {
    Metrics::register('a', Users::class);
    Metrics::register('b', fn () => Users::make());

    expect(Metrics::all())
        ->toHaveKeys(['a', 'b'])
        ->each->toBeInstanceOf(Users::class);
});

it('throws when resolving an unknown metric key', function (): void {
    Metrics::get('does-not-exist');
})->throws(UnknownMetricException::class, 'No metric is registered under the `does-not-exist` key.');

it('resolves the manager as a singleton', function (): void {
    expect(app(MetricsManager::class))->toBe(app('metrics'));
});

it('exposes ad-hoc builders from the facade', function (): void {
    expect(Metrics::value())->toBeInstanceOf(PendingValue::class)
        ->and(Metrics::trend())->toBeInstanceOf(PendingTrend::class)
        ->and(Metrics::progress())->toBeInstanceOf(PendingProgress::class)
        ->and(Metrics::partition())->toBeInstanceOf(PendingPartition::class);
});

/**
 * A registered instance is a template, not shared state: the manager is a singleton, and
 * under Octane it outlives the request, so a `range()` or `timezone()` one caller applied
 * to the instance itself used to reach every later `get()` — another request's `?period=`.
 */
it('hands out a fresh copy of a registered instance on every get', function (): void {
    $instance = Users::make();
    Metrics::register('reg', $instance);

    Metrics::get('reg')->range('TODAY')->timezone('Asia/Tokyo');

    $next = Metrics::get('reg')->toArray();

    expect($next['range']['current'])->toBe('ALL')
        ->and((fn () => $this->timezoneOverride)->call(Metrics::get('reg')))->toBeNull()
        ->and($instance->toArray()['range']['current'])->toBe('ALL')
        ->and(Metrics::get('reg'))->not->toBe(Metrics::get('reg'));
});

it('does not leave a dashboard range or timezone on a registered instance', function (): void {
    Metrics::register('reg', Users::make());

    Metrics::dashboard(['reg'])->range('TODAY')->timezone('Asia/Tokyo')->toArray();

    expect(Metrics::get('reg')->toArray()['range']['current'])->toBe('ALL')
        ->and((fn () => $this->timezoneOverride)->call(Metrics::get('reg')))->toBeNull();
});
