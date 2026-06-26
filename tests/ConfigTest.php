<?php

declare(strict_types=1);

use RoundlyConsulting\Metrics\Enums\Unit;
use RoundlyConsulting\Metrics\Tests\Metrics\Trend\QueryExpressions\MarkerQueryExpression;
use RoundlyConsulting\Metrics\Tests\Metrics\Trend\UsersBalance;
use RoundlyConsulting\Metrics\Tests\Metrics\Value\Users;

it('publishes the config file', function (): void {
    $target = config_path('metrics.php');

    if (file_exists($target)) {
        unlink($target);
    }

    $this->artisan('vendor:publish', ['--tag' => 'metrics-config'])->assertSuccessful();

    expect(file_exists($target))->toBeTrue();

    unlink($target);
});

it('reads trend drivers from config', function (): void {
    config()->set('metrics.trend_drivers', ['sqlite' => MarkerQueryExpression::class]);

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
