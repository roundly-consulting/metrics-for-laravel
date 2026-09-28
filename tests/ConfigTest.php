<?php

declare(strict_types=1);

use RoundlyConsulting\Metrics\Enums\Unit;
use RoundlyConsulting\Metrics\Facades\Metrics;
use RoundlyConsulting\Metrics\Tests\Metrics\Trend\QueryExpressions\MarkerQueryExpression;
use RoundlyConsulting\Metrics\Tests\Metrics\Trend\UsersBalance;
use RoundlyConsulting\Metrics\Tests\Metrics\Value\Users;
use RoundlyConsulting\Metrics\Tests\Models\User;
use RoundlyConsulting\Metrics\Types\Trend\PendingTrend;
use RoundlyConsulting\Metrics\Types\Trend\Trend;
use RoundlyConsulting\Testing\Database\DriverMatrix;

it('publishes the config file', function (): void {
    $target = config_path('metrics.php');

    if (file_exists($target)) {
        unlink($target);
    }

    $this->artisan('vendor:publish', ['--tag' => 'metrics-config'])->assertSuccessful();

    expect(file_exists($target))->toBeTrue();

    unlink($target);
});

/**
 * Keyed by the driver the suite is actually running on, not a hard-coded 'sqlite'.
 *
 * This test exists to prove `metrics.trend_drivers` really drives the dispatch — and the
 * literal meant it only ever proved that on the sqlite leg. On any other engine the marker
 * was registered under a key that never matched, so the metric threw
 * MissingTrendQueryExpressionException instead of the marker's own RuntimeException: the
 * config-dispatch claim went untested exactly where it mattered most.
 */
it('reads trend drivers from config', function (): void {
    config()->set('metrics.trend_drivers', [DriverMatrix::driver() => MarkerQueryExpression::class]);

    createUsersForMetricsTesting([
        ['balance' => 100, 'created_at' => '2023-02-26 10:00:00'],
    ]);

    UsersBalance::make()->daily()->toArray();
})->throws(RuntimeException::class, 'marker driver used');

it('applies the configured default range, unit and precision', function (): void {
    config()->set('metrics.default_range', 'TODAY');
    config()->set('metrics.precision', 3);
    config()->set('metrics.default_unit', 'WEEK');

    $value = Users::make();
    $trend = UsersBalance::make();

    expect($value->toArray()['range']['current'])->toBe('TODAY')
        ->and((fn () => $this->roundingPrecision)->call($value))->toBe(3)
        ->and((fn () => $this->unit)->call($trend))->toBe(Unit::Week);
});

/**
 * `config('metrics.trend_drivers')` is the one driver registry. A public static
 * `$queryExpressions` used to live on the ComputesTrend trait, so `Trend` and the
 * `PendingTrend` behind `Metrics::trend()` each had their own copy: a driver registered on
 * `Trend::$queryExpressions`, as the README said to, never reached `Metrics::trend()`.
 */
it('dispatches both trend styles through the configured drivers alone', function (): void {
    config()->set('metrics.trend_drivers', [DriverMatrix::driver() => MarkerQueryExpression::class]);

    createUsersForMetricsTesting([['balance' => 100, 'created_at' => '2023-02-26 10:00:00']]);

    expect(fn () => Metrics::trend()->count(User::query(), 'id')->result())->toThrow(RuntimeException::class, 'marker driver used')
        ->and(fn () => UsersBalance::make()->result())->toThrow(RuntimeException::class, 'marker driver used')
        ->and(property_exists(Trend::class, 'queryExpressions'))->toBeFalse()
        ->and(property_exists(PendingTrend::class, 'queryExpressions'))->toBeFalse();
});
