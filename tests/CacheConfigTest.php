<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use RoundlyConsulting\Metrics\Exceptions\InvalidConfigurationException;
use RoundlyConsulting\Metrics\Exceptions\MetricsException;
use RoundlyConsulting\Metrics\Tests\Metrics\Value\Users;

/*
 * `.env` values reach config as strings (`METRICS_CACHE_TTL=600` → '600'), so the
 * cache settings must be read as env strings, not only as native PHP values.
 */

function todayUserCount(): mixed
{
    return Users::make()->range('TODAY')->toArray()['result']['value'];
}

/**
 * Re-evaluate the shipped config file's cache block with the given env vars set,
 * exactly as a host app boots it from `.env`.
 *
 * @param  array<string, string>  $env
 */
function bootCacheConfigFromEnv(array $env): void
{
    foreach ($env as $name => $value) {
        putenv("{$name}={$value}");
        $_ENV[$name] = $_SERVER[$name] = $value;
    }

    try {
        /** @var array{cache: array<string, mixed>} $config */
        $config = require __DIR__.'/../config/metrics.php';

        config()->set('metrics.cache', $config['cache']);
    } finally {
        foreach (array_keys($env) as $name) {
            putenv($name);
            unset($_ENV[$name], $_SERVER[$name]);
        }
    }
}

it('honours a METRICS_CACHE_TTL read from .env', function (): void {
    bootCacheConfigFromEnv(['METRICS_CACHE_ENABLED' => 'true', 'METRICS_CACHE_TTL' => '600']);
    config()->set('metrics.cache.store', 'array');

    expect(config('metrics.cache.ttl'))->toBe('600');

    createUsersForMetricsTesting([['balance' => 1, 'created_at' => now()]]);

    expect(todayUserCount())->toBe(1.0);

    createUsersForMetricsTesting([['balance' => 1, 'created_at' => now()]]);

    // Past the 300s default but inside the configured 600s: still cached.
    Carbon::setTestNow(now()->addSeconds(400));
    expect(todayUserCount())->toBe(1.0);

    // Past the configured 600s: recomputed.
    Carbon::setTestNow(now()->addSeconds(250));
    expect(todayUserCount())->toBe(2.0);
});

it('honours a string ttl set through a config override', function (): void {
    config()->set('metrics.cache.enabled', true);
    config()->set('metrics.cache.store', 'array');
    config()->set('metrics.cache.ttl', '120');

    createUsersForMetricsTesting([['balance' => 1, 'created_at' => now()]]);
    expect(todayUserCount())->toBe(1.0);

    createUsersForMetricsTesting([['balance' => 1, 'created_at' => now()]]);

    // A 120s ttl has expired well before the 300s default would have.
    Carbon::setTestNow(now()->addSeconds(150));
    expect(todayUserCount())->toBe(2.0);
});

it('rejects an unusable cache ttl instead of silently using the default', function (mixed $ttl): void {
    config()->set('metrics.cache.enabled', true);
    config()->set('metrics.cache.store', 'array');
    config()->set('metrics.cache.ttl', $ttl);

    todayUserCount();
})->with([
    'not a number' => 'ten minutes',
    'zero' => '0',
    'negative' => '-60',
    'decimal' => '1.5',
    'above one year' => 31_536_001,
])->throws(InvalidConfigurationException::class, 'metrics.cache.ttl');

it('throws a MetricsException for an unusable cache ttl', function (): void {
    config()->set('metrics.cache.enabled', true);
    config()->set('metrics.cache.ttl', 'soon');

    expect(fn () => todayUserCount())->toThrow(MetricsException::class);
});

it('falls back to the 300s default when no ttl is configured', function (): void {
    config()->set('metrics.cache.enabled', true);
    config()->set('metrics.cache.store', 'array');
    config()->set('metrics.cache.ttl', null);

    createUsersForMetricsTesting([['balance' => 1, 'created_at' => now()]]);
    expect(todayUserCount())->toBe(1.0);

    createUsersForMetricsTesting([['balance' => 1, 'created_at' => now()]]);

    Carbon::setTestNow(now()->addSeconds(299));
    expect(todayUserCount())->toBe(1.0);

    Carbon::setTestNow(now()->addSeconds(2));
    expect(todayUserCount())->toBe(2.0);
});

it('reads METRICS_CACHE_ENABLED=off from .env as disabled', function (): void {
    bootCacheConfigFromEnv(['METRICS_CACHE_ENABLED' => 'off']);
    config()->set('metrics.cache.store', 'array');

    createUsersForMetricsTesting([['balance' => 1, 'created_at' => now()]]);
    expect(todayUserCount())->toBe(1.0);

    createUsersForMetricsTesting([['balance' => 1, 'created_at' => now()]]);
    expect(todayUserCount())->toBe(2.0);
});

it('reads METRICS_CACHE_ENABLED=1 from .env as enabled', function (): void {
    bootCacheConfigFromEnv(['METRICS_CACHE_ENABLED' => '1']);
    config()->set('metrics.cache.store', 'array');

    createUsersForMetricsTesting([['balance' => 1, 'created_at' => now()]]);
    expect(todayUserCount())->toBe(1.0);

    createUsersForMetricsTesting([['balance' => 1, 'created_at' => now()]]);
    expect(todayUserCount())->toBe(1.0);
});

it('throws the package exception for a METRICS_CACHE_ENABLED typo (strict config)', function (): void {
    bootCacheConfigFromEnv(['METRICS_CACHE_ENABLED' => 'disabled']);
    config()->set('metrics.cache.store', 'array');

    expect(config('metrics.cache.enabled'))->toBe('disabled')
        ->and(fn () => todayUserCount())->toThrow(
            InvalidConfigurationException::class,
            'Configuration value [metrics.cache.enabled] must be a boolean (true/false, 1/0, on/off or yes/no), [disabled] given.',
        );
});
