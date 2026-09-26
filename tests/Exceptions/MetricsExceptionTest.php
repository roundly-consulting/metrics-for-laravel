<?php

declare(strict_types=1);

use RoundlyConsulting\Metrics\Exceptions\InvalidConfigurationException;
use RoundlyConsulting\Metrics\Exceptions\InvalidRangeException;
use RoundlyConsulting\Metrics\Exceptions\InvalidUnitException;
use RoundlyConsulting\Metrics\Exceptions\MetricsException;
use RoundlyConsulting\Metrics\Exceptions\MissingTrendQueryExpressionException;

it('exposes a base metrics exception for every package exception', function (MetricsException $exception): void {
    expect($exception)
        ->toBeInstanceOf(MetricsException::class)
        ->toBeInstanceOf(Exception::class);
})->with([
    'invalid range' => fn () => InvalidRangeException::for('NOPE'),
    'invalid unit' => fn () => InvalidUnitException::for('NOPE'),
    'missing driver' => fn () => MissingTrendQueryExpressionException::forDriver('mssql'),
    'invalid configuration' => fn () => new InvalidConfigurationException('Configuration value [metrics.cache.ttl] must be an integer.'),
]);
