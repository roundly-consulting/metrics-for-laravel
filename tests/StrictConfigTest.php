<?php

declare(strict_types=1);

use RoundlyConsulting\Metrics\Enums\Unit;
use RoundlyConsulting\Metrics\Exceptions\InvalidConfigurationException;
use RoundlyConsulting\Metrics\Support\ResultCache;
use RoundlyConsulting\Metrics\Support\Timezones;
use RoundlyConsulting\Metrics\Tests\Metrics\Partition\Users as PartitionUsers;
use RoundlyConsulting\Metrics\Tests\Metrics\Trend\UsersBalance;
use RoundlyConsulting\Metrics\Tests\Metrics\Value\Users;
use RoundlyConsulting\Testing\Database\DriverMatrix;

/**
 * A typo in the host's metrics config fails loudly. Before: an unknown `default_unit` or
 * `default_range` and a non-int `precision` were silently ignored, and a non-string cache
 * store, prefix, timezone or "Other" label quietly became the default. A blank value (a
 * host's `KEY=`) is not set and takes the default.
 */
it('refuses an unknown default unit instead of ignoring it (strict config)', function (mixed $unit): void {
    config()->set('metrics.default_unit', $unit);

    expect(fn () => UsersBalance::make())
        ->toThrow(InvalidConfigurationException::class, 'metrics.default_unit');
})->with(['typo' => 'DAYS', 'lowercase' => 'day', 'int' => 7]);

it('refuses an unknown default range instead of ignoring it (strict config)', function (mixed $range): void {
    config()->set('metrics.default_range', $range);

    expect(fn () => Users::make())
        ->toThrow(InvalidConfigurationException::class, 'metrics.default_range');
})->with(['typo' => 'MONTH_TO_DATE', 'unknown int' => 31, 'array' => [['ALL']]]);

it('accepts a default range given as an int (strict config)', function (): void {
    config()->set('metrics.default_range', 30);

    expect(Users::make()->toArray()['range']['current'])->toBe('30');
});

it('refuses a non-integer precision instead of ignoring it (strict config)', function (mixed $precision): void {
    config()->set('metrics.precision', $precision);

    expect(fn () => Users::make())
        ->toThrow(InvalidConfigurationException::class, 'metrics.precision');
})->with(['word' => 'two', 'decimal' => '1.5', 'float' => 2.0]);

it('reads a canonical precision string and keeps the default when unset (strict config)', function (): void {
    config()->set('metrics.precision', '2');
    expect((fn () => $this->roundingPrecision)->call(Users::make()))->toBe(2);

    config()->set('metrics.precision', null);
    config()->set('metrics.default_unit', null);
    config()->set('metrics.default_range', null);
    expect((fn () => $this->roundingPrecision)->call(Users::make()))->toBe(0);
    UsersBalance::make();
});

it('reads a blank range, unit and precision as not set, keeping the metric defaults (strict config)', function (string $blank): void {
    config()->set('metrics.default_range', $blank);
    config()->set('metrics.default_unit', $blank);
    config()->set('metrics.precision', $blank);

    $value = Users::make();
    $trend = UsersBalance::make();

    expect((fn () => $this->roundingPrecision)->call($value))->toBe(0)
        ->and((fn () => $this->range)->call($value))->toBe('ALL')
        ->and((fn () => $this->unit)->call($trend))->toBe(Unit::Day);
})->with(['empty' => '', 'whitespace' => '  ']);

it('refuses a non-string cache store or prefix (strict config)', function (string $key, mixed $value, Closure $read): void {
    config()->set($key, $value);

    expect($read)->toThrow(InvalidConfigurationException::class, $key);
})->with([
    'array store' => ['metrics.cache.store', ['array'], fn () => ResultCache::repository()],
    'int prefix' => ['metrics.cache.prefix', 3, fn () => ResultCache::prefix()],
]);

it('defaults an unset or blank cache store and prefix (strict config)', function (?string $unset): void {
    config()->set('metrics.cache.store', $unset);
    config()->set('metrics.cache.prefix', $unset);

    expect(ResultCache::prefix())->toBe('metrics')
        ->and(ResultCache::repository())->toBe(cache()->store());
})->with(['absent' => null, 'empty' => '', 'whitespace' => ' ']);

it('refuses a non-string reporting timezone (strict config)', function (mixed $timezone): void {
    config()->set('metrics.timezone', $timezone);

    expect(fn () => Timezones::reporting())
        ->toThrow(InvalidConfigurationException::class, 'metrics.timezone');
})->with(['array' => [['UTC']], 'int' => 1]);

it('reads the reporting timezone, the storage clock when unset or blank (strict config)', function (): void {
    config()->set('metrics.timezone', 'Europe/Bratislava');
    expect(Timezones::reporting())->toBe('Europe/Bratislava');

    config()->set('metrics.timezone', null);
    expect(Timezones::reporting())->toBe(Timezones::storage());

    config()->set('metrics.timezone', '');
    expect(Timezones::reporting())->toBe(Timezones::storage());

    config()->set('metrics.timezone', '  ');
    expect(Timezones::reporting())->toBe(Timezones::storage());
});

it('refuses a non-string partition "Other" label (strict config)', function (mixed $label): void {
    config()->set('metrics.partition.other_label', $label);

    $metric = PartitionUsers::make('type');

    expect(fn () => (fn () => $this->resolveOtherLabel())->call($metric))
        ->toThrow(InvalidConfigurationException::class, 'metrics.partition.other_label');
})->with(['array' => [['Other']], 'int' => 5]);

it('reads a blank partition "Other" label as not set (strict config)', function (): void {
    config()->set('metrics.partition.other_label', '');

    $metric = PartitionUsers::make('type');

    expect((fn () => $this->resolveOtherLabel())->call($metric))->toBe('Other');
});

it('refuses a trend driver map entry that is not a query expression (strict config)', function (mixed $class): void {
    config()->set('metrics.trend_drivers', [DriverMatrix::driver() => $class]);

    createUsersForMetricsTesting([['balance' => 100, 'created_at' => '2023-02-26 10:00:00']]);

    expect(fn () => UsersBalance::make()->daily()->toArray())
        ->toThrow(InvalidConfigurationException::class, 'metrics.trend_drivers.'.DriverMatrix::driver());
})->with(['typo' => 'App\\Metrics\\Postgress', 'not an expression' => stdClass::class, 'int' => 5]);

it('refuses a trend driver map that is not an array (strict config)', function (): void {
    config()->set('metrics.trend_drivers', 'sqlite');

    createUsersForMetricsTesting([['balance' => 100, 'created_at' => '2023-02-26 10:00:00']]);

    expect(fn () => UsersBalance::make()->daily()->toArray())
        ->toThrow(InvalidConfigurationException::class, 'metrics.trend_drivers');
});
