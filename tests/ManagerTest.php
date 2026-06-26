<?php

declare(strict_types=1);

use RoundlyConsulting\Metrics\Exceptions\UnknownMetricException;
use RoundlyConsulting\Metrics\Facades\Metric;
use RoundlyConsulting\Metrics\MetricsManager;
use RoundlyConsulting\Metrics\Tests\Metrics\Value\Users;
use RoundlyConsulting\Metrics\Types\Partition\PendingPartition;
use RoundlyConsulting\Metrics\Types\Progress\PendingProgress;
use RoundlyConsulting\Metrics\Types\Trend\PendingTrend;
use RoundlyConsulting\Metrics\Types\Value\PendingValue;

it('registers and resolves metrics by key', function (): void {
    Metric::register('users', Users::class);

    expect(Metric::get('users'))->toBeInstanceOf(Users::class)
        ->and(Metric::has('users'))->toBeTrue();
});

it('resolves a registered instance and closure', function (): void {
    $instance = Users::make();

    Metric::register('instance', $instance);
    Metric::register('closure', fn () => Users::make());

    expect(Metric::get('instance'))->toBe($instance)
        ->and(Metric::get('closure'))->toBeInstanceOf(Users::class);
});

it('returns all registered metrics resolved', function (): void {
    Metric::register('a', Users::class);
    Metric::register('b', fn () => Users::make());

    expect(Metric::all())
        ->toHaveKeys(['a', 'b'])
        ->each->toBeInstanceOf(Users::class);
});

it('throws when resolving an unknown metric key', function (): void {
    Metric::get('does-not-exist');
})->throws(UnknownMetricException::class, 'No metric is registered under the `does-not-exist` key.');

it('resolves the manager as a singleton', function (): void {
    expect(app(MetricsManager::class))->toBe(app('metrics'));
});

it('exposes ad-hoc builders from the facade', function (): void {
    expect(Metric::value())->toBeInstanceOf(PendingValue::class)
        ->and(Metric::trend())->toBeInstanceOf(PendingTrend::class)
        ->and(Metric::progress())->toBeInstanceOf(PendingProgress::class)
        ->and(Metric::partition())->toBeInstanceOf(PendingPartition::class);
});
